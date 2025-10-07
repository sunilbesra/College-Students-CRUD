<?php

namespace App\Jobs;

use App\Models\CsvJob;
use App\Models\Student;
use App\Events\StudentCreated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCsvBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $jobIds;

    public function __construct(array $jobIds)
    {
        $this->jobIds = $jobIds;
    }

    public function handle()
    {
        foreach ($this->jobIds as $jobId) {
            try {
                $jobRecord = CsvJob::find($jobId);
                if (! $jobRecord) {
                    Log::warning("ProcessCsvBatch: missing CsvJob id={$jobId}");
                    continue;
                }

                $jobRecord->status = 'processing';
                $jobRecord->save();

                $row = $jobRecord->data;
                if (is_string($row)) {
                    $decoded = json_decode($row, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $row = $decoded;
                    }
                }

                if (! function_exists('student_validate')) {
                    $msg = 'Validation helper "student_validate" not available.';
                    Log::error($msg, ['job_id' => $jobId]);
                    $jobRecord->status = 'failed';
                    $jobRecord->error_message = $msg;
                    $jobRecord->save();
                    continue;
                }

                $validated = null;
                try {
                    $validated = \student_validate($row);
                } catch (\Illuminate\Validation\ValidationException $ve) {
                    $msg = 'CSV row validation failed: ' . implode('; ', array_map(function ($v) { return is_array($v) ? implode(', ', $v) : $v; }, $ve->errors()));
                    Log::warning($msg, ['job_id' => $jobId, 'row' => $row]);
                    $jobRecord->status = 'failed';
                    $jobRecord->error_message = $msg;
                    $jobRecord->save();
                    continue;
                }

                // Upsert by email if present
                $student = null;
                if (! empty($validated['email'])) {
                    $student = Student::where('email', $validated['email'])->first();
                }
                if (! $student && ! empty($row['id'])) {
                    $student = Student::where('_id', $row['id'])->first();
                }

                $apply = array_filter($validated, function ($v) {
                    return $v !== null;
                });

                if ($student) {
                    $student->fill($apply);
                    $student->save();
                } else {
                    $student = Student::create($apply);
                }

                event(new StudentCreated($student));

                $jobRecord->status = 'completed';
                $jobRecord->save();
            } catch (\Throwable $e) {
                Log::error('ProcessCsvBatch failed for job ' . $jobId . ': ' . $e->getMessage());
                if (isset($jobRecord) && $jobRecord) {
                    $jobRecord->status = 'failed';
                    $jobRecord->error_message = $e->getMessage();
                    $jobRecord->save();
                }
                // continue to next row; do not rethrow to avoid entire batch retry
            }
        }
    }
}
