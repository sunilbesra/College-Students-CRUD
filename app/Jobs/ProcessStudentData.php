<?php

namespace App\Jobs;

use App\Models\Student;
use App\Models\CsvJob;
use App\Events\StudentCreated;
use App\Events\StudentUpdated;
use App\Events\StudentDeleted;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\StudentValidator;

class ProcessStudentData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $data;
    public string $operation; // 'create', 'update', 'delete'
    public ?string $trackingId; // CsvJob ID for CSV operations, null for direct form operations
    public ?string $studentId;
    public string $source; // 'form' or 'csv'

    /**
     * Create a new job instance.
     */
    public function __construct(
        array $data,
        string $operation,
        string $source = 'form',
        ?string $trackingId = null,
        ?string $studentId = null
    ) {
        $this->data = $data;
        $this->operation = $operation;
        $this->source = $source;
        $this->trackingId = $trackingId;
        $this->studentId = $studentId;
        
        // Set the queue name based on source
        $queueName = $source === 'csv' 
            ? config('queue.connections.beanstalkd.queue', 'csv_jobs')
            : env('BEANSTALKD_STUDENT_QUEUE', 'student_jobs');
            
        $this->onQueue($queueName);
    }

    public function handle()
    {
        // Update tracking record if it's a CSV job
        $trackingRecord = null;
        if ($this->source === 'csv' && $this->trackingId) {
            $trackingRecord = CsvJob::find($this->trackingId);
            if ($trackingRecord) {
                $trackingRecord->status = 'processing';
                $trackingRecord->save();
            }
        }

        try {
            Log::info("Processing student data", [
                'operation' => $this->operation,
                'source' => $this->source,
                'tracking_id' => $this->trackingId,
                'student_id' => $this->studentId
            ]);

            $result = match($this->operation) {
                'create' => $this->handleCreate(),
                'update' => $this->handleUpdate(),
                'delete' => $this->handleDelete(),
                default => throw new \InvalidArgumentException("Unknown operation: {$this->operation}")
            };

            // Mark tracking record as completed
            if ($trackingRecord) {
                $trackingRecord->status = 'completed';
                $trackingRecord->save();
            }

            Log::info("Student data processed successfully", [
                'operation' => $this->operation,
                'source' => $this->source,
                'result' => $result
            ]);

        } catch (\Illuminate\Validation\ValidationException $ve) {
            $msg = 'Validation failed: ' . implode('; ', array_map(
                fn($v) => is_array($v) ? implode(', ', $v) : $v, 
                $ve->errors()
            ));
            
            Log::warning($msg, [
                'operation' => $this->operation,
                'source' => $this->source,
                'tracking_id' => $this->trackingId,
                'data' => $this->data
            ]);

            if ($trackingRecord) {
                $trackingRecord->status = 'failed';
                $trackingRecord->error_message = $msg;
                $trackingRecord->save();
            }

            // Don't rethrow validation errors - mark as failed and continue
            return;

        } catch (\Throwable $e) {
            $msg = 'Processing failed: ' . $e->getMessage();
            
            Log::error($msg, [
                'operation' => $this->operation,
                'source' => $this->source,
                'tracking_id' => $this->trackingId,
                'exception' => $e
            ]);

            if ($trackingRecord) {
                $trackingRecord->status = 'failed';
                $trackingRecord->error_message = $msg;
                $trackingRecord->save();
            }

            // Rethrow so worker handles retry/attempts
            throw $e;
        }
    }

    protected function handleCreate(): array
    {
        // Validate the data
        $validated = StudentValidator::validate($this->data);

        // Check for existing student by email
        $existingStudent = null;
        if (!empty($validated['email'])) {
            $existingStudent = Student::where('email', $validated['email'])->first();
        }

        if ($existingStudent) {
            // Update existing student instead of creating duplicate
            $apply = array_filter($validated, fn($v) => $v !== null);
            $existingStudent->fill($apply);
            $existingStudent->save();
            
            event(new StudentUpdated($existingStudent));
            
            return [
                'action' => 'updated_existing',
                'student_id' => (string) $existingStudent->_id,
                'email' => $existingStudent->email
            ];
        } else {
            // Create new student
            $apply = array_filter($validated, fn($v) => $v !== null);
            $student = Student::create($apply);
            
            event(new StudentCreated($student));
            
            return [
                'action' => 'created_new',
                'student_id' => (string) $student->_id,
                'email' => $student->email
            ];
        }
    }

    protected function handleUpdate(): array
    {
        if (!$this->studentId) {
            throw new \InvalidArgumentException('Student ID required for update operation');
        }

        // Find the student
        $student = Student::where('_id', $this->studentId)->first();
        if (!$student) {
            throw new \RuntimeException("Student not found with ID: {$this->studentId}");
        }

        // Validate the data (ignore current student's email for uniqueness)
        $validated = StudentValidator::validate($this->data, $this->studentId);

        // Apply updates
        $apply = array_filter($validated, fn($v) => $v !== null);
        $student->fill($apply);
        $student->save();

        event(new StudentUpdated($student));

        return [
            'action' => 'updated',
            'student_id' => (string) $student->_id,
            'email' => $student->email
        ];
    }

    protected function handleDelete(): array
    {
        if (!$this->studentId) {
            throw new \InvalidArgumentException('Student ID required for delete operation');
        }

        // Find the student
        $student = Student::where('_id', $this->studentId)->first();
        if (!$student) {
            throw new \RuntimeException("Student not found with ID: {$this->studentId}");
        }

        $studentData = [
            'student_id' => (string) $student->_id,
            'email' => $student->email,
            'name' => $student->name
        ];

        event(new StudentDeleted($student));
        
        $student->delete();

        return [
            'action' => 'deleted',
            ...$studentData
        ];
    }
}