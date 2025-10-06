<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\CsvController;
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


Route::get('upload-csv', [CsvController::class, 'showForm']);
Route::post('upload-csv', [CsvController::class, 'upload'])->name('csv.upload');