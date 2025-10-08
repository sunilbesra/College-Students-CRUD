<?php

namespace App\Listeners;

use App\Events\CsvUploadStarted;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class CreateCsvUploadStartedNotification
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
    public function handle(CsvUploadStarted $event): void
    {
        try {
            $data = [
                'file_name' => $event->fileName,  // Fixed: changed from 'filename' to 'file_name'
                'operation' => $event->operation,
                'total_rows' => $event->totalRows,
                'ip_address' => $event->ipAddress,
                'user_agent' => $event->userAgent,
                'started_at' => now()
            ];

            NotificationService::createCsvUploadNotification('started', $data);
            
            Log::debug('CSV upload started notification created', [
                'filename' => $event->fileName,
                'operation' => $event->operation,
                'total_rows' => $event->totalRows
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create CSV upload started notification', [
                'error' => $e->getMessage(),
                'filename' => $event->fileName ?? 'unknown'
            ]);
        }
    }
}
