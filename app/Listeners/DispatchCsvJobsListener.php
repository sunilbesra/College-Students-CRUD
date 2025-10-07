<?php

namespace App\Listeners;

use App\Events\CsvBatchQueued;
use App\Jobs\ProcessCsvRow;
use App\Jobs\ProcessCsvBatch;
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

        // Dispatch a single batch job which will process all jobIds. This avoids
        // enqueuing one job per row at the moment the event is handled which
        // previously resulted in duplicate/extra queue entries.
        try {
            $queueName = config('queue.connections.beanstalkd.queue') ?? env('BEANSTALKD_QUEUE', 'csv_jobs');
            Log::info("Dispatching ProcessCsvBatch to queue={$queueName} for count=" . count($event->jobIds));
            ProcessCsvBatch::dispatch($event->jobIds)
                ->onQueue($queueName)
                ->delay(now()->addSeconds(1));
            Log::info("Dispatched ProcessCsvBatch for file: {$batchId}");
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch ProcessCsvBatch: ' . $e->getMessage(), ['exception' => $e]);
        }

        Log::info("CsvBatchQueued dispatch completed for file: {$batchId}");
    }
}
