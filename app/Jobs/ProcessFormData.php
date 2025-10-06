<?php

namespace App\Jobs;

use App\Models\Student;
use App\Models\CsvJob;
use App\Events\StudentCreated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessFormData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $data;
    public ?string $csvJobId;

    /**
     * Create a new job instance.
     * @param array $data
     * @param string|null $csvJobId
     */
    public function __construct(array $data, ?string $csvJobId = null)
    {
        $this->data = $data;
        $this->csvJobId = $csvJobId;
    }

    public function handle()
    {
        $jobRecord = null;
        if ($this->csvJobId) {
            $jobRecord = CsvJob::find($this->csvJobId);
            if ($jobRecord) {
                $jobRecord->status = 'processing';
                $jobRecord->save();
            }
        }

        try {
            // Ensure profile_image is a string (path) if present
            if (isset($this->data['profile_image']) && is_object($this->data['profile_image'])) {
                // If it's an uploaded file object, we can't move here; convert to filename placeholder
                $this->data['profile_image'] = (string) $this->data['profile_image'];
            } elseif (isset($this->data['profile_image'])) {
                $this->data['profile_image'] = (string) $this->data['profile_image'];
            }

            // Ensure validation helper exists and call it from global namespace
            if (! function_exists('student_validate')) {
                $msg = 'Validation helper "student_validate" not available. Ensure app/helpers.php is autoloaded.';
                Log::error($msg, ['csvJobId' => $this->csvJobId]);
                if ($jobRecord) {
                    $jobRecord->status = 'failed';
                    $jobRecord->error_message = $msg;
                    $jobRecord->save();
                }
                return;
            }

            // Validate using helper; throws ValidationException on failure
            $validated = \student_validate($this->data);

            // Upsert by email if available
            $student = null;
            if (!empty($validated['email'])) {
                $student = Student::where('email', $validated['email'])->first();
            }

            if (!$student && !empty($validated['id'])) {
                $student = Student::where('_id', $validated['id'])->first();
            }

            $isNew = false;
            // Avoid overwriting existing values with nulls: only apply keys that are present and not null
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

            // Fire event for downstream listeners
            event(new StudentCreated($student));

            if ($jobRecord) {
                $jobRecord->status = 'completed';
                $jobRecord->save();
            }
        } catch (\Illuminate\Validation\ValidationException $ve) {
            $msg = 'Validation failed: ' . implode('; ', array_map(function ($v) { return is_array($v) ? implode(', ', $v) : $v; }, $ve->errors()));
            Log::warning($msg, ['csvJobId' => $this->csvJobId, 'data' => $this->data]);
            if ($jobRecord) {
                $jobRecord->status = 'failed';
                $jobRecord->error_message = $msg;
                $jobRecord->save();
            }
            // don't rethrow — mark as failed in tracking and stop
            return;
        } catch (\Throwable $e) {
            Log::error('ProcessFormData job failed: ' . $e->getMessage(), ['csvJobId' => $this->csvJobId, 'data' => $this->data]);
            if ($jobRecord) {
                $jobRecord->status = 'failed';
                $jobRecord->error_message = $e->getMessage();
                $jobRecord->save();
            }
            // rethrow so worker handles retry/attempts
            throw $e;
        }
    }
}
