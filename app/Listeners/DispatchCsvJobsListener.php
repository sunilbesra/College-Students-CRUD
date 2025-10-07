<?php

namespace App\Listeners;

use App\Events\CsvBatchQueued;
use App\Jobs\ProcessStudentData;
use App\Models\CsvJob;
use Illuminate\Support\Facades\Log;

class DispatchCsvJobsListener
{
    /**
     * Handle the event.
     */
    public function handle(CsvBatchQueued $event)
    {
        $batchId = $event->fileName ?? '(unknown file)';
        $batchMeta = [
            'fileName' => $batchId,
            'count' => count($event->jobIds),
            'jobIds' => $event->jobIds,
            'timestamp' => now()->toDateTimeString(),
        ];

        Log::info("CsvBatchQueued received for file: {$batchId}. Jobs in batch: " . $batchMeta['count']);

        // Cache the latest batch metadata so the frontend can poll and show a notification.
        try {
            $store = app('csv.batch');
            $store->put('csv_last_batch', $batchMeta, 300);
        } catch (\Throwable $ex) {
            Log::warning('Failed to cache csv_last_batch: ' . $ex->getMessage());
        }

        if (empty($event->jobIds)) {
            Log::warning("CsvBatchQueued contained no job IDs for file: {$batchId}");
            return;
        }

        $queueName = config('queue.connections.beanstalkd.queue') ?? env('BEANSTALKD_QUEUE', 'csv_jobs');
        foreach ($event->jobIds as $jobId) {
            try {
                // Get the CsvJob record to extract data
                $csvJob = CsvJob::find($jobId);
                if (!$csvJob) {
                    Log::warning("CsvJob not found for ID: {$jobId}");
                    continue;
                }

                Log::debug("Dispatching ProcessStudentData for CSV jobId={$jobId} to queue={$queueName}");
                
                // Dispatch unified ProcessStudentData job for CSV processing
                ProcessStudentData::dispatch(
                    $csvJob->data,
                    'create', // CSV uploads are always creates (or upserts)
                    'csv',
                    $jobId
                )->onQueue($queueName)
                 ->delay(now()->addSeconds(1));
                
                Log::info("Dispatched ProcessStudentData for CSV jobId={$jobId}");
            } catch (\Throwable $e) {
                Log::error("Failed to dispatch ProcessStudentData for CSV ID {$jobId}: " . $e->getMessage(), [
                    'jobId' => $jobId,
                    'exception' => $e,
                ]);
            }
        }

        Log::info("CsvBatchQueued dispatch completed for file: {$batchId}");
    }
}
