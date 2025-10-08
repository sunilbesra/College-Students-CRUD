<?php

namespace App\Jobs;

use App\Models\FormSubmission;
use App\Services\FormSubmissionValidator;
use App\Events\FormSubmissionProcessed;
    use App\Events\DuplicateEmailDetected;
    use Illuminate\Bus\Queueable;
    use Illuminate\Contracts\Queue\ShouldQueue;
    use Illuminate\Foundation\Bus\Dispatchable;
    use Illuminate\Queue\InteractsWithQueue;
    use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessFormSubmissionData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?string $submissionId;
    public array $submissionData;
    
    // Set job timeout and queue
    public $timeout = 300;
    public $tries = 3;

    /**
     * Create a new job instance.
     * For unified architecture: submissionId can be null for direct Beanstalk processing   
     */
    public function __construct(?string $submissionId, array $submissionData)
    {
        $this->submissionId = $submissionId;
        $this->submissionData = $submissionData;
        
        // Set the queue for this job
        $this->onQueue(env('BEANSTALKD_FORM_SUBMISSION_QUEUE', 'form_submission_jobs'));
    }

    /**
     * Execute the job - Unified Architecture Implementation
     * Form/CSV -> Beanstalk -> Laravel Consumer -> Validation -> MongoDB
     */
    public function handle(): void
    {
        Log::info('Starting unified form submission processing from Beanstalk', [
            'submission_id' => $this->submissionId,
            'operation' => $this->submissionData['operation'] ?? 'unknown',
            'source' => $this->submissionData['source'] ?? 'unknown',
            'job_queue' => $this->queue
        ]);

        $formSubmission = null;
        
        try {
            // Check if this is batch processing (CSV with multiple rows)
            if (isset($this->submissionData['batch_data'])) {
                $this->processBatchSubmission();
                return;
            }

            // Step 1: Create FormSubmission record for tracking (consumer creates the tracking record)
            if ($this->submissionId) {
                // Legacy path: FormSubmission already exists
                $formSubmission = FormSubmission::find($this->submissionId);
                if (!$formSubmission) {
                    throw new \Exception("Form submission record not found: {$this->submissionId}");
                }
            } else {
                // Unified path: Create FormSubmission record from Beanstalk data
                $formSubmission = FormSubmission::create([
                    'operation' => $this->submissionData['operation'],
                    'student_id' => $this->submissionData['student_id'] ?? null,
                    'data' => $this->submissionData['data'],
                    'source' => $this->submissionData['source'],
                    'ip_address' => $this->submissionData['ip_address'] ?? null,
                    'user_agent' => $this->submissionData['user_agent'] ?? null,
                    'status' => 'processing'
                ]);
                
                Log::info('Created FormSubmission record from Beanstalk data', [
                    'submission_id' => $formSubmission->_id,
                    'operation' => $formSubmission->operation,
                    'source' => $formSubmission->source
                ]);
            }

            // Update status to processing
            $formSubmission->update(['status' => 'processing']);
            
            // Step 2: Validate the data using FormSubmissionValidator
            // Pass current submission ID to exclude it from duplicate check
            $validatedData = $this->validateSubmissionData($formSubmission->data, $formSubmission->_id);

            // Step 3: Process the validated data (store in FormSubmission only)
            $result = $this->processFormSubmission($formSubmission, $validatedData);

            // Step 4: Update FormSubmission as completed with processed data
            $formSubmission->update([
                'status' => 'completed',
                'data' => $validatedData, // Store validated data
                'processed_at' => now(),
                'error_message' => null
            ]);

            // Fire FormSubmissionProcessed event
            Log::info(' FIRING EVENT: FormSubmissionProcessed (completed)', [
                'job' => 'ProcessFormSubmissionData',
                'submission_id' => $formSubmission->_id,
                'operation' => $formSubmission->operation,
                'source' => $formSubmission->source,
                'status' => 'completed'
            ]);
            event(new FormSubmissionProcessed($formSubmission, 'completed'));
            Log::debug(' FormSubmissionProcessed (completed) event fired successfully');

            Log::info('Form submission validation and processing completed successfully', [
                'submission_id' => $formSubmission->_id,
                'operation' => $formSubmission->operation,
                'source' => $formSubmission->source,
                'email' => $validatedData['email'] ?? 'N/A'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            $errorMessage = 'Validation failed: ' . implode('; ', array_map(
                fn($errors) => is_array($errors) ? implode(', ', $errors) : $errors, 
                $e->errors()
            ));

            if ($formSubmission) {
                // Check if this is a duplicate email validation error
                if (isset($e->errors()['email'])) {
                    $emailErrors = $e->errors()['email'];
                    foreach ($emailErrors as $emailError) {
                        if (str_contains(strtolower($emailError), 'already registered') || 
                            str_contains(strtolower($emailError), 'duplicate')) {
                            
                            // Fire duplicate email detected event
                            Log::warning('🔄 FIRING EVENT: DuplicateEmailDetected (from Beanstalk job)', [
                                'job' => 'ProcessFormSubmissionData',
                                'submission_id' => $formSubmission->_id,
                                'email' => $formSubmission->data['email'] ?? 'unknown',
                                'source' => $formSubmission->source,
                                'validation_error' => $emailError
                            ]);
                            event(new DuplicateEmailDetected(
                                $formSubmission->data['email'] ?? 'unknown',
                                $formSubmission->source,
                                $formSubmission->_id,
                                $formSubmission->data
                            ));
                            Log::debug('✅ DuplicateEmailDetected event fired from Beanstalk job');
                            break;
                        }
                    }
                }

                $formSubmission->update([
                    'status' => 'failed',
                    'error_message' => $errorMessage
                ]);
                
                // Fire FormSubmissionProcessed event for validation failure
                Log::warning(' FIRING EVENT: FormSubmissionProcessed (validation failed)', [
                    'job' => 'ProcessFormSubmissionData',
                    'submission_id' => $formSubmission->_id,
                    'status' => 'failed',
                    'error_type' => 'validation',
                    'error_message' => substr($errorMessage, 0, 100)
                ]);
                event(new FormSubmissionProcessed($formSubmission, 'failed', $errorMessage));
                Log::debug(' FormSubmissionProcessed (validation failed) event fired');
            }

            Log::warning('Form submission validation failed in consumer', [
                'submission_id' => $formSubmission->_id ?? 'unknown',
                'errors' => $e->errors(),
                'data' => $this->submissionData['data'] ?? []
            ]);

            // Don't re-throw validation errors - mark as failed and continue
            return;

        } catch (\Throwable $e) {
            if ($formSubmission) {
                $formSubmission->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ]);
                
                // Fire FormSubmissionProcessed event for general failure
                Log::error('🎯 FIRING EVENT: FormSubmissionProcessed (general failure)', [
                    'job' => 'ProcessFormSubmissionData',
                    'submission_id' => $formSubmission->_id,
                    'status' => 'failed',
                    'error_type' => 'general',
                    'error_message' => substr($e->getMessage(), 0, 100)
                ]);
                event(new FormSubmissionProcessed($formSubmission, 'failed', $e->getMessage()));
                Log::debug('✅ FormSubmissionProcessed (general failure) event fired');
            }

            Log::error('Form submission processing failed in consumer', [
                'submission_id' => $formSubmission->_id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'submission_data' => $this->submissionData
            ]);

            // Re-throw to trigger job retry mechanism
            throw $e;
        }
    }

    /**
     * Validate submission data using FormSubmissionValidator
     */
    private function validateSubmissionData(array $data, $ignoreId = null): array
    {
        // Use FormSubmissionValidator to validate form submission data
        // Pass ignoreId to exclude current submission from duplicate check
        return FormSubmissionValidator::validate($data, $ignoreId);
    }

    /**
     * Process the form submission (validate and store in FormSubmission only)
     */
    private function processFormSubmission(FormSubmission $formSubmission, array $validatedData): array
    {
        $result = [];
        
        switch ($formSubmission->operation) {
            case 'create':
                $result = $this->handleCreateOperation($formSubmission, $validatedData);
                break;
                
            case 'update':
                $result = $this->handleUpdateOperation($formSubmission, $validatedData);
                break;
                
            case 'delete':
                $result = $this->handleDeleteOperation($formSubmission);
                break;
                
            default:
                throw new \InvalidArgumentException("Unknown operation: {$formSubmission->operation}");
        }
        
        return $result;
    }

    /**
     * Handle create operation - validate and check for duplicates
     */
    private function handleCreateOperation(FormSubmission $formSubmission, array $validatedData): array
    {
        $email = $validatedData['email'];
        
        // Check for existing form submission with same email
        $existingSubmission = FormSubmission::where('data.email', $email)
            ->where('status', 'completed')
            ->where('operation', 'create')
            ->first();
            
        if ($existingSubmission) {
            Log::warning('Form submission with email already exists', [
                'email' => $email,
                'existing_submission_id' => $existingSubmission->_id,
                'current_submission_id' => $formSubmission->_id
            ]);
            
            // Mark as duplicate but completed
            $formSubmission->update([
                'error_message' => "Duplicate email: A form submission with email '{$email}' already exists (ID: {$existingSubmission->_id})",
                'duplicate_of' => $existingSubmission->_id
            ]);
            
            return [
                'action' => 'duplicate_detected',
                'existing_submission_id' => $existingSubmission->_id,
                'email' => $email
            ];
        }

        Log::info('Form submission create operation processed', [
            'submission_id' => $formSubmission->_id,
            'email' => $email,
            'name' => $validatedData['name'] ?? 'N/A'
        ]);

        return [
            'action' => 'created',
            'submission_id' => $formSubmission->_id,
            'email' => $email
        ];
    }

    /**
     * Handle update operation - find existing submission and validate
     */
    private function handleUpdateOperation(FormSubmission $formSubmission, array $validatedData): array
    {
        $targetSubmissionId = $formSubmission->student_id; // Using student_id field to reference target submission 
        $email = $validatedData['email'] ?? null;
        
        $targetSubmission = null;
        
        // Find target submission by ID or email
        if ($targetSubmissionId) {
            $targetSubmission = FormSubmission::where('_id', $targetSubmissionId)
                ->where('status', 'completed')
                ->first();
        } elseif ($email) {
            $targetSubmission = FormSubmission::where('data.email', $email)
                ->where('status', 'completed')
                ->where('operation', 'create')
                ->first();
        }
        
        if (!$targetSubmission) {
            throw new \Exception("Target form submission not found for update. ID: {$targetSubmissionId}, Email: " . ($email ?? 'N/A'));
        }

        Log::info('Form submission update operation processed', [
            'submission_id' => $formSubmission->_id,
            'target_submission_id' => $targetSubmission->_id,
            'email' => $email
        ]);

        // Store reference to updated submission
        $formSubmission->update([
            'updated_submission_id' => $targetSubmission->_id
        ]);

        return [
            'action' => 'updated',
            'submission_id' => $formSubmission->_id,
            'target_submission_id' => $targetSubmission->_id,
            'email' => $email
        ];
    }

    /**
     * Handle delete operation - mark existing submission as deleted
     */
    private function handleDeleteOperation(FormSubmission $formSubmission): array
    {
        $targetSubmissionId = $formSubmission->student_id;
        
        if (!$targetSubmissionId) {
            throw new \Exception("Submission ID is required for deletion");
        }
        
        $targetSubmission = FormSubmission::where('_id', $targetSubmissionId)
            ->where('status', 'completed')
            ->first();
            
        if (!$targetSubmission) {
            throw new \Exception("Target form submission not found for deletion. ID: {$targetSubmissionId}");
        }

        $submissionData = [
            'id' => $targetSubmission->_id,
            'name' => $targetSubmission->data['name'] ?? 'N/A',
            'email' => $targetSubmission->data['email'] ?? 'N/A'
        ];

        Log::info('Form submission delete operation processed', [
            'submission_id' => $formSubmission->_id,
            'target_submission_id' => $targetSubmission->_id,
            'target_email' => $submissionData['email']
        ]);

        // Store reference to deleted submission
        $formSubmission->update([
            'deleted_submission_id' => $targetSubmission->_id
        ]);

        return [
            'action' => 'deleted',
            'submission_id' => $formSubmission->_id,
            'target_submission_id' => $targetSubmissionId,
            'submission_data' => $submissionData
        ];
    }

    /**
     * The job failed to process permanently.
     */
    public function failed(\Throwable $exception): void
    {
        // Try to find or create a FormSubmission record for tracking the failure
        $formSubmission = null;
        
        if ($this->submissionId) {
            $formSubmission = FormSubmission::find($this->submissionId);
        } else {
            // For unified architecture, try to create a failure record
            try {
                $formSubmission = FormSubmission::create([
                    'operation' => $this->submissionData['operation'] ?? 'unknown',
                    'student_id' => $this->submissionData['student_id'] ?? null,
                    'data' => $this->submissionData['data'] ?? [],
                    'source' => $this->submissionData['source'] ?? 'unknown',
                    'ip_address' => $this->submissionData['ip_address'] ?? null,
                    'user_agent' => $this->submissionData['user_agent'] ?? null,
                    'status' => 'failed',
                    'error_message' => $exception->getMessage()
                ]);
            } catch (\Throwable $e) {
                // If we can't even create the failure record, just log it
                Log::error('Could not create FormSubmission failure record', [
                    'original_error' => $exception->getMessage(),
                    'creation_error' => $e->getMessage(),
                    'submission_data' => $this->submissionData
                ]);
            }
        }
        
        if ($formSubmission) {
            $formSubmission->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage()
            ]);
        }

        Log::error('Form submission job failed permanently in unified architecture', [
            'submission_id' => $formSubmission->_id ?? 'unknown',
            'operation' => $this->submissionData['operation'] ?? 'unknown',
            'source' => $this->submissionData['source'] ?? 'unknown',
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    /**
     * Process batch submission (CSV with multiple rows)
     */
    private function processBatchSubmission(): void
    {
        $batchData = $this->submissionData['batch_data'];
        $successCount = 0;
        $errorCount = 0;

        Log::info('Starting batch processing for CSV', [
            'total_rows' => count($batchData),
            'operation' => $this->submissionData['operation'],
            'csv_file' => $this->submissionData['csv_file'] ?? 'unknown'
        ]);

        foreach ($batchData as $rowIndex => $rowData) {
            try {
                // Create individual FormSubmission for each row
                $formSubmission = FormSubmission::create([
                    'operation' => $rowData['operation'],
                    'student_id' => $rowData['student_id'] ?? null,
                    'data' => $rowData['data'],
                    'source' => $rowData['source'],
                    'ip_address' => $this->submissionData['ip_address'] ?? null,
                    'user_agent' => $this->submissionData['user_agent'] ?? null,
                    'status' => 'processing'
                ]);

                // Validate the data
                // Pass current submission ID to exclude it from duplicate check
                $validatedData = $this->validateSubmissionData($rowData['data'], $formSubmission->_id);

                // Process the form submission
                $result = $this->processFormSubmission($formSubmission, $validatedData);

                // Update as completed
                $formSubmission->update([
                    'status' => 'completed',
                    'data' => $validatedData,
                    'processed_at' => now(),
                    'error_message' => null
                ]);
                
                // Fire FormSubmissionProcessed event for batch row
                Log::debug('🎯 FIRING EVENT: FormSubmissionProcessed (batch completed)', [
                    'job' => 'ProcessFormSubmissionData',
                    'submission_id' => $formSubmission->_id,
                    'csv_row' => $rowData['csv_row'] ?? $rowIndex + 1,
                    'batch_processing' => true,
                    'status' => 'completed'
                ]);
                event(new FormSubmissionProcessed($formSubmission, 'completed'));
                Log::debug('✅ FormSubmissionProcessed (batch) event fired successfully');

                $successCount++;

                Log::info('Batch row processed successfully', [
                    'submission_id' => $formSubmission->_id,
                    'csv_row' => $rowData['csv_row'] ?? $rowIndex + 1,
                    'email' => $validatedData['email'] ?? 'N/A'
                ]);

            } catch (\Illuminate\Validation\ValidationException $e) {
                $errorCount++;
                
                // Check if this is a duplicate email validation error in batch processing
                if (isset($e->errors()['email']) && isset($formSubmission)) {
                    $emailErrors = $e->errors()['email'];
                    foreach ($emailErrors as $emailError) {
                        if (str_contains(strtolower($emailError), 'already registered') || 
                            str_contains(strtolower($emailError), 'duplicate')) {
                            
                            // Fire duplicate email detected event for CSV row
                            Log::warning('🔄 FIRING EVENT: DuplicateEmailDetected (from CSV batch processing)', [
                                'job' => 'ProcessFormSubmissionData',
                                'submission_id' => $formSubmission->_id ?? 'unknown',
                                'csv_row' => $rowData['csv_row'] ?? $rowIndex + 1,
                                'email' => $rowData['data']['email'] ?? 'unknown',
                                'source' => $rowData['source'] ?? 'csv',
                                'validation_error' => $emailError
                            ]);
                            event(new DuplicateEmailDetected(
                                $rowData['data']['email'] ?? 'unknown',
                                $rowData['source'] ?? 'csv',
                                $formSubmission->_id ?? null,
                                $rowData['data'] ?? [],
                                $rowData['csv_row'] ?? $rowIndex + 1
                            ));
                            Log::debug('✅ DuplicateEmailDetected event fired from CSV batch processing');
                            break;
                        }
                    }
                    
                    // Update submission status to failed
                    if (isset($formSubmission)) {
                        $formSubmission->update([
                            'status' => 'failed',
                            'error_message' => 'Validation failed: ' . implode('; ', array_map(
                                fn($errors) => is_array($errors) ? implode(', ', $errors) : $errors, 
                                $e->errors()
                            ))
                        ]);
                    }
                }
                
                Log::error('Batch row validation failed', [
                    'csv_row' => $rowData['csv_row'] ?? $rowIndex + 1,
                    'validation_errors' => $e->errors(),
                    'data' => $rowData['data'] ?? []
                ]);
                
            } catch (\Throwable $e) {
                $errorCount++;
                Log::error('Batch row processing failed', [
                    'csv_row' => $rowData['csv_row'] ?? $rowIndex + 1,
                    'error' => $e->getMessage(),
                    'data' => $rowData['data'] ?? []
                ]);
            }
        }

        Log::info('Batch processing completed', [
            'total_rows' => count($batchData),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'csv_file' => $this->submissionData['csv_file'] ?? 'unknown'
        ]);
    }
}