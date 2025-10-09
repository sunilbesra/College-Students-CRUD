<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use App\Services\FormSubmissionValidator;
use App\Events\FormSubmissionCreated;
use App\Events\CsvUploadStarted;
use App\Events\CsvUploadCompleted;
use App\Events\DuplicateEmailDetected;
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
        // Basic validation first
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

        // No duplicate validation in controller - let Beanstalk consumer handle all validation

        // Prepare data for validation and insertion job
        $submissionData = [
            'operation' => $request->operation,
            'student_id' => $request->student_id,
            'data' => $formData,
            'source' => $request->source,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'submitted_at' => now()->toDateTimeString()
        ];

        // FIRST: Mirror form submission to Beanstalk tube immediately (before Laravel queue)
        $mirrorJobId = $this->mirrorToBeanstalk('form_submission_created', $submissionData);

        // SECOND: Dispatch to ProcessFormSubmissionData for validation and processing
        ProcessFormSubmissionData::dispatch(null, $submissionData)
            ->onQueue(env('BEANSTALKD_FORM_SUBMISSION_QUEUE', 'form_submission_jobs'));

        // THIRD: Fire FormSubmissionCreated event for immediate notifications
        Log::info('🎯 FIRING EVENT: FormSubmissionCreated', [
            'controller' => 'FormSubmissionController',
            'email' => $formData['email'] ?? 'N/A',
            'operation' => $request->operation,
            'source' => $request->source,
            'mirror_job_id' => $mirrorJobId
        ]);
        
        // Fire event with null FormSubmission (Beanstalk-first architecture - model created later in consumer)
        event(new \App\Events\FormSubmissionCreated(null, $submissionData, $request->source));
        Log::debug('✅ FormSubmissionCreated event fired successfully');

        Log::info('Form submission stored in Beanstalk and sent for processing', [
            'operation' => $request->operation,
            'source' => $request->source,
            'email' => $formData['email'] ?? 'N/A',
            'mirror_job_id' => $mirrorJobId,
            'job' => 'ProcessFormSubmissionData'
        ]);

        return redirect()->route('form_submissions.index')
            ->with('success', 'Form submission stored in Beanstalk for validation and processing.')
            ->with('processing_info', [
                'type' => 'form_submission',
                'email' => $formData['email'] ?? 'N/A',
                'mirror_job_id' => $mirrorJobId,
                'message' => 'Your submission is being validated. Duplicate validation happens in the consumer.'
            ]);
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
        // $request->validate([
        //     'operation' => 'required|in:create,update,delete',
        //     'student_id' => 'nullable|string',
        //     'data' => 'required|array',
        //     'source' => 'required|in:form,api,csv',
        //     'status' => 'required|in:queued,processing,completed,failed',
        //     'profile_image' => 'nullable|image|mimes:jpeg,jpg,png,gif|max:2048', // 2MB max
        //     'remove_current_image' => 'nullable|boolean'
        // ]);

        // Store original data for comparison
        $originalData = $formSubmission->data;

        // Handle profile image upload
        $data = $request->data;
        
        // Handle image removal
        if ($request->has('remove_current_image') && $request->remove_current_image) {
            // Remove the current image file if it exists
            if (isset($formSubmission->data['profile_image_path']) && $formSubmission->data['profile_image_path']) {
                $oldImagePath = public_path($formSubmission->data['profile_image_path']);
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                    Log::info('Profile image removed', [
                        'submission_id' => $formSubmission->_id,
                        'removed_path' => $formSubmission->data['profile_image_path']
                    ]);
                }
            }
            // Remove from data array
            unset($data['profile_image_path']);
        }
        
        // Handle new image upload
        if ($request->hasFile('profile_image')) {
            // Remove old image if exists
            if (isset($formSubmission->data['profile_image_path']) && $formSubmission->data['profile_image_path']) {
                $oldImagePath = public_path($formSubmission->data['profile_image_path']);
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }
            
            // Store new image
            $image = $request->file('profile_image');
            
            // Get file info before moving (to avoid the temp file issue)
            $originalName = $image->getClientOriginalName();
            $fileSize = $image->getSize();
            
            $imageName = time() . '_' . $originalName;
            $imagePath = 'uploads/profile-images/' . $imageName;
            
            // Create directory if it doesn't exist
            $uploadDir = public_path('uploads/profile-images');
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Move the uploaded file
            $image->move($uploadDir, $imageName);
            
            // Update data array with new image path
            $data['profile_image_path'] = $imagePath;
            
            Log::info('Profile image uploaded', [
                'submission_id' => $formSubmission->_id,
                'new_path' => $imagePath,
                'original_name' => $originalName,
                'size' => $fileSize
            ]);
        }

        // Prepare updated data
        $submissionData = [
            'operation' => $request->operation,
            'student_id' => $request->student_id,
            'data' => $data,
            'source' => $request->source,
            'status' => $request->status,
            'error_message' => $request->error_message
        ];

        // Update form submission record
        $formSubmission->update($submissionData);

        // Fire FormSubmissionUpdated event
        Log::info('🔄 FIRING EVENT: FormSubmissionUpdated', [
            'controller' => 'FormSubmissionController',
            'submission_id' => $formSubmission->_id,
            'operation' => $request->operation,
            'source' => $request->source,
            'email' => $data['email'] ?? 'N/A'
        ]);
        event(new \App\Events\FormSubmissionUpdated(
            $formSubmission,
            $originalData,
            $data,
            $request->source
        ));
        Log::debug('✅ FormSubmissionUpdated event fired successfully');

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

        $successMessage = 'Form submission updated successfully!';
        if ($request->hasFile('profile_image')) {
            $successMessage .= ' Profile image has been uploaded.';
        } elseif ($request->has('remove_current_image') && $request->remove_current_image) {
            $successMessage .= ' Profile image has been removed.';
        }

        return redirect()->route('form_submissions.index')
            ->with('success', $successMessage);
    }

    /**
     * Remove the specified form submission from storage.
     */
    public function destroy(FormSubmission $formSubmission)
    {
        $submissionId = $formSubmission->_id;
        $submissionData = $formSubmission->data;
        $operation = $formSubmission->operation;
        $studentId = $formSubmission->student_id;
        $source = $formSubmission->source ?? 'form';
        
        // Mirror deletion to Beanstalk
        $this->mirrorToBeanstalk('form_submission_deleted', [
            'id' => $submissionId,
            'operation' => $operation,
            'student_id' => $studentId
        ], $submissionId);

        // Fire FormSubmissionDeleted event before deletion
        Log::info('🗑️ FIRING EVENT: FormSubmissionDeleted', [
            'controller' => 'FormSubmissionController',
            'submission_id' => $submissionId,
            'operation' => $operation,
            'source' => $source,
            'email' => $submissionData['email'] ?? 'N/A'
        ]);
        event(new \App\Events\FormSubmissionDeleted(
            $submissionId,
            $submissionData,
            $operation,
            $studentId,
            $source
        ));
        Log::debug('✅ FormSubmissionDeleted event fired successfully');

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

        // Read CSV data
        $handle = fopen($fullPath, 'r');
        $headers = fgetcsv($handle); // Get headers
        $rowCount = 0;

        if (!$headers) {
            return redirect()->route('form_submissions.index')
                ->with('error', 'Invalid CSV file: No headers found.');
        }

        Log::info('Processing CSV file for form submissions', [
            'operation' => $operation,
            'headers' => $headers,
            'file_path' => $fullPath
        ]);

        // Start timing for performance tracking
        $startTime = microtime(true);

        // Read all CSV data first
        $csvData = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rowCount++;
            
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }
            
            if (count($row) !== count($headers)) {
                Log::warning("Row {$rowCount} has different column count, skipping", [
                    'expected' => count($headers),
                    'actual' => count($row),
                    'row_data' => $row
                ]);
                continue;
            }

            // Combine headers with row data and clean empty keys
            $rowData = array_combine($headers, $row);
            // Remove any empty keys and trim values
            $cleanedRowData = [];
            foreach ($rowData as $key => $value) {
                $cleanedKey = trim($key);
                $cleanedValue = is_string($value) ? trim($value) : $value;
                if (!empty($cleanedKey)) {
                    $cleanedRowData[$cleanedKey] = $cleanedValue;
                }
            }
            $csvData[] = $cleanedRowData;
        }

        // Fire CSV upload started event
        Log::info('📤 FIRING EVENT: CsvUploadStarted', [
            'controller' => 'FormSubmissionController',
            'file_name' => $file->getClientOriginalName(),
            'operation' => $operation,
            'total_rows' => count($csvData),
            'ip_address' => $request->ip()
        ]);
        event(new CsvUploadStarted(
            $file->getClientOriginalName(),
            $operation,
            count($csvData),
            $request->ip(),
            $request->userAgent()
        ));
        Log::debug('✅ CsvUploadStarted event fired successfully');

        fclose($handle);

        // Clean up the uploaded file
        unlink($fullPath);

        // IMMEDIATE duplicate validation for frontend display (before Beanstalk processing)
        $batchData = [];
        $immediateResults = [
            'duplicates' => [],
            'valid' => [],
            'total_processed' => 0
        ];
        
        foreach ($csvData as $rowIndex => $rowData) {
            $immediateResults['total_processed']++;
            
            // Check for duplicate email immediately for frontend display
            if (isset($rowData['email']) && !empty($rowData['email'])) {
                $validator = new \App\Services\FormSubmissionValidator();
                
                try {
                    // Check if email already exists
                    $existingSubmission = \App\Models\FormSubmission::where('data.email', $rowData['email'])->first();
                    
                    if ($existingSubmission) {
                        // Found duplicate - add to immediate results
                        $immediateResults['duplicates'][] = [
                            'email' => $rowData['email'],
                            'row' => $rowIndex + 1,
                            'name' => $rowData['name'] ?? 'Unknown',
                            'existing_id' => $existingSubmission->_id,
                            'message' => "Email '{$rowData['email']}' already exists in the database"
                        ];
                        
                        Log::info('Immediate duplicate detected in CSV', [
                            'email' => $rowData['email'],
                            'csv_row' => $rowIndex + 1,
                            'existing_submission_id' => $existingSubmission->_id
                        ]);
                        
                        // Fire DuplicateEmailDetected event for immediate notifications
                        Log::info('🎯 FIRING EVENT: DuplicateEmailDetected (immediate CSV)', [
                            'email' => $rowData['email'],
                            'csv_row' => $rowIndex + 1,
                            'source' => 'csv',
                            'existing_submission_id' => $existingSubmission->_id,
                            'duplicate_detection' => 'immediate'
                        ]);
                        
                        event(new \App\Events\DuplicateEmailDetected(
                            $rowData['email'],
                            'csv',
                            $existingSubmission->_id,
                            [
                                'csv_row' => $rowIndex + 1,
                                'name' => $rowData['name'] ?? 'Unknown',
                                'csv_file' => $file->getClientOriginalName(),
                                'detection_method' => 'immediate_controller',
                                'duplicate_count' => 1
                            ]
                        ));
                        Log::debug('✅ DuplicateEmailDetected (immediate CSV) event fired successfully');
                    } else {
                        $immediateResults['valid'][] = $rowData['email'];
                    }
                } catch (\Exception $e) {
                    Log::error('Error checking for immediate duplicates', [
                        'email' => $rowData['email'],
                        'error' => $e->getMessage(),
                        'csv_row' => $rowIndex + 1
                    ]);
                }
            }
            
            // Add all rows to batch data for Beanstalk processing (consistent behavior)
            $batchData[] = [
                'operation' => $operation,
                'student_id' => $rowData['student_id'] ?? null,
                'data' => $rowData,
                'source' => 'csv',
                'csv_row' => $rowIndex + 1
            ];
        }

        // Prepare batch submission data for Beanstalk
        $batchSubmissionData = [
            'operation' => $operation,
            'source' => 'csv',
            'batch_data' => $batchData, // Consumer expects this key
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'submitted_at' => now()->toDateTimeString(),
            'csv_file' => $file->getClientOriginalName(),
            'total_rows' => count($batchData)
        ];

        // FIRST: Mirror CSV batch to Beanstalk tube immediately (before Laravel queue)
        $mirrorJobId = null;
        if (count($batchData) > 0) {
            $mirrorJobId = $this->mirrorToBeanstalk('csv_batch_uploaded', $batchSubmissionData);
        }

        // SECOND: Dispatch to ProcessFormSubmissionData for validation and processing
        if (count($batchData) > 0) {
            ProcessFormSubmissionData::dispatch(null, $batchSubmissionData)
                ->onQueue(env('BEANSTALKD_FORM_SUBMISSION_QUEUE', 'form_submission_jobs'));
        }

        Log::info('CSV batch stored in Beanstalk and sent for processing', [
            'total_rows' => count($csvData),
            'batch_rows' => count($batchData),
            'operation' => $operation,
            'csv_file' => $file->getClientOriginalName(),
            'mirror_job_id' => $mirrorJobId,
            'job' => 'ProcessFormSubmissionData'
        ]);

        // Success message with immediate duplicate detection results
        if (count($csvData) === 0) {
            $message = "No data found in CSV file.";
            $alertType = 'error';
        } else {
            $duplicateCount = count($immediateResults['duplicates']);
            $validCount = count($immediateResults['valid']);
            
            if ($duplicateCount > 0) {
                $message = "CSV uploaded! Found {$duplicateCount} duplicate email(s) and {$validCount} valid email(s). Total: " . count($batchData) . " rows processed.";
                $alertType = 'warning';
            } else {
                $message = "CSV uploaded successfully! All {$validCount} email(s) are unique. Total: " . count($batchData) . " rows processed.";
                $alertType = 'success';
            }
            
            // Add immediate results and processing info for frontend display
            session()->flash('processing_info', [
                'type' => 'csv_upload',
                'csv_file' => $file->getClientOriginalName(),
                'total_rows' => count($batchData),
                'mirror_job_id' => $mirrorJobId,
                'immediate_results' => $immediateResults,
                'message' => 'CSV processed with immediate duplicate checking. Data also sent to Beanstalk for consistent processing.'
            ]);
            
            // Flash immediate duplicate results for frontend display
            if ($duplicateCount > 0) {
                session()->flash('immediate_duplicates', $immediateResults['duplicates']);
            }
        }

        // Calculate processing time
        $processingTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        
        // Fire CSV upload completed event
        Log::info('📥 FIRING EVENT: CsvUploadCompleted', [
            'controller' => 'FormSubmissionController',
            'file_name' => $file->getClientOriginalName(),
            'operation' => $operation,
            'processing_time_ms' => (int) $processingTime,
            'total_rows' => count($csvData),
            'batch_job_id' => count($batchData) > 0 ? 'batch_job_dispatched' : null,
            'mirror_job_id' => $mirrorJobId
        ]);
        event(new CsvUploadCompleted(
            $file->getClientOriginalName(),
            $operation,
            [
                'total_rows' => count($csvData), 
                'valid_rows' => count($immediateResults['valid']),
                'invalid_rows' => 0, // Will be calculated in consumer
                'duplicate_rows' => count($immediateResults['duplicates']),
                'immediate_duplicates' => count($immediateResults['duplicates'])
            ],
            (int) $processingTime,
            count($batchData) > 0 ? 'batch_job_dispatched' : null
        ));
        Log::debug('✅ CsvUploadCompleted event fired successfully');

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
                'mirror_id' => uniqid('mirror_', true), // Unique identifier for tracking
            ], JSON_UNESCAPED_UNICODE);
            
            $job = $pheanstalk->useTube($mirrorTube)->put($payload);
            
            Log::info('✅ Form submission successfully mirrored to Beanstalk', [
                'action' => $action,
                'tube' => $mirrorTube,
                'submission_id' => $submissionId,
                'job_id' => $job->getId(),
                'payload_size' => strlen($payload) . ' bytes',
                'beanstalk_host' => $pheanstalkHost . ':' . $pheanstalkPort
            ]);
            
            return $job->getId(); // Return job ID for tracking
            
        } catch (\Pheanstalk\Exception\ConnectionException $e) {
            Log::error("❌ Beanstalkd connection failed for mirror ({$action})", [
                'error' => $e->getMessage(),
                'host' => $pheanstalkHost ?? 'unknown',
                'port' => $pheanstalkPort ?? 'unknown',
                'submission_id' => $submissionId
            ]);
        } catch (\Pheanstalk\Exception\ServerException $e) {
            Log::error("❌ Beanstalkd server error for mirror ({$action})", [
                'error' => $e->getMessage(),
                'tube' => $mirrorTube ?? 'unknown',
                'submission_id' => $submissionId
            ]);
        } catch (\Throwable $e) {
            Log::warning("❌ Failed to write form submission mirror to beanstalk ({$action})", [
                'error' => $e->getMessage(),
                'submission_id' => $submissionId,
                'error_type' => get_class($e)
            ]);
        }
        
        return null;
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
     * Get latest submission ID for polling
     */
    public function getLatest()
    {
        $latest = FormSubmission::orderBy('created_at', 'desc')->first();
        
        return response()->json([
            'latest_id' => $latest ? substr($latest->_id, -8) : null,
            'total_count' => FormSubmission::count(),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Check if email is duplicate (AJAX endpoint)
     */
    public function checkDuplicateEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $email = trim(strtolower($request->email));
        
        // Check for existing form submissions with same email
        $existingSubmission = FormSubmission::where('data.email', $email)
            ->where('status', 'completed')
            ->where('operation', 'create')
            ->first();

        if ($existingSubmission) {
            return response()->json([
                'is_duplicate' => true,
                'existing_id' => substr($existingSubmission->_id, -8),
                'existing_submission_id' => $existingSubmission->_id,
                'message' => 'This email address is already registered in the system.'
            ]);
        }

        return response()->json([
            'is_duplicate' => false,
            'message' => 'Email is available.'
        ]);
    }

    /**
     * Get recent duplicate notifications (AJAX endpoint for frontend)
     */
    public function getRecentDuplicateNotifications(Request $request)
    {
        try {
            // Get recent duplicate notifications from the last 10 minutes
            $recentNotifications = \App\Models\Notification::where('title', 'like', '%Duplicate Email%')
                ->where('created_at', '>=', now()->subMinutes(10))
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            $duplicateNotifications = [];
            
            foreach ($recentNotifications as $notification) {
                $data = $notification->data ?? [];
                
                $duplicateNotifications[] = [
                    'id' => $notification->_id,
                    'email' => $data['email'] ?? 'N/A',
                    'source' => $data['source'] ?? 'unknown',
                    'csv_row' => $data['csv_row'] ?? null,
                    'existing_submission_id' => substr($data['existing_submission_id'] ?? '', -8),
                    'duplicate_count' => $data['duplicate_count'] ?? 1,
                    'detected_at' => $notification->created_at->diffForHumans(),
                    'message' => $this->buildDuplicateMessage($data),
                    'created_at' => $notification->created_at->toDateTimeString()
                ];
            }

            return response()->json([
                'success' => true,
                'notifications' => $duplicateNotifications,
                'count' => count($duplicateNotifications),
                'last_check' => now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch duplicate notifications: ' . $e->getMessage(),
                'notifications' => [],
                'count' => 0
            ]);
        }
    }

    /**
     * Build duplicate message based on notification data
     */
    private function buildDuplicateMessage(array $data): string
    {
        $email = $data['email'] ?? 'Unknown email';
        $source = $data['source'] ?? 'unknown';
        $csvRow = $data['csv_row'] ?? null;
        $existingId = substr($data['existing_submission_id'] ?? '', -8);

        if ($source === 'csv' && $csvRow) {
            return "Duplicate email '{$email}' detected in CSV row {$csvRow}. Already exists as submission #{$existingId}.";
        } elseif ($source === 'form') {
            return "Duplicate email '{$email}' detected in form submission. Already exists as submission #{$existingId}.";
        } else {
            return "Duplicate email '{$email}' detected via {$source}. Already exists as submission #{$existingId}.";
        }
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

    /**
     * Clear all duplicate notifications (AJAX endpoint)
     */
    public function clearDuplicateNotifications(Request $request)
    {
        try {
            // Delete all duplicate email notifications
            $deletedCount = \App\Models\Notification::where('title', 'like', '%Duplicate Email%')->delete();

            Log::info("Cleared {$deletedCount} duplicate notifications");

            return response()->json([
                'success' => true,
                'message' => 'All duplicate notifications cleared successfully',
                'cleared_count' => $deletedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Error clearing duplicate notifications: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to clear duplicate notifications: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all recent notifications for both forms and CSV uploads (AJAX endpoint for frontend)
     */
    public function getAllRecentNotifications(Request $request)
    {
        try {
            // Get recent notifications from the last 30 minutes
            $recentNotifications = \App\Models\Notification::whereIn('type', [
                    \App\Models\Notification::TYPE_FORM_SUBMISSION,
                    \App\Models\Notification::TYPE_CSV_UPLOAD,
                    \App\Models\Notification::TYPE_DUPLICATE_EMAIL
                ])
                ->where('created_at', '>=', now()->subMinutes(30))
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            $notifications = [];
            
            foreach ($recentNotifications as $notification) {
                $data = $notification->data ?? [];
                
                $notifications[] = [
                    'id' => $notification->_id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'icon' => $notification->icon ?? 'fas fa-info-circle',
                    'color' => $notification->color ?? 'blue',
                    'action_url' => $notification->action_url,
                    'action_text' => $notification->action_text,
                    'created_at' => $notification->created_at->toDateTimeString(),
                    'time_ago' => $notification->created_at->diffForHumans(),
                    'is_read' => $notification->read_at !== null,
                    'data' => $data
                ];
            }

            return response()->json([
                'success' => true,
                'notifications' => $notifications,
                'count' => count($notifications),
                'types' => [
                    'form_submission' => $notifications ? count(array_filter($notifications, fn($n) => $n['type'] === \App\Models\Notification::TYPE_FORM_SUBMISSION)) : 0,
                    'csv_upload' => $notifications ? count(array_filter($notifications, fn($n) => $n['type'] === \App\Models\Notification::TYPE_CSV_UPLOAD)) : 0,
                    'duplicate_email' => $notifications ? count(array_filter($notifications, fn($n) => $n['type'] === \App\Models\Notification::TYPE_DUPLICATE_EMAIL)) : 0,
                ],
                'last_check' => now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch notifications: ' . $e->getMessage(),
                'notifications' => [],
                'count' => 0
            ]);
        }
    }

    /**
     * Clear all recent notifications (AJAX endpoint for frontend)
     */
    public function clearAllRecentNotifications(Request $request)
    {
        try {
            // Clear all recent notifications from the last hour
            $deletedCount = \App\Models\Notification::whereIn('type', [
                    \App\Models\Notification::TYPE_FORM_SUBMISSION,
                    \App\Models\Notification::TYPE_CSV_UPLOAD,
                    \App\Models\Notification::TYPE_DUPLICATE_EMAIL
                ])
                ->where('created_at', '>=', now()->subHour())
                ->delete();

            return response()->json([
                'success' => true,
                'message' => "Cleared {$deletedCount} notification(s)",
                'deleted_count' => $deletedCount,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to clear notifications: ' . $e->getMessage(),
                'deleted_count' => 0,
                'timestamp' => now()->toISOString()
            ]);
        }
    }
}