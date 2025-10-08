<?php

namespace App\Listeners;

use App\Events\FormSubmissionCreated;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CreateFormSubmissionNotification // implements ShouldQueue
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
    public function handle(FormSubmissionCreated $event): void
    {
        try {
            $data = [
                'submission_id' => $event->formSubmission->_id ?? null,
                'email' => $event->submissionData['email'] ?? 'Unknown',
                'name' => $event->submissionData['name'] ?? 'Unknown',
                'source' => $event->source,
                'operation' => $event->formSubmission->operation ?? 'create'
            ];

            NotificationService::createFormSubmissionNotification('created', $data);
            
            Log::debug('Form submission notification created', [
                'submission_id' => $data['submission_id'],
                'email' => $data['email']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create form submission notification', [
                'error' => $e->getMessage(),
                'submission_id' => $event->formSubmission->_id ?? null
            ]);
        }
    }
}
