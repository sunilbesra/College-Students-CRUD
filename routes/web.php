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

// CSV notification polling endpoint
Route::get('/csv/last-batch', [\App\Http\Controllers\CsvNotificationController::class, 'lastBatch']);


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

Route::get('upload-csv', [CsvController::class, 'showForm']);
Route::post('upload-csv', [CsvController::class, 'upload'])->name('csv.upload');