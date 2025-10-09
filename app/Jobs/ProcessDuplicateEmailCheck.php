<?php

namespace App\Jobs;

use App\Models\FormSubmission;
use App\Events\DuplicateEmailDetected;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ProcessDuplicateEmailCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;

    protected $email;
    protected $source;
    protected $submissionData;
    protected $existingSubmissionId;
    protected $csvRow;
    protected $operation;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $email,
        string $source,
        array $submissionData,
        ?string $existingSubmissionId = null,
        ?int $csvRow = null,
        string $operation = 'create'
    ) {
        $this->email = $email;
        $this->source = $source;
        $this->submissionData = $submissionData;
        $this->existingSubmissionId = $existingSubmissionId;
        $this->csvRow = $csvRow;
        $this->operation = $operation;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('🚀 Processing duplicate email check asynchronously', [
            'job' => 'ProcessDuplicateEmailCheck',
            'email' => $this->email,
            'source' => $this->source,
            'csv_row' => $this->csvRow,
            'operation' => $this->operation
        ]);

        try {
            // Step 1: Perform comprehensive duplicate check
            $duplicateCheckResult = $this->performDuplicateCheck();

            if ($duplicateCheckResult['is_duplicate']) {
                Log::warning('📧 Duplicate email detected in async job', [
                    'email' => $this->email,
                    'source' => $this->source,
                    'existing_submission_id' => $duplicateCheckResult['existing_submission_id'],
                    'csv_row' => $this->csvRow
                ]);

                // Step 2: Fire the DuplicateEmailDetected event
                $this->fireDuplicateEvent($duplicateCheckResult);

                // Step 3: Create notification asynchronously
                $this->createDuplicateNotification($duplicateCheckResult);

                // Step 4: Update duplicate statistics
                $this->updateDuplicateStatistics($duplicateCheckResult);

                // Step 5: Handle source-specific logic
                $this->handleSourceSpecificLogic($duplicateCheckResult);

            } else {
                Log::info('✅ No duplicate found in async check', [
                    'email' => $this->email,
                    'source' => $this->source
                ]);
            }

        } catch (\Exception $e) {
            Log::error('❌ Error in duplicate email check job', [
                'job' => 'ProcessDuplicateEmailCheck',
                'email' => $this->email,
                'source' => $this->source,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Perform comprehensive duplicate check
     */
    private function performDuplicateCheck(): array
    {
        Log::debug('🔍 Performing comprehensive duplicate check', [
            'email' => $this->email,
            'operation' => $this->operation
        ]);

        $result = [
            'is_duplicate' => false,
            'existing_submission_id' => null,
            'existing_submission' => null,
            'duplicate_count' => 0,
            'check_performed_at' => now()
        ];

        // Check for existing submissions with same email
        $existingSubmissions = FormSubmission::where('data.email', $this->email)
            ->where('status', 'completed')
            ->get();

        if ($existingSubmissions->isNotEmpty()) {
            $result['is_duplicate'] = true;
            $result['existing_submission_id'] = $existingSubmissions->first()->_id;
            $result['existing_submission'] = $existingSubmissions->first();
            $result['duplicate_count'] = $existingSubmissions->count();

            Log::debug('🔍 Duplicate check results', [
                'email' => $this->email,
                'duplicate_count' => $result['duplicate_count'],
                'existing_submission_id' => $result['existing_submission_id']
            ]);
        }

        return $result;
    }

    /**
     * Fire the DuplicateEmailDetected event
     */
    private function fireDuplicateEvent(array $duplicateCheckResult): void
    {
        Log::debug('🔥 Firing DuplicateEmailDetected event from async job', [
            'email' => $this->email,
            'source' => $this->source
        ]);

        event(new DuplicateEmailDetected(
            $this->email,
            $this->source,
            $duplicateCheckResult['existing_submission_id'],
            $this->submissionData,
            $this->csvRow
        ));

        Log::debug('✅ DuplicateEmailDetected event fired from async job');
    }

    /**
     * Create notification for duplicate email
     */
    private function createDuplicateNotification(array $duplicateCheckResult): void
    {
        try {
            Log::debug('📢 Creating duplicate email notification', [
                'email' => $this->email,
                'source' => $this->source
            ]);

            // Prepare notification data
            $notificationData = [
                'email' => $this->email,
                'source' => $this->source,
                'existing_submission_id' => $duplicateCheckResult['existing_submission_id'],
                'attempted_data' => $this->submissionData,
                'csv_row' => $this->csvRow,
                'duplicate_count' => $duplicateCheckResult['duplicate_count'],
                'detected_at' => now(),
                'operation' => $this->operation
            ];

            // Create notification using NotificationService
            $notification = NotificationService::createFormSubmissionNotification('duplicate', $notificationData);

            Log::info('✅ Duplicate email notification created', [
                'email' => $this->email,
                'notification_id' => $notification->id ?? 'unknown',
                'source' => $this->source
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to create duplicate email notification', [
                'email' => $this->email,
                'source' => $this->source,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update duplicate statistics
     */
    private function updateDuplicateStatistics(array $duplicateCheckResult): void
    {
        try {
            Log::debug('📊 Updating duplicate statistics asynchronously', [
                'email' => $this->email,
                'source' => $this->source
            ]);

            // Daily duplicate counter
            $dailyKey = "duplicate_emails_daily_" . now()->format('Y-m-d');
            Cache::increment($dailyKey, 1);
            Cache::put($dailyKey, Cache::get($dailyKey, 0), now()->addDays(30));

            // Source-specific counters
            $sourceKey = "duplicate_emails_source_{$this->source}";
            Cache::increment($sourceKey, 1);

            // Operation-specific counters
            $operationKey = "duplicate_emails_operation_{$this->operation}";
            Cache::increment($operationKey, 1);

            // Email-specific counter (to track repeat offenders)
            $emailHash = md5(strtolower($this->email));
            $emailKey = "duplicate_attempts_email_{$emailHash}";
            Cache::increment($emailKey, 1);
            Cache::put($emailKey, Cache::get($emailKey, 0), now()->addDays(7));

            // Track most duplicated emails
            $topDuplicatesKey = "top_duplicate_emails";
            $topDuplicates = Cache::get($topDuplicatesKey, []);
            
            if (!isset($topDuplicates[$this->email])) {
                $topDuplicates[$this->email] = 0;
            }
            $topDuplicates[$this->email]++;
            
            // Keep only top 100 most duplicated emails
            arsort($topDuplicates);
            $topDuplicates = array_slice($topDuplicates, 0, 100, true);
            
            Cache::put($topDuplicatesKey, $topDuplicates, now()->addDays(30));

            // Store detailed duplicate attempt data
            $this->storeDuplicateAttemptData($duplicateCheckResult);

        } catch (\Exception $e) {
            Log::error('❌ Failed to update duplicate statistics', [
                'email' => $this->email,
                'source' => $this->source,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle source-specific logic
     */
    private function handleSourceSpecificLogic(array $duplicateCheckResult): void
    {
        Log::debug('🎯 Handling source-specific duplicate logic', [
            'email' => $this->email,
            'source' => $this->source
        ]);

        switch ($this->source) {
            case 'form':
                $this->handleFormDuplicateLogic($duplicateCheckResult);
                break;
            case 'csv':
                $this->handleCsvDuplicateLogic($duplicateCheckResult);
                break;
            case 'api':
                $this->handleApiDuplicateLogic($duplicateCheckResult);
                break;
            default:
                Log::debug('No specific logic for source: ' . $this->source);
        }
    }

    /**
     * Handle form submission duplicate logic
     */
    private function handleFormDuplicateLogic(array $duplicateCheckResult): void
    {
        Log::debug('📝 Processing form duplicate logic', [
            'email' => $this->email,
            'existing_id' => $duplicateCheckResult['existing_submission_id']
        ]);

        // Form-specific async processing:
        // - Could trigger email to user about existing account
        // - Could update user preferences
        // - Could log user behavior patterns
        
        // Store form-specific duplicate data
        Cache::put(
            "form_duplicate_{$this->email}_" . now()->timestamp,
            [
                'email' => $this->email,
                'existing_submission_id' => $duplicateCheckResult['existing_submission_id'],
                'attempted_data' => $this->submissionData,
                'processed_at' => now()
            ],
            now()->addDays(7)
        );
    }

    /**
     * Handle CSV upload duplicate logic
     */
    private function handleCsvDuplicateLogic(array $duplicateCheckResult): void
    {
        Log::debug('📄 Processing CSV duplicate logic', [
            'email' => $this->email,
            'csv_row' => $this->csvRow,
            'existing_id' => $duplicateCheckResult['existing_submission_id']
        ]);

        // CSV-specific async processing:
        // - Track problematic CSV files
        // - Generate detailed error reports
        // - Could trigger admin notifications for high duplicate rates
        
        // Update CSV file statistics
        $csvStatsKey = "csv_duplicate_stats_" . now()->format('Y-m-d');
        $csvStats = Cache::get($csvStatsKey, ['total_rows' => 0, 'duplicate_rows' => 0]);
        $csvStats['duplicate_rows']++;
        Cache::put($csvStatsKey, $csvStats, now()->addDays(30));

        // Store CSV-specific duplicate data
        Cache::put(
            "csv_duplicate_{$this->csvRow}_" . now()->timestamp,
            [
                'email' => $this->email,
                'csv_row' => $this->csvRow,
                'existing_submission_id' => $duplicateCheckResult['existing_submission_id'],
                'attempted_data' => $this->submissionData,
                'processed_at' => now()
            ],
            now()->addDays(7)
        );
    }

    /**
     * Handle API duplicate logic
     */
    private function handleApiDuplicateLogic(array $duplicateCheckResult): void
    {
        Log::debug('🔌 Processing API duplicate logic', [
            'email' => $this->email,
            'existing_id' => $duplicateCheckResult['existing_submission_id']
        ]);

        // API-specific async processing:
        // - Track API client behavior
        // - Could implement rate limiting for repeat offenders
        // - Could trigger webhook notifications to API clients
        
        // Store API-specific duplicate data
        Cache::put(
            "api_duplicate_{$this->email}_" . now()->timestamp,
            [
                'email' => $this->email,
                'existing_submission_id' => $duplicateCheckResult['existing_submission_id'],
                'attempted_data' => $this->submissionData,
                'api_client' => request()->userAgent() ?? 'unknown',
                'ip_address' => request()->ip() ?? 'unknown',
                'processed_at' => now()
            ],
            now()->addDays(7)
        );
    }

    /**
     * Store detailed duplicate attempt data
     */
    private function storeDuplicateAttemptData(array $duplicateCheckResult): void
    {
        try {
            $attemptKey = "duplicate_attempt_" . now()->format('Y-m-d-H-i-s') . "_" . uniqid();
            
            $attemptData = [
                'email' => $this->email,
                'source' => $this->source,
                'operation' => $this->operation,
                'csv_row' => $this->csvRow,
                'existing_submission_id' => $duplicateCheckResult['existing_submission_id'],
                'duplicate_count' => $duplicateCheckResult['duplicate_count'],
                'attempted_data' => $this->submissionData,
                'detected_at' => now()->toDateTimeString(),
                'ip_address' => request()->ip() ?? null,
                'user_agent' => request()->userAgent() ?? null,
                'job_processed_at' => now()->toDateTimeString()
            ];

            // Store for 7 days for analysis
            Cache::put($attemptKey, $attemptData, now()->addDays(7));

            Log::debug('💾 Stored duplicate attempt data', [
                'email' => $this->email,
                'attempt_key' => $attemptKey
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to store duplicate attempt data', [
                'email' => $this->email,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('❌ ProcessDuplicateEmailCheck job failed', [
            'job' => 'ProcessDuplicateEmailCheck',
            'email' => $this->email,
            'source' => $this->source,
            'csv_row' => $this->csvRow,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Could implement fallback logic here:
        // - Send admin notification about failed duplicate check
        // - Store failed attempt for manual review
        // - Trigger alternative duplicate detection mechanism
    }
}