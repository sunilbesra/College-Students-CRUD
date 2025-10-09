<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\CsvController;
use App\Http\Controllers\FormSubmissionController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Test route to create a sample notification
Route::get('/test-notification', function () {
    \App\Models\Notification::create([
        'type' => 'test',
        'title' => 'Test Notification',
        'message' => 'This is a test notification created from the test route.',
        'importance' => 'medium',
        'is_read' => false,
        'data' => json_encode(['source' => 'test_route'])
    ]);
    
    return response()->json(['message' => 'Test notification created successfully']);
});

// Test route to create a form submission and trigger notifications
Route::get('/test-form-submission', function () {
    // Create a form submission
    $formSubmission = \App\Models\FormSubmission::create([
        'operation' => 'create',
        'data' => [
            'name' => 'Test Student',
            'email' => 'test@example.com',
            'contact' => '123-456-7890',
            'gender' => 'Male'
        ],
        'status' => 'queued',
        'source' => 'form',
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent()
    ]);

    // Fire the FormSubmissionCreated event
    \Illuminate\Support\Facades\Log::info('Firing FormSubmissionCreated event');
    event(new \App\Events\FormSubmissionCreated(
        $formSubmission,
        $formSubmission->data,
        'form'
    ));

    // Simulate processing by updating status and firing processed event
    $formSubmission->update([
        'status' => 'completed',
        'processed_at' => now()
    ]);

    \Illuminate\Support\Facades\Log::info('Firing FormSubmissionProcessed event');
    event(new \App\Events\FormSubmissionProcessed(
        $formSubmission,
        'completed',
        null
    ));

    return response()->json([
        'message' => 'Form submission created and processed successfully',
        'submission_id' => $formSubmission->_id
    ]);
});

// Test route to simulate duplicate email detection
Route::get('/test-duplicate-email', function () {
    // Fire the DuplicateEmailDetected event
    \Illuminate\Support\Facades\Log::info('Firing DuplicateEmailDetected event');
    event(new \App\Events\DuplicateEmailDetected(
        'duplicate@example.com',
        'form',
        '68e61f8172e42f0dba0cc767', // existing submission ID
        [
            'name' => 'Duplicate User',
            'email' => 'duplicate@example.com',
            'contact' => '987-654-3210'
        ],
        null // csvRow
    ));

    return response()->json([
        'message' => 'Duplicate email event fired successfully'
    ]);
});

// Test route to simulate CSV upload events
Route::get('/test-csv-upload', function () {
    // Fire the CsvUploadStarted event
    \Illuminate\Support\Facades\Log::info('Firing CsvUploadStarted event');
    event(new \App\Events\CsvUploadStarted(
        'test_students.csv',
        'create',
        100,
        request()->ip(),
        request()->userAgent()
    ));

    // Simulate processing completion
    sleep(1); // Small delay to simulate processing
    
    \Illuminate\Support\Facades\Log::info('Firing CsvUploadCompleted event');
    event(new \App\Events\CsvUploadCompleted(
        'test_students.csv',
        'create',
        [
            'total_rows' => 100,
            'valid_rows' => 95,
            'invalid_rows' => 5,
            'created' => 95,
            'updated' => 0,
            'skipped' => 5
        ],
        2500, // processing time in ms
        'batch_123'
    ));

    return response()->json([
        'message' => 'CSV upload events fired successfully'
    ]);
});

// Test route to simulate form submission update
Route::get('/test-form-update', function () {
    // Create a form submission first
    $formSubmission = \App\Models\FormSubmission::create([
        'operation' => 'update',
        'data' => [
            'name' => 'Updated Student',
            'email' => 'updated@example.com',
            'contact' => '555-123-4567',
            'gender' => 'Female'
        ],
        'status' => 'completed',
        'source' => 'form',
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent()
    ]);

    // Simulate update with different data
    $originalData = $formSubmission->data;
    $updatedData = [
        'name' => 'Updated Student Name',
        'email' => 'updated@example.com', // same
        'contact' => '555-999-8888', // changed
        'gender' => 'Female', // same
        'address' => '123 New Street' // added
    ];

    // Fire the FormSubmissionUpdated event
    \Illuminate\Support\Facades\Log::info('Firing FormSubmissionUpdated event');
    event(new \App\Events\FormSubmissionUpdated(
        $formSubmission,
        $originalData,
        $updatedData,
        'form'
    ));

    return response()->json([
        'message' => 'Form submission update event fired successfully',
        'submission_id' => $formSubmission->_id,
        'changes' => array_diff_assoc($updatedData, $originalData)
    ]);
});

// Test route to simulate form submission deletion
Route::get('/test-form-delete', function () {
    // Create a form submission to delete
    $formSubmission = \App\Models\FormSubmission::create([
        'operation' => 'delete',
        'data' => [
            'name' => 'To Be Deleted',
            'email' => 'delete@example.com',
            'contact' => '555-000-0000',
            'gender' => 'Male'
        ],
        'status' => 'completed',
        'source' => 'form',
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent()
    ]);

    // Fire the FormSubmissionDeleted event
    \Illuminate\Support\Facades\Log::info('Firing FormSubmissionDeleted event');
    event(new \App\Events\FormSubmissionDeleted(
        $formSubmission->_id,
        $formSubmission->data,
        $formSubmission->operation,
        $formSubmission->student_id,
        $formSubmission->source
    ));

    // Actually delete the submission
    $formSubmission->delete();

    return response()->json([
        'message' => 'Form submission delete event fired successfully',
        'deleted_submission_id' => $formSubmission->_id
    ]);
});

// CSV notification polling endpoint
Route::get('/csv/last-batch', [\App\Http\Controllers\CsvNotificationController::class, 'lastBatch']);

// Notification routes
Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
Route::patch('/notifications/{notification}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
Route::patch('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.read_all');
Route::get('/notifications/stats', [\App\Http\Controllers\NotificationController::class, 'stats'])->name('notifications.stats');
Route::post('/notifications/test', [\App\Http\Controllers\NotificationController::class, 'createTest'])->name('notifications.test');
Route::delete('/notifications/{notification}', [\App\Http\Controllers\NotificationController::class, 'destroy'])->name('notifications.destroy');


// Student CRUD routes (explicit, not resource)
Route::get('students', [StudentController::class, 'index'])->name('students.index');
Route::get('students/create', [StudentController::class, 'create'])->name('students.create');
Route::post('students', [StudentController::class, 'store'])->name('students.store');
Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');
Route::get('students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
Route::put('students/{student}', [StudentController::class, 'update'])->name('students.update');
Route::delete('students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');

// Form Submission CRUD routes
Route::get('form-submissions', [FormSubmissionController::class, 'index'])->name('form_submissions.index');
Route::get('form-submissions/create', [FormSubmissionController::class, 'create'])->name('form_submissions.create');
Route::post('form-submissions', [FormSubmissionController::class, 'store'])->name('form_submissions.store');
Route::get('form-submissions/{formSubmission}', [FormSubmissionController::class, 'show'])->name('form_submissions.show');
Route::get('form-submissions/{formSubmission}/edit', [FormSubmissionController::class, 'edit'])->name('form_submissions.edit');
Route::put('form-submissions/{formSubmission}', [FormSubmissionController::class, 'update'])->name('form_submissions.update');
Route::delete('form-submissions/{formSubmission}', [FormSubmissionController::class, 'destroy'])->name('form_submissions.destroy');

// Form Submission CSV routes
Route::get('form-submissions/csv/upload', [FormSubmissionController::class, 'uploadCsv'])->name('form_submissions.upload_csv');
Route::post('form-submissions/csv/process', [FormSubmissionController::class, 'processCsv'])->name('form_submissions.process_csv');

// Form Submission API/Stats routes
Route::get('form-submissions/api/stats', [FormSubmissionController::class, 'stats'])->name('form_submissions.stats');
Route::get('form-submissions/api/latest', [FormSubmissionController::class, 'getLatest'])->name('form_submissions.latest');
Route::post('form-submissions/check-duplicate-email', [FormSubmissionController::class, 'checkDuplicateEmail'])->name('form_submissions.check_duplicate_email');
Route::get('form-submissions/api/recent-duplicates', [FormSubmissionController::class, 'getRecentDuplicateNotifications'])->name('form_submissions.recent_duplicates');
Route::delete('form-submissions/api/clear-duplicates', [FormSubmissionController::class, 'clearDuplicateNotifications'])->name('form_submissions.clear_duplicates');

Route::get('upload-csv', [CsvController::class, 'showForm']);
Route::post('upload-csv', [CsvController::class, 'upload'])->name('csv.upload');