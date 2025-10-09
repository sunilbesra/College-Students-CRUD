<?php

namespace App\Jobs;

use App\Models\FormSubmission;
use App\Services\FormSubmissionValidator;
use App\Events\FormSubmissionProcessed;
use App\Events\DuplicateEmailDetected;
use App\Jobs\ProcessDuplicateEmailCheck;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Pheanstalk\Pheanstalk;

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
                // Unified path: Validate data BEFORE creating FormSubmission record
                
                // Step 1: Validate the raw data first (including duplicate check)
                try {
                    $validatedData = $this->validateSubmissionData($this->submissionData['data']);
                    
                    Log::info('Data validation passed - proceeding to create FormSubmission', [
                        'operation' => $this->submissionData['operation'],
                        'source' => $this->submissionData['source'],
                        'email' => $this->submissionData['data']['email'] ?? 'N/A'
                    ]);
                    
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $email = $this->submissionData['data']['email'] ?? 'unknown';
                    
                    // Check if this is a duplicate email validation error
                    if (isset($e->errors()['email'])) {
                        $emailErrors = $e->errors()['email'];
                        foreach ($emailErrors as $emailError) {
                            if (str_contains(strtolower($emailError), 'already registered') || 
                                str_contains(strtolower($emailError), 'duplicate')) {
                                
                                Log::info('Consumer validation: Duplicate email detected - queuing async duplicate processing', [
                                    'email' => $email,
                                    'operation' => $this->submissionData['operation'],
                                    'source' => $this->submissionData['source'],
                                    'validation_level' => 'consumer_validation'
                                ]);
                                
                                // Queue asynchronous duplicate email processing (check + notification)
                                ProcessDuplicateEmailCheck::dispatch(
                                    $email,
                                    $this->submissionData['source'],
                                    $this->submissionData['data'],
                                    null, // No existing submission ID yet
                                    null, // No CSV row for form submissions
                                    $this->submissionData['operation']
                                );
                                
                                Log::debug('✅ Duplicate email processing queued asynchronously', [
                                    'email' => $email,
                                    'source' => $this->submissionData['source']
                                ]);
                                
                                // Exit early - duplicate prevented by consumer validation
                                return;
                            }
                        }
                    }
                    
                    // For other validation errors, log and exit
                    Log::warning('Consumer validation failed - preventing FormSubmission creation', [
                        'operation' => $this->submissionData['operation'],
                        'source' => $this->submissionData['source'],
                        'validation_errors' => $e->errors()
                    ]);
                    return;
                }
                
                // Step 2: If validation passed, create FormSubmission record
                $formSubmission = FormSubmission::create([
                    'operation' => $this->submissionData['operation'],
                    'student_id' => $this->submissionData['student_id'] ?? null,
                    'data' => $validatedData, // Store validated data
                    'source' => $this->submissionData['source'],
                    'ip_address' => $this->submissionData['ip_address'] ?? null,
                    'user_agent' => $this->submissionData['user_agent'] ?? null,
                    'status' => 'processing',
                    'csv_row' => $this->submissionData['csv_row'] ?? null
                ]);
                
                Log::info('Created FormSubmission record after consumer validation', [
                    'submission_id' => $formSubmission->_id,
                    'operation' => $formSubmission->operation,
                    'source' => $formSubmission->source,
                    'email' => $formSubmission->data['email'] ?? 'N/A'
                ]);
            }

            // Update status to processing
            $formSubmission->update(['status' => 'processing']);
            
            // Step 2: Data is already validated - just process the form submission

            // Step 3: Process the validated data (store in FormSubmission only)
            $result = $this->processFormSubmission($formSubmission, $formSubmission->data);

            // Step 4: Update FormSubmission as completed with processed data
            $formSubmission->update([
                'status' => 'completed',
                'processed_at' => now(),
                'error_message' => null
            ]);

            // Mirror completed submission to Beanstalk for external consumers
            $this->mirrorToBeanstalk('form_submission_processed', [
                'submission_id' => $formSubmission->_id,
                'operation' => $formSubmission->operation,
                'source' => $formSubmission->source,
                'data' => $formSubmission->data,
                'status' => 'completed',
                'processed_at' => $formSubmission->processed_at
            ], $formSubmission->_id);

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
                'email' => $formSubmission->data['email'] ?? 'N/A'
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
                            
                            // Queue asynchronous duplicate email processing
                            Log::warning('🔄 QUEUING: Async duplicate processing (from Beanstalk job)', [
                                'job' => 'ProcessFormSubmissionData',
                                'submission_id' => $formSubmission->_id,
                                'email' => $formSubmission->data['email'] ?? 'unknown',
                                'source' => $formSubmission->source,
                                'validation_error' => $emailError
                            ]);
                            
                            ProcessDuplicateEmailCheck::dispatch(
                                $formSubmission->data['email'] ?? 'unknown',
                                $formSubmission->source,
                                $formSubmission->data,
                                $formSubmission->_id,
                                null, // No CSV row for regular form submissions
                                $formSubmission->operation ?? 'create'
                            );
                            
                            Log::debug('✅ Async duplicate processing queued from Beanstalk job');
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
     * Validate CSV data using FormSubmissionValidator
     */
    private function validateCsvData(array $data, $ignoreId = null): array
    {
        // Use FormSubmissionValidator to validate CSV data
        // Pass ignoreId to exclude current submission from duplicate check
        return FormSubmissionValidator::validateCsv($data, $ignoreId);
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
        
        // Comprehensive duplicate check for create operations
        $duplicateResult = $this->checkForDuplicateEmail($email, $formSubmission->_id);
        
        if ($duplicateResult['is_duplicate']) {
            $existingSubmission = $duplicateResult['existing_submission'];
            
            Log::warning('Duplicate email detected in ProcessFormSubmissionData job', [
                'email' => $email,
                'existing_submission_id' => $existingSubmission->_id,
                'current_submission_id' => $formSubmission->_id,
                'source' => $formSubmission->source,
                'operation' => $formSubmission->operation
            ]);
            
            // Mark current submission as failed due to duplicate
            $formSubmission->update([
                'status' => 'failed',
                'error_message' => "Duplicate email detected: A form submission with email '{$email}' already exists (ID: {$existingSubmission->_id})",
                'duplicate_of' => $existingSubmission->_id
            ]);
            
            // Queue async duplicate processing for comprehensive analytics and notifications
            ProcessDuplicateEmailCheck::dispatch(
                $email,
                $formSubmission->source,
                $formSubmission->data,
                $formSubmission->_id,
                $formSubmission->csv_row ?? null,
                $formSubmission->operation
            )->onQueue(env('BEANSTALKD_DUPLICATE_CHECK_QUEUE', 'duplicate_check_jobs'));
            
            Log::info('Queued async duplicate processing from ProcessFormSubmissionData', [
                'email' => $email,
                'current_submission_id' => $formSubmission->_id,
                'existing_submission_id' => $existingSubmission->_id,
                'job' => 'ProcessDuplicateEmailCheck'
            ]);
            
            return [
                'action' => 'duplicate_detected',
                'existing_submission_id' => $existingSubmission->_id,
                'current_submission_id' => $formSubmission->_id,
                'email' => $email,
                'status' => 'failed'
            ];
        }

        Log::info('Form submission create operation processed - no duplicates found', [
            'submission_id' => $formSubmission->_id,
            'email' => $email,
            'name' => $validatedData['name'] ?? 'N/A',
            'source' => $formSubmission->source
        ]);

        return [
            'action' => 'created',
            'submission_id' => $formSubmission->_id,
            'email' => $email,
            'status' => 'completed'
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
     * Check for duplicate email in existing form submissions
     */
    private function checkForDuplicateEmail(string $email, ?string $ignoreSubmissionId = null): array
    {
        $email = trim(strtolower($email));
        
        // Build query to check for existing submissions with same email
        $query = FormSubmission::where('data.email', $email)
            ->where('status', 'completed')
            ->where('operation', 'create');
        
        // Exclude current submission if provided
        if ($ignoreSubmissionId) {
            $query->where('_id', '!=', $ignoreSubmissionId);
        }
        
        $existingSubmission = $query->first();
        
        if ($existingSubmission) {
            return [
                'is_duplicate' => true,
                'existing_submission' => $existingSubmission,
                'existing_submission_id' => $existingSubmission->_id,
                'email' => $email
            ];
        }
        
        return [
            'is_duplicate' => false,
            'existing_submission' => null,
            'existing_submission_id' => null,
            'email' => $email
        ];
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
                // Step 1: Validate CSV data BEFORE creating FormSubmission record
                $validatedData = $this->validateCsvData($rowData['data']);
                
                Log::debug('CSV row validation passed', [
                    'csv_row' => $rowData['csv_row'] ?? $rowIndex + 1,
                    'email' => $rowData['data']['email'] ?? 'N/A'
                ]);
                
                // Step 2: Check for duplicates before creating FormSubmission
                $email = $validatedData['email'] ?? null;
                if ($email && $rowData['operation'] === 'create') {
                    $duplicateResult = $this->checkForDuplicateEmail($email);
                    
                    if ($duplicateResult['is_duplicate']) {
                        $existingSubmission = $duplicateResult['existing_submission'];
                        
                        Log::warning('CSV row duplicate email detected in job', [
                            'csv_row' => $rowData['csv_row'] ?? $rowIndex + 1,
                            'email' => $email,
                            'existing_submission_id' => $existingSubmission->_id
                        ]);
                        
                        // Create failed FormSubmission record for tracking
                        $formSubmission = FormSubmission::create([
                            'operation' => $rowData['operation'],
                            'student_id' => $rowData['student_id'] ?? null,
                            'data' => $validatedData,
                            'source' => $rowData['source'],
                            'ip_address' => $this->submissionData['ip_address'] ?? null,
                            'user_agent' => $this->submissionData['user_agent'] ?? null,
                            'status' => 'failed',
                            'error_message' => "Duplicate email: A form submission with email '{$email}' already exists (ID: {$existingSubmission->_id})",
                            'duplicate_of' => $existingSubmission->_id,
                            'csv_row' => $rowData['csv_row'] ?? $rowIndex + 1
                        ]);
                        
                        // Queue async duplicate processing
                        ProcessDuplicateEmailCheck::dispatch(
                            $email,
                            $rowData['source'],
                            $validatedData,
                            $formSubmission->_id,
                            $rowData['csv_row'] ?? $rowIndex + 1,
                            $rowData['operation']
                        )->onQueue(env('BEANSTALKD_DUPLICATE_CHECK_QUEUE', 'duplicate_check_jobs'));
                        
                        $errorCount++;
                        continue; // Skip to next row
                    }
                }
                
                // Step 3: Create FormSubmission record after validation and duplicate check pass
                $formSubmission = FormSubmission::create([
                    'operation' => $rowData['operation'],
                    'student_id' => $rowData['student_id'] ?? null,
                    'data' => $validatedData, // Store validated data
                    'source' => $rowData['source'],
                    'ip_address' => $this->submissionData['ip_address'] ?? null,
                    'user_agent' => $this->submissionData['user_agent'] ?? null,
                    'status' => 'processing',
                    'csv_row' => $rowData['csv_row'] ?? $rowIndex + 1
                ]);

                // Process the form submission
                $result = $this->processFormSubmission($formSubmission, $validatedData);

                // Update as completed only if not marked as duplicate
                if ($formSubmission->status !== 'failed') {
                    $formSubmission->update([
                        'status' => 'completed',
                        'data' => $validatedData,
                        'processed_at' => now(),
                        'error_message' => null
                    ]);

                    // Mirror completed CSV row to Beanstalk for external consumers
                    $this->mirrorToBeanstalk('csv_row_processed', [
                        'submission_id' => $formSubmission->_id,
                        'operation' => $formSubmission->operation,
                        'source' => $formSubmission->source,
                        'data' => $validatedData,
                        'status' => 'completed',
                        'csv_row' => $rowData['csv_row'] ?? $rowIndex + 1,
                        'csv_file' => $this->submissionData['csv_file'] ?? 'unknown',
                        'processed_at' => $formSubmission->processed_at
                    ], $formSubmission->_id);
                }
                
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
                    'email' => $formSubmission->data['email'] ?? 'N/A'
                ]);

            } catch (\Illuminate\Validation\ValidationException $e) {
                $errorCount++;
                $email = $rowData['data']['email'] ?? 'unknown';
                
                // Check if this is a duplicate email validation error
                if (isset($e->errors()['email'])) {
                    $emailErrors = $e->errors()['email'];
                    foreach ($emailErrors as $emailError) {
                        if (str_contains(strtolower($emailError), 'already registered') || 
                            str_contains(strtolower($emailError), 'duplicate')) {
                            
                            Log::info('CSV row duplicate email detected - queuing async duplicate processing', [
                                'email' => $email,
                                'csv_row' => $rowData['csv_row'] ?? $rowIndex + 1,
                                'validation_level' => 'consumer_validation'
                            ]);
                            
                            // Queue asynchronous duplicate email processing (check + notification)
                            ProcessDuplicateEmailCheck::dispatch(
                                $email,
                                $rowData['source'] ?? 'csv',
                                $rowData['data'] ?? [],
                                null, // No existing submission ID yet
                                $rowData['csv_row'] ?? $rowIndex + 1,
                                $rowData['operation'] ?? 'create'
                            );
                            
                            Log::debug('✅ CSV duplicate email processing queued asynchronously', [
                                'email' => $email,
                                'csv_row' => $rowData['csv_row'] ?? $rowIndex + 1
                            ]);
                            
                            continue; // Skip to next row - no FormSubmission created for duplicates
                        }
                    }
                }
                
                // For other validation errors, log and continue
                Log::error('CSV row validation failed', [
                    'csv_row' => $rowData['csv_row'] ?? $rowIndex + 1,
                    'validation_errors' => $e->errors(),
                    'data' => $rowData['data'] ?? []
                ]);
                continue; // Skip to next row - no FormSubmission created for invalid data
                
            } catch (\Exception $e) {
                $errorCount++;
                
                // General exception handling
                Log::error('CSV row processing failed', [
                    'csv_row' => $rowData['csv_row'] ?? $rowIndex + 1,
                    'error' => $e->getMessage(),
                    'data' => $rowData['data'] ?? []
                ]);
                continue; // Skip to next row
            }
        }

        Log::info('Batch processing completed', [
            'total_rows' => count($batchData),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'csv_file' => $this->submissionData['csv_file'] ?? 'unknown'
        ]);
    }

    /**
     * Mirror operation to Beanstalk for external consumers
     */
    private function mirrorToBeanstalk(string $action, array $data, ?string $submissionId = null)
    {
        try {
            $pheanstalkHost = env('BEANSTALKD_QUEUE_HOST', '127.0.0.1');
            $pheanstalkPort = env('BEANSTALKD_PORT', 11300);
            $pheanstalk = Pheanstalk::create($pheanstalkHost, $pheanstalkPort);
            
            $mirrorTube = env('BEANSTALKD_FORM_SUBMISSION_TUBE', 'form_submission_json');
            
            $payload = json_encode([
                'action' => $action,
                'submission_id' => $submissionId,
                'data' => $data,
                'queued_at' => now()->toDateTimeString(),
                'source' => 'form_submission_job',
                'mirror_id' => uniqid('mirror_job_', true), // Unique identifier for tracking
            ], JSON_UNESCAPED_UNICODE);
            
            $job = $pheanstalk->useTube($mirrorTube)->put($payload);
            
            Log::info('✅ Form submission successfully mirrored to Beanstalk from job', [
                'action' => $action,
                'tube' => $mirrorTube,
                'submission_id' => $submissionId,
                'job_id' => $job->getId(),
                'payload_size' => strlen($payload) . ' bytes',
                'beanstalk_host' => $pheanstalkHost . ':' . $pheanstalkPort,
                'source' => 'ProcessFormSubmissionData'
            ]);
            
            return $job->getId(); // Return job ID for tracking
            
        } catch (\Pheanstalk\Exception\ConnectionException $e) {
            Log::error("❌ Beanstalkd connection failed for mirror from job ({$action})", [
                'error' => $e->getMessage(),
                'host' => $pheanstalkHost ?? 'unknown',
                'port' => $pheanstalkPort ?? 'unknown',
                'submission_id' => $submissionId,
                'source' => 'ProcessFormSubmissionData'
            ]);
        } catch (\Pheanstalk\Exception\ServerException $e) {
            Log::error("❌ Beanstalkd server error for mirror from job ({$action})", [
                'error' => $e->getMessage(),
                'tube' => $mirrorTube ?? 'unknown',
                'submission_id' => $submissionId,
                'source' => 'ProcessFormSubmissionData'
            ]);
        } catch (\Throwable $e) {
            Log::warning("❌ Failed to write form submission mirror to beanstalk from job ({$action})", [
                'error' => $e->getMessage(),
                'submission_id' => $submissionId,
                'error_type' => get_class($e),
                'source' => 'ProcessFormSubmissionData'
            ]);
        }
        
        return null;
    }
}