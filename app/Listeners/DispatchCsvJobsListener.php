<?php

namespace App\Listeners;

use App\Events\CsvBatchQueued;
use App\Jobs\ProcessCsvRow;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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
            Cache::put('csv_last_batch', $batchMeta, 300); // keep for 5 minutes
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
                Log::debug("Dispatching ProcessCsvRow for jobId={$jobId} to queue={$queueName}");
                ProcessCsvRow::dispatch($jobId)
                    ->onQueue($queueName)
                    ->delay(now()->addSeconds(1));
                Log::info("Dispatched ProcessCsvRow jobId={$jobId}");
            } catch (\Throwable $e) {
                Log::error("Failed to dispatch ProcessCsvRow for ID {$jobId}: " . $e->getMessage(), [
                    'jobId' => $jobId,
                    'exception' => $e,
                ]);
            }
        }

        Log::info("CsvBatchQueued dispatch completed for file: {$batchId}");
    }
}
