<?php

namespace App\Listeners;

use App\Events\FormSubmissionProcessed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class UpdateFormSubmissionStats implements ShouldQueue
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
        Log::info('📊 EVENT LISTENER: FormSubmissionProcessed triggered', [
            'listener' => 'UpdateFormSubmissionStats',
            'event_timestamp' => now()->toDateTimeString(),
            'submission_id' => $event->formSubmission->_id,
            'status' => $event->status,
            'operation' => $event->formSubmission->operation,
            'source' => $event->formSubmission->source,
            'has_error' => !empty($event->errorMessage),
            'error_message' => $event->errorMessage ? substr($event->errorMessage, 0, 200) : null,
            'processed_at' => now()
        ]);

        // Update cached statistics
        Log::debug('📈 Updating cached statistics', [
            'listener' => 'UpdateFormSubmissionStats',
            'submission_id' => $event->formSubmission->_id,
            'status' => $event->status
        ]);
        
        $this->updateCachedStats($event);

        // Handle different status outcomes
        switch ($event->status) {
            case 'completed':
                Log::debug('✅ Handling successful processing', [
                    'listener' => 'UpdateFormSubmissionStats',
                    'submission_id' => $event->formSubmission->_id
                ]);
                $this->handleSuccessfulProcessing($event);
                break;
            case 'failed':
                Log::debug('❌ Handling failed processing', [
                    'listener' => 'UpdateFormSubmissionStats',
                    'submission_id' => $event->formSubmission->_id,
                    'error' => $event->errorMessage
                ]);
                $this->handleFailedProcessing($event);
                break;
        }
        
        Log::debug('✅ FormSubmissionProcessed event handled successfully', [
            'listener' => 'UpdateFormSubmissionStats',
            'submission_id' => $event->formSubmission->_id
        ]);
    }

    /**
     * Update cached statistics for dashboard
     */
    private function updateCachedStats(FormSubmissionProcessed $event): void
    {
        try {
            Log::debug('🗺 Updating cached statistics details', [
                'listener' => 'UpdateFormSubmissionStats',
                'submission_id' => $event->formSubmission->_id,
                'status' => $event->status,
                'operation' => $event->formSubmission->operation,
                'source' => $event->formSubmission->source
            ]);
            
            // Increment status counters
            $statusKey = "form_submissions_count_{$event->status}";
            $oldStatusValue = Cache::get($statusKey, 0);
            Cache::increment($statusKey, 1);
            Log::debug("🔢 Updated status counter: {$statusKey} from {$oldStatusValue} to " . Cache::get($statusKey, 0));

            // Increment operation counters
            $operationKey = "form_submissions_operation_{$event->formSubmission->operation}";
            $oldOperationValue = Cache::get($operationKey, 0);
            Cache::increment($operationKey, 1);
            Log::debug("🔢 Updated operation counter: {$operationKey} from {$oldOperationValue} to " . Cache::get($operationKey, 0));

            // Increment source counters
            $sourceKey = "form_submissions_source_{$event->formSubmission->source}";
            $oldSourceValue = Cache::get($sourceKey, 0);
            Cache::increment($sourceKey, 1);
            Log::debug("🔢 Updated source counter: {$sourceKey} from {$oldSourceValue} to " . Cache::get($sourceKey, 0));

            // Update hourly stats
            $hourKey = "form_submissions_hourly_" . now()->format('Y-m-d-H');
            $oldHourlyValue = Cache::get($hourKey, 0);
            Cache::increment($hourKey, 1);
            Log::debug("🔢 Updated hourly counter: {$hourKey} from {$oldHourlyValue} to " . Cache::get($hourKey, 0));

            // Set expiration for hourly stats (keep for 7 days)
            Cache::put($hourKey, Cache::get($hourKey, 0), now()->addDays(7));
            
            Log::debug('✅ All cached statistics updated successfully', [
                'submission_id' => $event->formSubmission->_id
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to update cached stats', [
                'listener' => 'UpdateFormSubmissionStats',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'submission_id' => $event->formSubmission->_id
            ]);
        }
    }

    /**
     * Handle successful processing
     */
    private function handleSuccessfulProcessing(FormSubmissionProcessed $event): void
    {
        Log::info('✨ Form submission successfully processed', [
            'listener' => 'UpdateFormSubmissionStats',
            'method' => 'handleSuccessfulProcessing',
            'submission_id' => $event->formSubmission->_id,
            'operation' => $event->formSubmission->operation,
            'source' => $event->formSubmission->source,
            'data_keys' => array_keys($event->formSubmission->data ?? []),
            'processed_at' => $event->formSubmission->processed_at
        ]);

        // You can add success-specific logic here:
        // - Send confirmation emails
        // - Trigger webhooks
        // - Update external systems
        
        Log::debug('✅ Success processing completed', [
            'submission_id' => $event->formSubmission->_id
        ]);
    }

    /**
     * Handle failed processing
     */
    private function handleFailedProcessing(FormSubmissionProcessed $event): void
    {
        Log::error('⚠️ Form submission processing failed', [
            'listener' => 'UpdateFormSubmissionStats',
            'method' => 'handleFailedProcessing',
            'submission_id' => $event->formSubmission->_id,
            'operation' => $event->formSubmission->operation,
            'source' => $event->formSubmission->source,
            'error_message' => $event->errorMessage,
            'data' => $event->formSubmission->data ?? [],
            'failed_at' => now()
        ]);

        // You can add failure-specific logic here:
        // - Send alert notifications
        // - Create retry mechanisms
        // - Update monitoring systems
        
        Log::debug('❌ Failure processing completed', [
            'submission_id' => $event->formSubmission->_id
        ]);
    }
}