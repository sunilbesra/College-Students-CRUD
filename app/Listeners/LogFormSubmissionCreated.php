<?php

namespace App\Listeners;

use App\Events\FormSubmissionCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LogFormSubmissionCreated implements ShouldQueue
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
    public function handle(FormSubmissionCreated $event): void
    {
        Log::info('🎯 EVENT LISTENER: FormSubmissionCreated triggered', [
            'listener' => 'LogFormSubmissionCreated',
            'event_timestamp' => now()->toDateTimeString(),
            'submission_id' => $event->formSubmission->_id,
            'operation' => $event->formSubmission->operation,
            'source' => $event->source,
            'email' => $event->submissionData['email'] ?? 'No email',
            'name' => $event->submissionData['name'] ?? 'No name',
            'ip_address' => $event->formSubmission->ip_address,
            'user_agent' => substr($event->formSubmission->user_agent ?? '', 0, 100),
            'created_at' => $event->formSubmission->created_at
        ]);

        // Log additional information based on source
        if ($event->source === 'csv') {
            Log::info('📄 CSV submission event details', [
                'listener' => 'LogFormSubmissionCreated',
                'submission_id' => $event->formSubmission->_id,
                'csv_row' => $event->submissionData['csv_row'] ?? null,
                'batch_processing' => true
            ]);
        }

        // Log successful event processing
        Log::debug('✅ FormSubmissionCreated event processed successfully', [
            'listener' => 'LogFormSubmissionCreated',
            'submission_id' => $event->formSubmission->_id,
            'processing_time' => microtime(true)
        ]);

        // You can add more logging logic here, such as:
        // - Analytics tracking
        // - Audit trail creation
        // - Real-time notifications
    }
}