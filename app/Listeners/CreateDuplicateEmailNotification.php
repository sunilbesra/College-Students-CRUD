<?php

namespace App\Listeners;

use App\Events\DuplicateEmailDetected;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CreateDuplicateEmailNotification implements ShouldQueue
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
    public function handle(DuplicateEmailDetected $event): void
    {
        try {
            $data = [
                'email' => $event->email,
                'source' => $event->source,
                'existing_submission_id' => $event->existingSubmissionId,
                'attempted_data' => $event->attemptedData,
                'row' => $event->csvRow ?? null
            ];

            NotificationService::createFormSubmissionNotification('duplicate', $data);
            
            Log::debug('Duplicate email notification created', [
                'email' => $event->email,
                'source' => $event->source
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create duplicate email notification', [
                'error' => $e->getMessage(),
                'email' => $event->email,
                'source' => $event->source
            ]);
        }
    }
}
