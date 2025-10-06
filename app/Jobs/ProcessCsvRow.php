<?php

namespace App\Jobs;

use App\Models\CsvJob;
use App\Models\Student;
use App\Events\StudentCreated;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCsvRow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $jobId;

    public function __construct($jobId)
    {
        $this->jobId = $jobId;
    }

    public function handle()
    {
        $jobRecord = CsvJob::find($this->jobId);

        if (!$jobRecord) return;

        // Mark as processing
        $jobRecord->status = 'processing';
        $jobRecord->save();

        try {
            $row = $jobRecord->data;

            // If data field is a JSON string (stored as string), decode it
            if (is_string($row)) {
                $decoded = json_decode($row, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $row = $decoded;
                }
            }

            // Map CSV fields to Student attributes
            $studentData = [
                'name' => $row['name'] ?? null,
                'email' => $row['email'] ?? null,
                'contact' => $row['contact'] ?? null,
                'profile_image' => isset($row['profile_image']) ? (string) $row['profile_image'] : null,
                'address' => $row['address'] ?? null,
                'college' => $row['college'] ?? null,
                'gender' => $row['gender'] ?? null,
                'dob' => $row['dob'] ?? null,
                'enrollment_status' => $row['enrollment_status'] ?? null,
                'course' => $row['course'] ?? null,
                'agreed_to_terms' => isset($row['agreed_to_terms']) ? (bool)$row['agreed_to_terms'] : null,
            ];


            // Ensure helper exists and call it from global namespace to avoid namespacing issues
            if (! function_exists('student_validate')) {
                $msg = 'Validation helper "student_validate" not available. Ensure app/helpers.php is autoloaded.';
                Log::error($msg, ['job_id' => $this->jobId]);
                $jobRecord->status = 'failed';
                $jobRecord->error_message = $msg;
                $jobRecord->save();
                return;
            }

            try {
                $validated = \student_validate($studentData);
            } catch (\Illuminate\Validation\ValidationException $ve) {
                $msg = 'CSV row validation failed: ' . implode('; ', array_map(function ($v) { return is_array($v) ? implode(', ', $v) : $v; }, $ve->errors()));
                Log::warning($msg, ['job_id' => $this->jobId, 'row' => $row]);
                $jobRecord->status = 'failed';
                $jobRecord->error_message = $msg;
                $jobRecord->save();
                return;
            }

            // Upsert student by email if available, else by provided id or create new
            $student = null;

            if (!empty($validated['email'])) {
                $student = Student::where('email', $validated['email'])->first();
            }

            if (!$student && !empty($row['id'])) {
                // try by row identifier id (string or numeric)
                $student = Student::where('_id', $row['id'])->first();
            }

            $isNew = false;
            $apply = array_filter($validated, function ($v) {
                return $v !== null;
            });

            if ($student) {
                $student->fill($apply);
                $student->save();
            } else {
                $student = Student::create($apply);
                $isNew = true;
            }

            // Dispatch StudentCreated event for downstream listeners (notification)
            event(new StudentCreated($student));

            // Mark as completed
            $jobRecord->status = 'completed';
            $jobRecord->save();
        } catch (\Throwable $e) {
            // Catch Throwable so fatal errors (Error) are handled and job status updated.
            Log::error('ProcessCsvRow failed for job ' . $this->jobId . ': ' . $e->getMessage());
            $jobRecord->status = 'failed';
            $jobRecord->error_message = $e->getMessage();
            $jobRecord->save();

            // rethrow so worker marks attempt/tries correctly
            throw $e;
        }
    }
}