<?php

namespace App\Listeners;

use App\Events\FormSubmissionProcessed;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CreateProcessedSubmissionNotification implements ShouldQueue
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
    public function handle(FormSubmissionProcessed $event): void
    {
        try {
            $data = [
                'submission_id' => $event->formSubmission->_id ?? null,
                'email' => $event->formSubmission->data['email'] ?? 'Unknown',
                'name' => $event->formSubmission->data['name'] ?? 'Unknown',
                'status' => $event->status,
                'operation' => $event->formSubmission->operation ?? 'create',
                'source' => $event->formSubmission->source ?? 'unknown'
            ];

            if ($event->status === 'completed') {
                NotificationService::createFormSubmissionNotification('processed', $data);
            } elseif ($event->status === 'failed') {
                $data['error'] = $event->errorMessage ?? 'Unknown error';
                NotificationService::createFormSubmissionNotification('failed', $data);
            }
            
            Log::debug('Form submission processed notification created', [
                'submission_id' => $data['submission_id'],
                'status' => $event->status
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create processed submission notification', [
                'error' => $e->getMessage(),
                'submission_id' => $event->formSubmission->_id ?? null,
                'status' => $event->status
            ]);
        }
    }
}
