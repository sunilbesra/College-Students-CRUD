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
                'profile_image' => $row['profile_image'] ?? null,
                'address' => $row['address'] ?? null,
                'college' => $row['college'] ?? null,
            ];

            // If email is missing, avoid creating duplicate-null-key entries
            if (empty($studentData['email'])) {
                $msg = 'Missing email in CSV row; skipping to avoid unique index error';
                Log::warning($msg, ['job_id' => $this->jobId, 'row' => $row]);
                $jobRecord->status = 'failed';
                $jobRecord->error_message = $msg;
                $jobRecord->save();
                return;
            }

            // Upsert student by email if available, else by provided id or create new
            $student = null;

            if (!empty($studentData['email'])) {
                $student = Student::where('email', $studentData['email'])->first();
            }

            if (!$student && !empty($row['id'])) {
                // try by row identifier id (string or numeric)
                $student = Student::where('_id', $row['id'])->first();
            }

            $isNew = false;
            if ($student) {
                $student->fill($studentData);
                $student->save();
            } else {
                $student = Student::create($studentData);
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