<?php

namespace App\Listeners;

use App\Events\DuplicateEmailDetected;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class HandleDuplicateEmail implements ShouldQueue
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
        Log::warning('🔄 EVENT LISTENER: DuplicateEmailDetected triggered', [
            'listener' => 'HandleDuplicateEmail',
            'event_timestamp' => now()->toDateTimeString(),
            'email' => $event->email,
            'source' => $event->source,
            'existing_submission_id' => $event->existingSubmissionId,
            'csv_row' => $event->csvRow,
            'attempted_data_keys' => $event->attemptedData ? array_keys($event->attemptedData) : null,
            'detected_at' => now()
        ]);

        // Track duplicate statistics
        Log::debug('📊 Updating duplicate statistics', [
            'listener' => 'HandleDuplicateEmail',
            'email' => $event->email,
            'source' => $event->source
        ]);
        $this->updateDuplicateStats($event);

        // Handle different sources differently
        Log::debug('🎯 Handling source-specific duplicate logic', [
            'listener' => 'HandleDuplicateEmail',
            'email' => $event->email,
            'source' => $event->source
        ]);
        
        switch ($event->source) {
            case 'form':
                Log::debug('📝 Handling form duplicate', ['email' => $event->email]);
                $this->handleFormDuplicate($event);
                break;
            case 'csv':
                Log::debug('📄 Handling CSV duplicate', ['email' => $event->email, 'row' => $event->csvRow]);
                $this->handleCsvDuplicate($event);
                break;
            case 'api':
                Log::debug('🔌 Handling API duplicate', ['email' => $event->email]);
                $this->handleApiDuplicate($event);
                break;
        }

        // Store duplicate attempt for analysis
        Log::debug('💾 Storing duplicate attempt for analysis', [
            'listener' => 'HandleDuplicateEmail',
            'email' => $event->email
        ]);
        $this->storeDuplicateAttempt($event);
        
        Log::debug('✅ DuplicateEmailDetected event handled successfully', [
            'listener' => 'HandleDuplicateEmail',
            'email' => $event->email,
            'source' => $event->source
        ]);
    }

    /**
     * Update duplicate statistics
     */
    private function updateDuplicateStats(DuplicateEmailDetected $event): void
    {
        try {
            // Daily duplicate counter
            $dailyKey = "duplicate_emails_daily_" . now()->format('Y-m-d');
            Cache::increment($dailyKey, 1);
            Cache::put($dailyKey, Cache::get($dailyKey, 0), now()->addDays(30));

            // Source-specific counters
            $sourceKey = "duplicate_emails_source_{$event->source}";
            Cache::increment($sourceKey, 1);

            // Email-specific counter (to track repeat offenders)
            $emailHash = md5(strtolower($event->email));
            $emailKey = "duplicate_attempts_email_{$emailHash}";
            Cache::increment($emailKey, 1);
            Cache::put($emailKey, Cache::get($emailKey, 0), now()->addDays(7));

            // Track most duplicated emails
            $topDuplicatesKey = "top_duplicate_emails";
            $topDuplicates = Cache::get($topDuplicatesKey, []);
            
            if (!isset($topDuplicates[$event->email])) {
                $topDuplicates[$event->email] = 0;
            }
            $topDuplicates[$event->email]++;
            
            // Keep only top 100 most duplicated emails
            arsort($topDuplicates);
            $topDuplicates = array_slice($topDuplicates, 0, 100, true);
            
            Cache::put($topDuplicatesKey, $topDuplicates, now()->addDays(30));

        } catch (\Exception $e) {
            Log::warning('Failed to update duplicate email stats', [
                'error' => $e->getMessage(),
                'email' => $event->email
            ]);
        }
    }

    /**
     * Handle form submission duplicates
     */
    private function handleFormDuplicate(DuplicateEmailDetected $event): void
    {
        Log::debug('Form duplicate detected', [
            'email' => $event->email,
            'existing_id' => $event->existingSubmissionId
        ]);

        // You can add form-specific logic here:
        // - Show user-friendly error messages
        // - Suggest the user to update instead of create
        // - Offer to resend confirmation emails
    }

    /**
     * Handle CSV upload duplicates
     */
    private function handleCsvDuplicate(DuplicateEmailDetected $event): void
    {
        Log::debug('CSV duplicate detected', [
            'email' => $event->email,
            'csv_row' => $event->csvRow,
            'existing_id' => $event->existingSubmissionId
        ]);

        // CSV-specific logic:
        // - Track problematic CSV files
        // - Generate detailed error reports
        // - Suggest data cleanup
    }

    /**
     * Handle API duplicates
     */
    private function handleApiDuplicate(DuplicateEmailDetected $event): void
    {
        Log::debug('API duplicate detected', [
            'email' => $event->email,
            'existing_id' => $event->existingSubmissionId
        ]);

        // API-specific logic:
        // - Return proper HTTP status codes
        // - Provide alternative suggestions
        // - Track API client behavior
    }

    /**
     * Store duplicate attempt for analysis
     */
    private function storeDuplicateAttempt(DuplicateEmailDetected $event): void
    {
        try {
            $attemptKey = "duplicate_attempt_" . now()->format('Y-m-d-H-i-s') . "_" . uniqid();
            
            $attemptData = [
                'email' => $event->email,
                'source' => $event->source,
                'existing_submission_id' => $event->existingSubmissionId,
                'csv_row' => $event->csvRow,
                'attempted_data' => $event->attemptedData,
                'detected_at' => now()->toDateTimeString(),
                'ip_address' => request()->ip() ?? null,
                'user_agent' => request()->userAgent() ?? null
            ];

            // Store for 7 days for analysis
            Cache::put($attemptKey, $attemptData, now()->addDays(7));

        } catch (\Exception $e) {
            Log::warning('Failed to store duplicate attempt data', [
                'error' => $e->getMessage(),
                'email' => $event->email
            ]);
        }
    }
}