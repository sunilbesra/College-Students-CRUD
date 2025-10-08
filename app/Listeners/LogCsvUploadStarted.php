<?php

namespace App\Listeners;

use App\Events\CsvUploadStarted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class LogCsvUploadStarted implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(CsvUploadStarted $event): void
    {
        Log::info('📤 EVENT LISTENER: CsvUploadStarted triggered', [
            'listener' => 'LogCsvUploadStarted',
            'event_timestamp' => now()->toDateTimeString(),
            'file_name' => $event->fileName,
            'operation' => $event->operation,
            'total_rows' => $event->totalRows,
            'ip_address' => $event->ipAddress,
            'user_agent' => substr($event->userAgent ?? '', 0, 100),
            'started_at' => now()
        ]);

        // Store upload start time for duration calculation
        $uploadKey = "csv_upload_start_" . md5($event->fileName . now()->timestamp);
        Cache::put($uploadKey, now(), now()->addHours(2));
        
        Log::debug('⏰ Stored CSV upload start time in cache', [
            'listener' => 'LogCsvUploadStarted',
            'cache_key' => $uploadKey,
            'file_name' => $event->fileName
        ]);

        // Update CSV upload statistics
        Log::debug('📊 Updating CSV upload statistics', [
            'listener' => 'LogCsvUploadStarted',
            'file_name' => $event->fileName,
            'total_rows' => $event->totalRows
        ]);
        
        $this->updateCsvStats($event);
        
        Log::debug('✅ CsvUploadStarted event processed successfully', [
            'listener' => 'LogCsvUploadStarted',
            'file_name' => $event->fileName
        ]);

        // You can add more logic here:
        // - Send notifications to administrators
        // - Create audit trail entries
        // - Initialize progress tracking
    }

    /**
     * Update CSV upload statistics
     */
    private function updateCsvStats(CsvUploadStarted $event): void
    {
        try {
            // Increment daily CSV upload counter
            $dailyKey = "csv_uploads_daily_" . now()->format('Y-m-d');
            Cache::increment($dailyKey, 1);
            Cache::put($dailyKey, Cache::get($dailyKey, 0), now()->addDays(30));

            // Increment operation-specific counter
            $operationKey = "csv_uploads_operation_{$event->operation}";
            Cache::increment($operationKey, 1);

            // Track total rows being processed
            $rowsKey = "csv_uploads_total_rows_" . now()->format('Y-m-d');
            Cache::increment($rowsKey, $event->totalRows);
            Cache::put($rowsKey, Cache::get($rowsKey, 0), now()->addDays(30));

        } catch (\Exception $e) {
            Log::warning('Failed to update CSV upload stats', [
                'error' => $e->getMessage(),
                'file_name' => $event->fileName
            ]);
        }
    }
}