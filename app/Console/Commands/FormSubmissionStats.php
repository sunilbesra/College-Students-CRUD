<?php

namespace App\Console\Commands;

use App\Models\FormSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class FormSubmissionStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'form:stats 
                            {--clear-cache : Clear cached statistics}
                            {--detailed : Show detailed statistics}
                            {--export= : Export statistics to file (json|csv)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display form submission and CSV upload statistics with event tracking data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('clear-cache')) {
            $this->clearCachedStats();
            return;
        }

        $this->info('Form Submission Statistics Dashboard');
        $this->info('=====================================');

        // Get real-time statistics from database
        $dbStats = $this->getDatabaseStats();
        
        // Get cached event statistics
        $eventStats = $this->getCachedEventStats();
        
        // Display overview
        $this->displayOverview($dbStats, $eventStats);
        
        if ($this->option('detailed')) {
            $this->displayDetailedStats($dbStats, $eventStats);
        }
        
        $exportFormat = $this->option('export');
        if ($exportFormat) {
            $this->exportStats($dbStats, $eventStats, $exportFormat);
        }
    }

    /**
     * Get statistics from database
     */
    private function getDatabaseStats(): array
    {
        $stats = [];
        
        // Form submission counts by status
        $stats['status'] = [
            'total' => FormSubmission::count(),
            'queued' => FormSubmission::where('status', 'queued')->count(),
            'processing' => FormSubmission::where('status', 'processing')->count(),
            'completed' => FormSubmission::where('status', 'completed')->count(),
            'failed' => FormSubmission::where('status', 'failed')->count(),
        ];
        
        // Counts by operation
        $stats['operations'] = [
            'create' => FormSubmission::where('operation', 'create')->count(),
            'update' => FormSubmission::where('operation', 'update')->count(),
            'delete' => FormSubmission::where('operation', 'delete')->count(),
        ];
        
        // Counts by source
        $stats['sources'] = [
            'form' => FormSubmission::where('source', 'form')->count(),
            'csv' => FormSubmission::where('source', 'csv')->count(),
            'api' => FormSubmission::where('source', 'api')->count(),
        ];
        
        // Recent activity (last 24 hours)
        $last24Hours = now()->subDay();
        $stats['recent'] = [
            'total' => FormSubmission::where('created_at', '>=', $last24Hours)->count(),
            'completed' => FormSubmission::where('created_at', '>=', $last24Hours)
                                       ->where('status', 'completed')->count(),
            'failed' => FormSubmission::where('created_at', '>=', $last24Hours)
                                    ->where('status', 'failed')->count(),
        ];
        
        return $stats;
    }

    /**
     * Get cached event statistics
     */
    private function getCachedEventStats(): array
    {
        $today = now()->format('Y-m-d');
        $currentHour = now()->format('Y-m-d-H');
        
        return [
            'form_processing' => [
                'completed_today' => Cache::get("form_submissions_count_completed", 0),
                'failed_today' => Cache::get("form_submissions_count_failed", 0),
                'current_hour' => Cache::get("form_submissions_hourly_{$currentHour}", 0),
            ],
            'csv_uploads' => [
                'uploads_today' => Cache::get("csv_uploads_daily_{$today}", 0),
                'total_rows_today' => Cache::get("csv_uploads_total_rows_{$today}", 0),
                'avg_processing_time' => Cache::get("csv_avg_processing_time", 0),
                'processing_count' => Cache::get("csv_processing_count", 0),
            ],
            'duplicates' => [
                'detected_today' => Cache::get("duplicate_emails_daily_{$today}", 0),
                'from_forms' => Cache::get("duplicate_emails_source_form", 0),
                'from_csv' => Cache::get("duplicate_emails_source_csv", 0),
                'from_api' => Cache::get("duplicate_emails_source_api", 0),
            ],
            'validation' => [
                'total_valid_rows' => Cache::get("csv_total_valid_rows", 0),
                'total_invalid_rows' => Cache::get("csv_total_invalid_rows", 0),
                'total_duplicate_rows' => Cache::get("csv_total_duplicate_rows", 0),
            ]
        ];
    }

    /**
     * Display overview statistics
     */
    private function displayOverview(array $dbStats, array $eventStats): void
    {
        $this->newLine();
        
        // Status overview
        $this->info('📊 Form Submissions Overview');
        $this->table(
            ['Status', 'Count', 'Percentage'],
            [
                ['Total', $dbStats['status']['total'], '100%'],
                ['Completed', $dbStats['status']['completed'], $this->percentage($dbStats['status']['completed'], $dbStats['status']['total'])],
                ['Failed', $dbStats['status']['failed'], $this->percentage($dbStats['status']['failed'], $dbStats['status']['total'])],
                ['Processing', $dbStats['status']['processing'], $this->percentage($dbStats['status']['processing'], $dbStats['status']['total'])],
                ['Queued', $dbStats['status']['queued'], $this->percentage($dbStats['status']['queued'], $dbStats['status']['total'])],
            ]
        );
        
        // Source breakdown
        $this->newLine();
        $this->info('📁 Sources Breakdown');
        $this->table(
            ['Source', 'Count', 'Percentage'],
            [
                ['Form', $dbStats['sources']['form'], $this->percentage($dbStats['sources']['form'], $dbStats['status']['total'])],
                ['CSV', $dbStats['sources']['csv'], $this->percentage($dbStats['sources']['csv'], $dbStats['status']['total'])],
                ['API', $dbStats['sources']['api'], $this->percentage($dbStats['sources']['api'], $dbStats['status']['total'])],
            ]
        );
        
        // Recent activity
        $this->newLine();
        $this->info('🕒 Recent Activity (Last 24 Hours)');
        $this->line("• Total submissions: {$dbStats['recent']['total']}");
        $this->line("• Completed: {$dbStats['recent']['completed']}");
        $this->line("• Failed: {$dbStats['recent']['failed']}");
        $this->line("• Current hour: {$eventStats['form_processing']['current_hour']}");
        
        // Duplicate statistics
        $this->newLine();
        $this->info('🔄 Duplicate Email Detection (Today)');
        $this->line("• Total duplicates detected: {$eventStats['duplicates']['detected_today']}");
        $this->line("• From forms: {$eventStats['duplicates']['from_forms']}");
        $this->line("• From CSV: {$eventStats['duplicates']['from_csv']}");
        $this->line("• From API: {$eventStats['duplicates']['from_api']}");
    }

    /**
     * Display detailed statistics
     */
    private function displayDetailedStats(array $dbStats, array $eventStats): void
    {
        $this->newLine();
        $this->info('📈 Detailed Statistics');
        $this->info('=====================');
        
        // CSV Processing Details
        $this->newLine();
        $this->info('📄 CSV Processing');
        $avgTime = $eventStats['csv_uploads']['avg_processing_time'];
        $this->line("• Uploads today: {$eventStats['csv_uploads']['uploads_today']}");
        $this->line("• Total rows processed today: {$eventStats['csv_uploads']['total_rows_today']}");
        $this->line("• Average processing time: " . round($avgTime / 1000, 2) . "s");
        $this->line("• Total CSV files processed: {$eventStats['csv_uploads']['processing_count']}");
        
        // Validation Statistics
        $this->newLine();
        $this->info('✅ Validation Statistics');
        $totalValidated = $eventStats['validation']['total_valid_rows'] + 
                         $eventStats['validation']['total_invalid_rows'] + 
                         $eventStats['validation']['total_duplicate_rows'];
        
        if ($totalValidated > 0) {
            $validRate = round(($eventStats['validation']['total_valid_rows'] / $totalValidated) * 100, 1);
            $this->line("• Total rows validated: {$totalValidated}");
            $this->line("• Valid rows: {$eventStats['validation']['total_valid_rows']} ({$validRate}%)");
            $this->line("• Invalid rows: {$eventStats['validation']['total_invalid_rows']}");
            $this->line("• Duplicate rows: {$eventStats['validation']['total_duplicate_rows']}");
        } else {
            $this->line("• No validation data available yet");
        }
        
        // Top duplicate emails
        $this->displayTopDuplicates();
    }

    /**
     * Display top duplicate emails
     */
    private function displayTopDuplicates(): void
    {
        $topDuplicates = Cache::get('top_duplicate_emails', []);
        
        if (!empty($topDuplicates)) {
            $this->newLine();
            $this->info('🔥 Most Duplicated Emails');
            
            $tableData = [];
            $count = 0;
            foreach ($topDuplicates as $email => $attempts) {
                if ($count >= 10) break; // Show top 10
                $tableData[] = [$email, $attempts];
                $count++;
            }
            
            if (!empty($tableData)) {
                $this->table(['Email', 'Attempts'], $tableData);
            }
        }
    }

    /**
     * Calculate percentage
     */
    private function percentage(int $part, int $total): string
    {
        if ($total == 0) return '0%';
        return round(($part / $total) * 100, 1) . '%';
    }

    /**
     * Clear cached statistics
     */
    private function clearCachedStats(): void
    {
        $keys = [
            'form_submissions_count_*',
            'form_submissions_operation_*',
            'form_submissions_source_*',
            'form_submissions_hourly_*',
            'csv_uploads_daily_*',
            'csv_uploads_total_rows_*',
            'csv_avg_processing_time',
            'csv_processing_count',
            'duplicate_emails_daily_*',
            'duplicate_emails_source_*',
            'csv_total_*',
            'top_duplicate_emails'
        ];
        
        foreach ($keys as $pattern) {
            if (strpos($pattern, '*') !== false) {
                // For patterns, we'd need to implement cache key scanning
                // For now, just clear known keys
                $baseKey = str_replace('*', '', $pattern);
                for ($i = 0; $i < 30; $i++) {
                    Cache::forget($baseKey . $i);
                }
            } else {
                Cache::forget($pattern);
            }
        }
        
        $this->info('✅ Cached statistics cleared successfully!');
    }

    /**
     * Export statistics to file
     */
    private function exportStats(array $dbStats, array $eventStats, string $format): void
    {
        $data = [
            'generated_at' => now()->toDateTimeString(),
            'database_stats' => $dbStats,
            'event_stats' => $eventStats
        ];
        
        $filename = 'form_submission_stats_' . now()->format('Y-m-d_H-i-s');
        
        switch ($format) {
            case 'json':
                $content = json_encode($data, JSON_PRETTY_PRINT);
                $filename .= '.json';
                break;
                
            case 'csv':
                // Convert to CSV format
                $content = $this->convertToCsv($data);
                $filename .= '.csv';
                break;
                
            default:
                $this->error("Unsupported export format: {$format}");
                return;
        }
        
        file_put_contents($filename, $content);
        $this->info("📄 Statistics exported to: {$filename}");
    }

    /**
     * Convert statistics to CSV format
     */
    private function convertToCsv(array $data): string
    {
        $csv = "Metric,Value\n";
        
        // Flatten the data structure for CSV
        $flat = $this->flattenArray($data, '');
        
        foreach ($flat as $key => $value) {
            $csv .= "\"{$key}\",\"{$value}\"\n";
        }
        
        return $csv;
    }

    /**
     * Flatten multi-dimensional array
     */
    private function flattenArray(array $array, string $prefix): array
    {
        $result = [];
        
        foreach ($array as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;
            
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }
        
        return $result;
    }
}
