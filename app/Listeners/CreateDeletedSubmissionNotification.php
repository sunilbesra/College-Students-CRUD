<?php

namespace App\Listeners;

use App\Events\FormSubmissionDeleted;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CreateDeletedSubmissionNotification // implements ShouldQueue
{
    // use InteractsWithQueue;

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
    public function handle(FormSubmissionDeleted $event): void
    {
        try {
            $data = [
                'submission_id' => $event->submissionId,
                'email' => $event->submissionData['email'] ?? 'Unknown',
                'name' => $event->submissionData['name'] ?? 'Unknown',
                'source' => $event->source,
                'operation' => $event->operation,
                'student_id' => $event->studentId,
                'deleted_data' => $event->submissionData
            ];

            NotificationService::createFormSubmissionNotification('deleted', $data);
            
            Log::debug('Form submission deleted notification created', [
                'submission_id' => $data['submission_id'],
                'email' => $data['email'],
                'operation' => $event->operation
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create deleted submission notification', [
                'error' => $e->getMessage(),
                'submission_id' => $event->submissionId
            ]);
        }
    }
}
