<?php

namespace App\Listeners;

use App\Events\FormSubmissionUpdated;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CreateUpdatedSubmissionNotification // implements ShouldQueue
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
    public function handle(FormSubmissionUpdated $event): void
    {
        try {
            $data = [
                'submission_id' => $event->formSubmission->_id ?? null,
                'email' => $event->updatedData['email'] ?? $event->originalData['email'] ?? 'Unknown',
                'name' => $event->updatedData['name'] ?? $event->originalData['name'] ?? 'Unknown',
                'source' => $event->source,
                'operation' => $event->formSubmission->operation ?? 'update',
                'changes' => $this->getChanges($event->originalData, $event->updatedData)
            ];

            NotificationService::createFormSubmissionNotification('updated', $data);
            
            Log::debug('Form submission updated notification created', [
                'submission_id' => $data['submission_id'],
                'email' => $data['email'],
                'changes_count' => count($data['changes'])
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create updated submission notification', [
                'error' => $e->getMessage(),
                'submission_id' => $event->formSubmission->_id ?? null
            ]);
        }
    }

    /**
     * Get the changes between original and updated data
     */
    private function getChanges(array $originalData, array $updatedData): array
    {
        $changes = [];
        
        // Find added/changed fields
        foreach ($updatedData as $key => $value) {
            if (!array_key_exists($key, $originalData) || $originalData[$key] !== $value) {
                $changes[$key] = [
                    'from' => $originalData[$key] ?? null,
                    'to' => $value
                ];
            }
        }
        
        // Find removed fields
        foreach ($originalData as $key => $value) {
            if (!array_key_exists($key, $updatedData)) {
                $changes[$key] = [
                    'from' => $value,
                    'to' => null
                ];
            }
        }
        
        return $changes;
    }
}
