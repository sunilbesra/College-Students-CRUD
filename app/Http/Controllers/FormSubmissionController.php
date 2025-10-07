<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use App\Services\FormSubmissionValidator;
use Illuminate\Http\Request;
use App\Jobs\ProcessFormSubmissionData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Pheanstalk\Pheanstalk;

class FormSubmissionController extends Controller
{
   
    public function index(Request $request)
    {
        // Sanitize and normalize inputs
        $q = (string) $request->query('q', '');
        $q = trim($q);
        
        $status = $request->query('status', '');
        $operation = $request->query('operation', '');
        $source = $request->query('source', '');

        // Per-page control
        $allowed = [5, 10, 25, 50];
        $perPage = (int) $request->query('per_page', 10);
        if (!in_array($perPage, $allowed, true)) {
            $perPage = 10;
        }

        // Build query
        $query = FormSubmission::query();

        // Text search in data field
        if (!empty($q)) {
            $query->where(function($subQuery) use ($q) {
                $subQuery->where('data', 'like', "%{$q}%")
                        ->orWhere('error_message', 'like', "%{$q}%")
                        ->orWhere('student_id', 'like', "%{$q}%");
            });
        }

        // Filter by status
        if (!empty($status)) {
            $query->status($status);
        }

        // Filter by operation
        if (!empty($operation)) {
            $query->operation($operation);
        }

        // Filter by source
        if (!empty($source)) {
            $query->where('source', $source);
        }

        $query->orderBy('created_at', 'desc');

        // Simple caching for production
        if (app()->environment('production')) {
            $cacheKey = 'form_submissions:' . md5(serialize([
                'q' => $q, 
                'status' => $status,
                'operation' => $operation,
                'source' => $source,
                'page' => $request->query('page', 1), 
                'per' => $perPage
            ]));
            $submissions = Cache::remember($cacheKey, now()->addMinutes(2), function () use ($query, $perPage) {
                return $query->paginate($perPage);
            });
        } else {
            $submissions = $query->paginate($perPage);
        }

        $submissions->appends([
            'q' => $q, 
            'status' => $status,
            'operation' => $operation,
            'source' => $source,
            'per_page' => $perPage
        ]);

        return view('form_submissions.index', compact('submissions', 'q', 'status', 'operation', 'source', 'perPage'));
    }

    /**
     * Show the form for creating a new form submission.
     */
    public function create()
    {
        return view('form_submissions.create');
    }

    /**
     * Store a newly created form submission in storage.
     */
    public function store(Request $request)
    {
        // Basic validation
        $request->validate([
            'operation' => 'required|in:create,update,delete',
            'student_id' => 'nullable|string',
            'data' => 'required|array',
            'source' => 'required|in:form,api,csv',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        // Handle profile image upload
        $profileImagePath = null;
        if ($request->hasFile('profile_image')) {
            $profileImagePath = $this->handleImageUpload($request->file('profile_image'));
        }

        // Add profile image path to data if uploaded
        $formData = $request->data;
        if ($profileImagePath) {
            $formData['profile_image_path'] = $profileImagePath;
        }

        // Validate form data including duplicate email check
        try {
            $validatedData = FormSubmissionValidator::validate($formData, $request->student_id);
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', 'Please correct the validation errors and try again.');
        }

        // Prepare data for direct Beanstalk processing
        $submissionData = [
            'operation' => $request->operation,
            'student_id' => $request->student_id,
            'data' => $validatedData,
            'source' => $request->source,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'submitted_at' => now()->toDateTimeString()
        ];

        // Send directly to Beanstalk for processing (unified architecture)
        ProcessFormSubmissionData::dispatch(null, $submissionData)
            ->onQueue(env('BEANSTALKD_FORM_SUBMISSION_QUEUE', 'form_submission_jobs'));

        Log::info('Form submission sent to Beanstalk for processing', [
            'operation' => $request->operation,
            'source' => $request->source,
            'queue' => env('BEANSTALKD_FORM_SUBMISSION_QUEUE', 'form_submission_jobs')
        ]);

        return redirect()->route('form_submissions.index')
            ->with('success', 'Form submission sent for processing. The consumer will validate and store the data!');
    }

    /**
     * Display the specified form submission.
     */
    public function show(FormSubmission $formSubmission)
    {
        return view('form_submissions.show', compact('formSubmission'));
    }

    /**
     * Show the form for editing the specified form submission.
     */
    public function edit(FormSubmission $formSubmission)
    {
        return view('form_submissions.edit', compact('formSubmission'));
    }

    /**
     * Update the specified form submission in storage.
     */
    public function update(Request $request, FormSubmission $formSubmission)
    {
        // Basic validation
        $request->validate([
            'operation' => 'required|in:create,update,delete',
            'student_id' => 'nullable|string',
            'data' => 'required|array',
            'source' => 'required|in:form,api,csv',
            'status' => 'required|in:queued,processing,completed,failed'
        ]);

        // Prepare updated data
        $submissionData = [
            'operation' => $request->operation,
            'student_id' => $request->student_id,
            'data' => $request->data,
            'source' => $request->source,
            'status' => $request->status,
            'error_message' => $request->error_message
        ];

        // Update form submission record
        $formSubmission->update($submissionData);

        // If status changed to queued, reprocess
        if ($request->status === 'queued') {
            ProcessFormSubmissionData::dispatch($formSubmission->_id, $submissionData);
            
            // Mirror to Beanstalk
            $this->mirrorToBeanstalk('form_submission_requeued', $submissionData, $formSubmission->_id);
            
            Log::info('Form submission requeued for processing', [
                'submission_id' => $formSubmission->_id,
                'operation' => $request->operation
            ]);
        }

        return redirect()->route('form_submissions.index')
            ->with('success', 'Form submission updated successfully!');
    }

    /**
     * Remove the specified form submission from storage.
     */
    public function destroy(FormSubmission $formSubmission)
    {
        $submissionId = $formSubmission->_id;
        
        // Mirror deletion to Beanstalk
        $this->mirrorToBeanstalk('form_submission_deleted', [
            'id' => $submissionId,
            'operation' => $formSubmission->operation,
            'student_id' => $formSubmission->student_id
        ], $submissionId);

        $formSubmission->delete();

        Log::info('Form submission deleted', [
            'submission_id' => $submissionId
        ]);

        return redirect()->route('form_submissions.index')
            ->with('success', 'Form submission deleted successfully!');
    }

    /**
     * Upload and process CSV file for form submissions
     */
    public function uploadCsv()
    {
        return view('form_submissions.upload_csv');
    }

    /**
     * Process uploaded CSV file
     */
    public function processCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
            'operation' => 'required|in:create,update,delete',
        ]);

        $file = $request->file('csv_file');
        $operation = $request->operation;

        // Store the uploaded file
        $filePath = $file->store('csv_uploads', 'local');
        $fullPath = storage_path('app/' . $filePath);

        // Read CSV and create form submissions
        $handle = fopen($fullPath, 'r');
        $headers = fgetcsv($handle); // Get headers
        $rowCount = 0;
        $processedCount = 0;
        $errors = [];
        $duplicateEmails = [];
        $csvEmails = []; // Track emails within this CSV file
        $validRows = []; // Collect valid rows for batch processing

        if (!$headers) {
            return redirect()->route('form_submissions.index')
                ->with('error', 'Invalid CSV file: No headers found.');
        }

        Log::info('Processing CSV file for form submissions', [
            'operation' => $operation,
            'headers' => $headers,
            'file_path' => $fullPath
        ]);

        // Read all CSV data first
        $csvData = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rowCount++;
            
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }
            
            if (count($row) !== count($headers)) {
                $error = "Row {$rowCount} has different column count, skipping";
                $errors[] = $error;
                Log::warning($error, [
                    'expected' => count($headers),
                    'actual' => count($row),
                    'row_data' => $row
                ]);
                continue;
            }

            // Combine headers with row data
            $rowData = array_combine($headers, $row);
            $csvData[] = $rowData;
        }

        // Use FormSubmissionValidator for batch validation
        $validationResults = FormSubmissionValidator::validateCsvBatch($csvData);
        $summary = FormSubmissionValidator::getCsvValidationSummary($validationResults);

        // Process valid rows
        foreach ($validationResults['valid'] as $validRow) {
            $validRows[] = [
                'operation' => $operation,
                'student_id' => $validRow['data']['student_id'] ?? null,
                'data' => $validRow['data'],
                'source' => 'csv',
                'csv_row' => $validRow['row']
            ];
        }

        // Collect errors from validation
        foreach ($validationResults['invalid'] as $invalidRow) {
            $errorMessages = [];
            foreach ($invalidRow['errors'] as $field => $fieldErrors) {
                $errorMessages[] = "$field: " . implode(', ', $fieldErrors);
            }
            $errors[] = "Row {$invalidRow['row']}: " . implode(' | ', $errorMessages);
        }

        // Collect duplicate errors
        foreach ($validationResults['duplicates'] as $duplicate) {
            $errors[] = "Row {$duplicate['row']}: {$duplicate['error']}";
            $duplicateEmails[] = $duplicate['email'];
        }

        $processedCount = count($validRows);
        
        Log::info('CSV validation completed', [
            'csv_file' => $file->getClientOriginalName(),
            'total_rows' => $summary['total_rows'],
            'valid_rows' => $summary['valid_rows'],
            'invalid_rows' => $summary['invalid_rows'],
            'duplicate_rows' => $summary['duplicate_rows']
        ]);

        fclose($handle);

        // Clean up the uploaded file
        unlink($fullPath);

        // Dispatch single batch job for all valid rows
        if (!empty($validRows)) {
            $batchSubmissionData = [
                'operation' => $operation,
                'source' => 'csv',
                'batch_data' => $validRows,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'submitted_at' => now()->toDateTimeString(),
                'csv_file' => $file->getClientOriginalName(),
                'total_rows' => count($validRows)
            ];

            ProcessFormSubmissionData::dispatch(null, $batchSubmissionData)
                ->onQueue(env('BEANSTALKD_FORM_SUBMISSION_QUEUE', 'form_submission_jobs'));

            Log::info('CSV batch job dispatched to Beanstalk', [
                'total_rows' => count($validRows),
                'operation' => $operation,
                'csv_file' => $file->getClientOriginalName()
            ]);
        }

        Log::info('CSV file processed for form submissions', [
            'total_rows' => $summary['total_rows'],
            'valid_rows' => $summary['valid_rows'],
            'invalid_rows' => $summary['invalid_rows'],
            'duplicate_rows' => $summary['duplicate_rows'],
            'processed_rows' => $processedCount,
            'operation' => $operation
        ]);

        // Build detailed success/warning message
        $message = "CSV processed! {$summary['valid_rows']} form submissions queued for processing.";
        $alertType = 'success';
        
        if ($summary['duplicate_rows'] > 0) {
            $message .= " {$summary['duplicate_rows']} duplicate emails were skipped.";
            $alertType = 'warning';
        }
        
        if ($summary['invalid_rows'] > 0) {
            $message .= " {$summary['invalid_rows']} validation errors occurred.";
            $alertType = 'warning';
        }

        if ($summary['total_rows'] === 0) {
            $message = "No valid data found in CSV file.";
            $alertType = 'error';
        }

        // Store detailed errors in session for display
        if (!empty($errors)) {
            session()->flash('csv_errors', $errors);
        }
        if (!empty($duplicateEmails)) {
            session()->flash('duplicate_emails', array_unique($duplicateEmails));
        }

        return redirect()->route('form_submissions.index')
            ->with($alertType, $message);
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
                'source' => 'form_submission_controller',
            ], JSON_UNESCAPED_UNICODE);
            
            $pheanstalk->useTube($mirrorTube)->put($payload);
            
            Log::debug('Form submission mirrored to Beanstalk', [
                'action' => $action,
                'tube' => $mirrorTube,
                'submission_id' => $submissionId
            ]);
            
        } catch (\Throwable $e) {
            Log::warning("Failed to write form submission mirror to beanstalk ({$action}): " . $e->getMessage());
        }
    }

    /**
     * Get form submission statistics
     */
    public function stats()
    {
        $stats = [
            'total' => FormSubmission::count(),
            'queued' => FormSubmission::status('queued')->count(),
            'processing' => FormSubmission::status('processing')->count(),
            'completed' => FormSubmission::status('completed')->count(),
            'failed' => FormSubmission::status('failed')->count(),
            'by_operation' => [
                'create' => FormSubmission::operation('create')->count(),
                'update' => FormSubmission::operation('update')->count(),
                'delete' => FormSubmission::operation('delete')->count(),
            ],
            'by_source' => [
                'form' => FormSubmission::where('source', 'form')->count(),
                'api' => FormSubmission::where('source', 'api')->count(),
                'csv' => FormSubmission::where('source', 'csv')->count(),
            ]
        ];

        return response()->json($stats);
    }

    /**
     * Handle profile image upload
     */
    private function handleImageUpload($file): string
    {
        try {
            // Create uploads directory if it doesn't exist
            $uploadsPath = public_path('uploads/profiles');
            if (!file_exists($uploadsPath)) {
                mkdir($uploadsPath, 0755, true);
            }

            // Generate unique filename
            $timestamp = now()->format('Y-m-d_H-i-s');
            $randomString = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
            $extension = $file->getClientOriginalExtension();
            $filename = "profile_{$timestamp}_{$randomString}.{$extension}";

            // Store the file
            $file->move($uploadsPath, $filename);

            // Return relative path for storage
            return "uploads/profiles/{$filename}";

        } catch (\Exception $e) {
            Log::error('Profile image upload failed', [
                'error' => $e->getMessage(),
                'file_name' => $file->getClientOriginalName()
            ]);
            throw new \Exception('Failed to upload profile image: ' . $e->getMessage());
        }
    }
}