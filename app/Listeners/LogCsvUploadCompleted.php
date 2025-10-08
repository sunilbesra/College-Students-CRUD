<?php

namespace App\Listeners;

use App\Events\CsvUploadCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class LogCsvUploadCompleted implements ShouldQueue
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
    public function handle(CsvUploadCompleted $event): void
    {
        Log::info('📥 EVENT LISTENER: CsvUploadCompleted triggered', [
            'listener' => 'LogCsvUploadCompleted',
            'event_timestamp' => now()->toDateTimeString(),
            'file_name' => $event->fileName,
            'operation' => $event->operation,
            'processing_time_ms' => $event->processingTimeMs,
            'batch_job_id' => $event->batchJobId,
            'validation_summary' => $event->validationSummary,
            'completed_at' => now()
        ]);

        // Log detailed validation results
        Log::debug('📋 Logging detailed validation results', [
            'listener' => 'LogCsvUploadCompleted',
            'file_name' => $event->fileName
        ]);
        $this->logValidationResults($event);

        // Update completion statistics
        Log::debug('📊 Updating completion statistics', [
            'listener' => 'LogCsvUploadCompleted',
            'file_name' => $event->fileName
        ]);
        $this->updateCompletionStats($event);

        // Handle success/failure notifications
        Log::debug('🔔 Handling notifications', [
            'listener' => 'LogCsvUploadCompleted',
            'file_name' => $event->fileName
        ]);
        $this->handleNotifications($event);
        
        Log::debug('✅ CsvUploadCompleted event processed successfully', [
            'listener' => 'LogCsvUploadCompleted',
            'file_name' => $event->fileName
        ]);
    }

    /**
     * Log detailed validation results
     */
    private function logValidationResults(CsvUploadCompleted $event): void
    {
        $summary = $event->validationSummary;
        
        Log::info('CSV validation summary', [
            'file_name' => $event->fileName,
            'total_rows' => $summary['total_rows'] ?? 0,
            'valid_rows' => $summary['valid_rows'] ?? 0,
            'invalid_rows' => $summary['invalid_rows'] ?? 0,
            'duplicate_rows' => $summary['duplicate_rows'] ?? 0,
            'success_rate' => $summary['total_rows'] > 0 ? 
                round(($summary['valid_rows'] / $summary['total_rows']) * 100, 2) : 0
        ]);

        // Log warnings for low success rates
        if ($summary['total_rows'] > 0) {
            $successRate = ($summary['valid_rows'] / $summary['total_rows']) * 100;
            
            if ($successRate < 50) {
                Log::warning('Low CSV upload success rate', [
                    'file_name' => $event->fileName,
                    'success_rate' => round($successRate, 2),
                    'validation_summary' => $summary
                ]);
            }
        }
    }

    /**
     * Update completion statistics
     */
    private function updateCompletionStats(CsvUploadCompleted $event): void
    {
        try {
            $summary = $event->validationSummary;

            // Update daily completion stats
            $dailyKey = "csv_completions_daily_" . now()->format('Y-m-d');
            Cache::increment($dailyKey, 1);
            Cache::put($dailyKey, Cache::get($dailyKey, 0), now()->addDays(30));

            // Update processing time statistics
            $avgTimeKey = "csv_avg_processing_time";
            $currentAvg = Cache::get($avgTimeKey, 0);
            $count = Cache::get("csv_processing_count", 0) + 1;
            $newAvg = (($currentAvg * ($count - 1)) + $event->processingTimeMs) / $count;
            
            Cache::put($avgTimeKey, $newAvg, now()->addDays(30));
            Cache::put("csv_processing_count", $count, now()->addDays(30));

            // Track validation statistics
            if (isset($summary['valid_rows'])) {
                Cache::increment("csv_total_valid_rows", $summary['valid_rows']);
            }
            if (isset($summary['invalid_rows'])) {
                Cache::increment("csv_total_invalid_rows", $summary['invalid_rows']);
            }
            if (isset($summary['duplicate_rows'])) {
                Cache::increment("csv_total_duplicate_rows", $summary['duplicate_rows']);
            }

        } catch (\Exception $e) {
            Log::warning('Failed to update CSV completion stats', [
                'error' => $e->getMessage(),
                'file_name' => $event->fileName
            ]);
        }
    }

    /**
     * Handle completion notifications
     */
    private function handleNotifications(CsvUploadCompleted $event): void
    {
        $summary = $event->validationSummary;
        
        // Determine if this upload needs attention
        $needsAttention = false;
        $reasons = [];

        if ($summary['invalid_rows'] > 0) {
            $needsAttention = true;
            $reasons[] = "Contains {$summary['invalid_rows']} invalid rows";
        }

        if ($summary['duplicate_rows'] > 0) {
            $needsAttention = true;
            $reasons[] = "Contains {$summary['duplicate_rows']} duplicate emails";
        }

        if ($event->processingTimeMs > 30000) { // More than 30 seconds
            $needsAttention = true;
            $reasons[] = "Long processing time: " . round($event->processingTimeMs / 1000, 2) . "s";
        }

        if ($needsAttention) {
            Log::notice('CSV upload needs attention', [
                'file_name' => $event->fileName,
                'reasons' => $reasons,
                'validation_summary' => $summary,
                'processing_time_ms' => $event->processingTimeMs
            ]);

            // Here you could add:
            // - Email notifications to administrators
            // - Slack notifications
            // - Dashboard alerts
        }
    }
}