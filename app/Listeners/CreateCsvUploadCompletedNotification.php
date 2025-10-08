<?php

namespace App\Listeners;

use App\Events\CsvUploadCompleted;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class CreateCsvUploadCompletedNotification
{

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
        try {
            $data = [
                'file_name' => $event->fileName,  // Fixed: changed from 'filename' to 'file_name'
                'operation' => $event->operation,
                'summary' => $event->validationSummary,  // Fixed: changed from 'validation_summary' to 'summary'
                'processing_time_ms' => $event->processingTimeMs,
                'batch_job_id' => $event->batchJobId,
                'completed_at' => now()
            ];

            NotificationService::createCsvUploadNotification('completed', $data);
            
            Log::debug('CSV upload completed notification created', [
                'filename' => $event->fileName,
                'operation' => $event->operation,
                'processing_time_ms' => $event->processingTimeMs,
                'validation_summary' => $event->validationSummary
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create CSV upload completed notification', [
                'error' => $e->getMessage(),
                'filename' => $event->fileName ?? 'unknown'
            ]);
        }
    }
}
