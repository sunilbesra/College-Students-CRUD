<?php

namespace App\Jobs;

use App\Models\FormSubmission;
use App\Services\FormSubmissionValidator;
use App\Events\FormSubmissionCreated;
use App\Events\DuplicateEmailDetected;
use App\Jobs\ProcessDuplicateEmailCheck;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ValidateAndInsertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;

    protected $submissionData;
    protected $submissionId;

    /**
     * Create a new job instance.
     */
    public function __construct(?string $submissionId, array $submissionData)
    {
        $this->submissionId = $submissionId;
        $this->submissionData = $submissionData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('🚀 Starting validation and insertion job', [
            'job' => 'ValidateAndInsertJob',
            'submission_id' => $this->submissionId,
            'operation' => $this->submissionData['operation'] ?? 'unknown',
            'source' => $this->submissionData['source'] ?? 'unknown'
        ]);

        try {
            if (isset($this->submissionData['batch_data'])) {
                // Handle batch CSV data
                $this->processBatchData();
            } else {
                // Handle single form submission
                $this->processSingleSubmission();
            }
        } catch (\Exception $e) {
            Log::error('❌ Error in validation and insertion job', [
                'job' => 'ValidateAndInsertJob',
                'submission_id' => $this->submissionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Process single form submission
     */
    private function processSingleSubmission(): void
    {
        Log::info('📝 Processing single form submission', [
            'operation' => $this->submissionData['operation'],
            'source' => $this->submissionData['source'],
            'email' => $this->submissionData['data']['email'] ?? 'N/A'
        ]);

        try {
            // Step 1: Validate data including duplicate check
            $validatedData = $this->validateSubmissionData($this->submissionData['data']);
            
            Log::info('✅ Single submission validation passed', [
                'email' => $validatedData['email'] ?? 'N/A',
                'operation' => $this->submissionData['operation']
            ]);

            // Step 2: Create FormSubmission record in MongoDB
            $formSubmission = FormSubmission::create([
                'operation' => $this->submissionData['operation'],
                'student_id' => $this->submissionData['student_id'] ?? null,
                'data' => $validatedData,
                'source' => $this->submissionData['source'],
                'ip_address' => $this->submissionData['ip_address'] ?? null,
                'user_agent' => $this->submissionData['user_agent'] ?? null,
                'status' => 'processing'
            ]);

            Log::info('✅ FormSubmission created successfully in MongoDB', [
                'submission_id' => $formSubmission->_id,
                'email' => $validatedData['email'] ?? 'N/A',
                'operation' => $formSubmission->operation
            ]);

            // Step 3: Process the actual operation (create/update/delete student)
            $this->processOperation($formSubmission, $validatedData);

            // Step 4: Fire success event
            event(new FormSubmissionCreated(
                $formSubmission,
                $validatedData,
                $this->submissionData['source']
            ));

        } catch (ValidationException $e) {
            $this->handleValidationError($e, $this->submissionData);
        }
    }

    /**
     * Process batch CSV data
     */
    private function processBatchData(): void
    {
        $batchData = $this->submissionData['batch_data'];
        $successCount = 0;
        $errorCount = 0;
        
        Log::info('📄 Processing CSV batch data', [
            'total_rows' => count($batchData),
            'csv_file' => $this->submissionData['csv_file'] ?? 'unknown'
        ]);

        foreach ($batchData as $rowIndex => $rowData) {
            try {
                Log::debug('📋 Processing CSV row', [
                    'csv_row' => $rowData['csv_row'] ?? $rowIndex + 1,
                    'email' => $rowData['data']['email'] ?? 'N/A'
                ]);

                // Step 1: Validate CSV row data including duplicate check
                $validatedData = $this->validateCsvData($rowData['data']);
                
                Log::debug('✅ CSV row validation passed', [
                    'csv_row' => $rowData['csv_row'] ?? $rowIndex + 1,
                    'email' => $validatedData['email'] ?? 'N/A'
                ]);

                // Step 2: Create FormSubmission record in MongoDB
                $formSubmission = FormSubmission::create([
                    'operation' => $rowData['operation'],
                    'student_id' => $rowData['student_id'] ?? null,
                    'data' => $validatedData,
                    'source' => $rowData['source'],
                    'ip_address' => $this->submissionData['ip_address'] ?? null,
                    'user_agent' => $this->submissionData['user_agent'] ?? null,
                    'status' => 'processing',
                    'csv_row' => $rowData['csv_row'] ?? $rowIndex + 1
                ]);

                Log::info('✅ CSV FormSubmission created in MongoDB', [
                    'submission_id' => $formSubmission->_id,
                    'csv_row' => $rowData['csv_row'] ?? $rowIndex + 1,
                    'email' => $validatedData['email'] ?? 'N/A'
                ]);

                // Step 3: Process the actual operation
                $this->processOperation($formSubmission, $validatedData);

                // Step 4: Fire success event
                event(new FormSubmissionCreated(
                    $formSubmission,
                    $validatedData,
                    $rowData['source']
                ));

                $successCount++;

            } catch (ValidationException $e) {
                $errorCount++;
                $this->handleValidationError($e, $rowData, $rowIndex + 1);
            } catch (\Exception $e) {
                $errorCount++;
                Log::error('❌ Unexpected error processing CSV row', [
                    'csv_row' => $rowData['csv_row'] ?? $rowIndex + 1,
                    'email' => $rowData['data']['email'] ?? 'N/A',
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('📊 Batch processing completed', [
            'total_rows' => count($batchData),
            'successful_rows' => $successCount,
            'failed_rows' => $errorCount,
            'csv_file' => $this->submissionData['csv_file'] ?? 'unknown'
        ]);
    }

    /**
     * Validate submission data with duplicate checking
     */
    private function validateSubmissionData(array $data): array
    {
        // Custom validation without automatic duplicate checking
        // We'll handle duplicates manually in this job
        $validatedData = $this->performBasicValidation($data, false);

        // Check for duplicates if this is a create operation
        if ($this->submissionData['operation'] === 'create' && isset($validatedData['email'])) {
            $this->checkForDuplicateEmail($validatedData['email'], $this->submissionData['source']);
        }

        return $validatedData;
    }

    /**
     * Validate CSV data with duplicate checking
     */
    private function validateCsvData(array $data): array
    {
        // Custom validation without automatic duplicate checking
        // We'll handle duplicates manually in this job
        $validatedData = $this->performBasicCsvValidation($data, false);

        // Check for duplicates if this is a create operation
        if (isset($this->submissionData['operation']) && $this->submissionData['operation'] === 'create' && isset($validatedData['email'])) {
            $this->checkForDuplicateEmail($validatedData['email'], 'csv');
        }

        return $validatedData;
    }

    /**
     * Perform basic validation without duplicate checking
     */
    private function performBasicValidation(array $data, bool $checkDuplicates = true): array
    {
        if ($checkDuplicates) {
            // Use the validator's validate method which includes duplicate checking
            return FormSubmissionValidator::validate($data);
        }

        // Perform validation without duplicate checking
        $validator = \Illuminate\Support\Facades\Validator::make($data, FormSubmissionValidator::rules(), [
            'name.required' => 'Student name is required.',
            'email.required' => 'Student email is required.',
            'email.email' => 'Please provide a valid email address.',
            'phone.regex' => 'Phone number must be 10-15 digits, optionally starting with +.',
            'phone.max' => 'Phone number must not exceed 20 characters.',
            'gender.required' => 'Gender selection is required.',
            'gender.in' => 'Gender must be either male or female.',
            'date_of_birth.date' => 'Date of birth must be a valid date.',
            'date_of_birth.date_format' => 'Date of birth must be in YYYY-MM-DD format.',
            'enrollment_date.date' => 'Enrollment date must be a valid date.',
            'enrollment_date.date_format' => 'Enrollment date must be in YYYY-MM-DD format.',
        ]);

        return $validator->validate();
    }

    /**
     * Perform basic CSV validation without duplicate checking
     */
    private function performBasicCsvValidation(array $data, bool $checkDuplicates = true): array
    {
        if ($checkDuplicates) {
            // Use the validator's validateCsv method which includes duplicate checking
            return FormSubmissionValidator::validateCsv($data);
        }

        // Clean the data first
        $cleanedData = [];
        foreach ($data as $key => $value) {
            $cleanedValue = is_string($value) ? trim($value) : $value;
            if ($cleanedValue !== '' && $cleanedValue !== null) {
                $cleanedData[$key] = $cleanedValue;
            }
        }

        // Perform validation without duplicate checking
        $validator = \Illuminate\Support\Facades\Validator::make($cleanedData, FormSubmissionValidator::csvRules(), [
            'name.required' => 'Student name is required.',
            'email.required' => 'Student email is required.',
            'email.email' => 'Please provide a valid email address.',
            'phone.required' => 'Phone number is required for CSV uploads.',
            'phone.regex' => 'Phone number must be 10-15 digits, optionally starting with +.',
            'phone.max' => 'Phone number must not exceed 20 characters.',
            'gender.required' => 'Gender selection is required.',
            'gender.in' => 'Gender must be either male or female.',
            'date_of_birth.required' => 'Date of birth is required for CSV uploads.',
            'course.required' => 'Course is required for CSV uploads.',
            'enrollment_date.required' => 'Enrollment date is required for CSV uploads.',
        ]);

        return $validator->validate();
    }

    /**
     * Check for duplicate email and handle accordingly
     */
    private function checkForDuplicateEmail(string $email, string $source, ?int $csvRow = null): void
    {
        $email = trim(strtolower($email));
        
        Log::debug('🔍 Checking for duplicate email in job', [
            'email' => $email,
            'source' => $source,
            'csv_row' => $csvRow
        ]);

        // Check for existing form submissions with same email
        $existingSubmission = FormSubmission::where('data.email', $email)
            ->where('status', 'completed')
            ->where('operation', 'create')
            ->first();

        if ($existingSubmission) {
            Log::warning('📧 Duplicate email detected in validation job', [
                'email' => $email,
                'source' => $source,
                'csv_row' => $csvRow,
                'existing_submission_id' => $existingSubmission->_id
            ]);

            // Queue asynchronous duplicate processing
            ProcessDuplicateEmailCheck::dispatch(
                $email,
                $source,
                $this->submissionData['data'] ?? [],
                $existingSubmission->_id,
                $csvRow,
                $this->submissionData['operation'] ?? 'create'
            );

            // Throw validation exception to prevent insertion
            throw ValidationException::withMessages([
                'email' => [
                    'This email address is already registered in the system. Existing record ID: ' . substr($existingSubmission->_id, -8)
                ]
            ]);
        }

        Log::debug('✅ No duplicate email found', [
            'email' => $email,
            'source' => $source
        ]);
    }

    /**
     * Process the actual operation (create/update/delete student)
     */
    private function processOperation(FormSubmission $formSubmission, array $validatedData): void
    {
        Log::info('⚙️ Processing operation', [
            'submission_id' => $formSubmission->_id,
            'operation' => $formSubmission->operation,
            'email' => $validatedData['email'] ?? 'N/A'
        ]);

        try {
            switch ($formSubmission->operation) {
                case 'create':
                    $result = $this->handleCreateOperation($formSubmission, $validatedData);
                    break;
                case 'update':
                    $result = $this->handleUpdateOperation($formSubmission, $validatedData);
                    break;
                case 'delete':
                    $result = $this->handleDeleteOperation($formSubmission, $validatedData);
                    break;
                default:
                    throw new \InvalidArgumentException('Invalid operation: ' . $formSubmission->operation);
            }

            // Update status to completed
            $formSubmission->update([
                'status' => 'completed',
                'processed_at' => now(),
                'result' => $result
            ]);

            Log::info('✅ Operation processed successfully', [
                'submission_id' => $formSubmission->_id,
                'operation' => $formSubmission->operation,
                'email' => $validatedData['email'] ?? 'N/A'
            ]);

        } catch (\Exception $e) {
            $formSubmission->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at' => now()
            ]);

            Log::error('❌ Operation processing failed', [
                'submission_id' => $formSubmission->_id,
                'operation' => $formSubmission->operation,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Handle create operation
     */
    private function handleCreateOperation(FormSubmission $formSubmission, array $validatedData): array
    {
        // Implementation for creating a student record
        // This would typically create a Student model record
        Log::info('📝 Handling create operation', [
            'submission_id' => $formSubmission->_id,
            'email' => $validatedData['email'] ?? 'N/A'
        ]);

        return [
            'action' => 'create',
            'student_created' => true,
            'student_data' => $validatedData
        ];
    }

    /**
     * Handle update operation
     */
    private function handleUpdateOperation(FormSubmission $formSubmission, array $validatedData): array
    {
        // Implementation for updating a student record
        Log::info('✏️ Handling update operation', [
            'submission_id' => $formSubmission->_id,
            'student_id' => $formSubmission->student_id,
            'email' => $validatedData['email'] ?? 'N/A'
        ]);

        return [
            'action' => 'update',
            'student_updated' => true,
            'student_id' => $formSubmission->student_id,
            'updated_data' => $validatedData
        ];
    }

    /**
     * Handle delete operation
     */
    private function handleDeleteOperation(FormSubmission $formSubmission, array $validatedData): array
    {
        // Implementation for deleting a student record
        Log::info('🗑️ Handling delete operation', [
            'submission_id' => $formSubmission->_id,
            'student_id' => $formSubmission->student_id
        ]);

        return [
            'action' => 'delete',
            'student_deleted' => true,
            'student_id' => $formSubmission->student_id
        ];
    }

    /**
     * Handle validation errors
     */
    private function handleValidationError(ValidationException $e, array $submissionData, ?int $csvRow = null): void
    {
        $email = $submissionData['data']['email'] ?? 'unknown';
        $source = $submissionData['source'] ?? 'unknown';

        Log::warning('⚠️ Validation failed in job', [
            'email' => $email,
            'source' => $source,
            'csv_row' => $csvRow,
            'errors' => $e->errors()
        ]);

        // Check if this is a duplicate email error
        if (isset($e->errors()['email'])) {
            $emailErrors = $e->errors()['email'];
            foreach ($emailErrors as $emailError) {
                if (str_contains(strtolower($emailError), 'already registered') || 
                    str_contains(strtolower($emailError), 'duplicate')) {
                    
                    Log::info('📧 Duplicate email validation error handled', [
                        'email' => $email,
                        'source' => $source,
                        'csv_row' => $csvRow,
                        'validation_message' => $emailError
                    ]);

                    // Create failed FormSubmission record for tracking
                    FormSubmission::create([
                        'operation' => $submissionData['operation'] ?? 'create',
                        'student_id' => $submissionData['student_id'] ?? null,
                        'data' => $submissionData['data'] ?? [],
                        'source' => $source,
                        'ip_address' => $this->submissionData['ip_address'] ?? null,
                        'user_agent' => $this->submissionData['user_agent'] ?? null,
                        'status' => 'failed',
                        'error_message' => $emailError,
                        'csv_row' => $csvRow,
                        'processed_at' => now()
                    ]);

                    return; // Exit early for duplicate errors
                }
            }
        }

        // Create failed FormSubmission record for other validation errors
        FormSubmission::create([
            'operation' => $submissionData['operation'] ?? 'create',
            'student_id' => $submissionData['student_id'] ?? null,
            'data' => $submissionData['data'] ?? [],
            'source' => $source,
            'ip_address' => $this->submissionData['ip_address'] ?? null,
            'user_agent' => $this->submissionData['user_agent'] ?? null,
            'status' => 'failed',
            'error_message' => $e->getMessage(),
            'csv_row' => $csvRow,
            'processed_at' => now()
        ]);
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('❌ ValidateAndInsertJob failed completely', [
            'job' => 'ValidateAndInsertJob',
            'submission_id' => $this->submissionId,
            'operation' => $this->submissionData['operation'] ?? 'unknown',
            'source' => $this->submissionData['source'] ?? 'unknown',
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Could implement fallback logic here:
        // - Create failed FormSubmission record
        // - Send admin notification
        // - Store data for manual processing
    }
}