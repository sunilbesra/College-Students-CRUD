<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MonitorEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:monitor 
                            {--filter= : Filter by event type (form|csv|duplicate|all)}
                            {--tail=50 : Number of lines to show from the end}
                            {--follow : Follow log file in real-time}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor form submission and CSV upload events in real-time from logs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filter = $this->option('filter') ?? 'all';
        $tailLines = $this->option('tail') ?? 50;
        $follow = $this->option('follow');

        $this->info("🔍 Event Monitor Started - Filter: {$filter}");
        $this->info('=====================================');

        $logFile = storage_path('logs/laravel.log');

        if (!file_exists($logFile)) {
            $this->error("Log file not found: {$logFile}");
            return 1;
        }

        if ($follow) {
            $this->followLogs($logFile, $filter);
        } else {
            $this->showRecentLogs($logFile, $filter, $tailLines);
        }

        return 0;
    }

    /**
     * Show recent log entries
     */
    private function showRecentLogs(string $logFile, string $filter, int $tailLines): void
    {
        $this->info("📜 Showing last {$tailLines} event entries...\n");

        $command = "tail -{$tailLines} \"{$logFile}\"";
        $lines = [];
        exec($command, $lines);

        $filteredLines = $this->filterEventLines($lines, $filter);

        if (empty($filteredLines)) {
            $this->warn("No events found matching filter: {$filter}");
            return;
        }

        foreach ($filteredLines as $line) {
            $this->displayLogLine($line);
        }

        $this->newLine();
        $this->info("📊 Total events shown: " . count($filteredLines));
        $this->line("💡 Use --follow to monitor events in real-time");
    }

    /**
     * Follow logs in real-time
     */
    private function followLogs(string $logFile, string $filter): void
    {
        $this->info("👀 Following events in real-time... (Press Ctrl+C to stop)\n");

        $handle = popen("tail -f \"{$logFile}\"", 'r');
        
        if (!$handle) {
            $this->error("Could not open log file for following");
            return;
        }

        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line === false) continue;

            $line = trim($line);
            if (empty($line)) continue;

            if ($this->shouldShowLine($line, $filter)) {
                $this->displayLogLine($line);
            }
        }

        pclose($handle);
    }

    /**
     * Filter log lines based on event type
     */
    private function filterEventLines(array $lines, string $filter): array
    {
        return array_filter($lines, function ($line) use ($filter) {
            return $this->shouldShowLine($line, $filter);
        });
    }

    /**
     * Check if line should be shown based on filter
     */
    private function shouldShowLine(string $line, string $filter): bool
    {
        // Look for event-related log entries
        $eventPatterns = [
            'FIRING EVENT:',
            'EVENT LISTENER:',
            'FormSubmissionCreated',
            'FormSubmissionProcessed',
            'CsvUploadStarted',
            'CsvUploadCompleted',
            'DuplicateEmailDetected'
        ];

        $hasEventPattern = false;
        foreach ($eventPatterns as $pattern) {
            if (strpos($line, $pattern) !== false) {
                $hasEventPattern = true;
                break;
            }
        }

        if (!$hasEventPattern) {
            return false;
        }

        // Apply specific filter
        switch ($filter) {
            case 'form':
                return strpos($line, 'FormSubmission') !== false;
                
            case 'csv':
                return strpos($line, 'CsvUpload') !== false;
                
            case 'duplicate':
                return strpos($line, 'DuplicateEmail') !== false;
                
            case 'all':
            default:
                return true;
        }
    }

    /**
     * Display a formatted log line
     */
    private function displayLogLine(string $line): void
    {
        $timestamp = $this->extractTimestamp($line);
        $level = $this->extractLogLevel($line);
        $message = $this->extractMessage($line);

        // Color coding based on log level and event type
        $color = $this->getColorForLevel($level);
        $icon = $this->getIconForEvent($line);

        $formattedTime = $timestamp ? date('H:i:s', strtotime($timestamp)) : '??:??:??';
        
        $this->line("<fg={$color}>[{$formattedTime}] {$icon} {$message}</>");
    }

    /**
     * Extract timestamp from log line
     */
    private function extractTimestamp(string $line): ?string
    {
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extract log level from log line
     */
    private function extractLogLevel(string $line): string
    {
        if (preg_match('/\] local\.(\w+):/', $line, $matches)) {
            return strtoupper($matches[1]);
        }
        return 'INFO';
    }

    /**
     * Extract message from log line
     */
    private function extractMessage(string $line): string
    {
        // Try to extract the main message part
        if (strpos($line, 'FIRING EVENT:') !== false) {
            if (preg_match('/FIRING EVENT: (\w+)/', $line, $matches)) {
                return "🔥 FIRING: {$matches[1]}";
            }
        }
        
        if (strpos($line, 'EVENT LISTENER:') !== false) {
            if (preg_match('/EVENT LISTENER: (\w+) triggered/', $line, $matches)) {
                return "👂 LISTENER: {$matches[1]}";
            }
        }

        // Extract the part after the log level
        if (preg_match('/\] local\.\w+: (.+)/', $line, $matches)) {
            $message = $matches[1];
            // Truncate very long messages
            if (strlen($message) > 100) {
                $message = substr($message, 0, 97) . '...';
            }
            return $message;
        }

        return substr($line, 0, 100);
    }

    /**
     * Get color for log level
     */
    private function getColorForLevel(string $level): string
    {
        switch ($level) {
            case 'ERROR':
                return 'red';
            case 'WARNING':
                return 'yellow';
            case 'INFO':
                return 'green';
            case 'DEBUG':
                return 'blue';
            default:
                return 'white';
        }
    }

    /**
     * Get icon for event type
     */
    private function getIconForEvent(string $line): string
    {
        if (strpos($line, 'FormSubmissionCreated') !== false) {
            return '📝';
        }
        if (strpos($line, 'FormSubmissionProcessed') !== false) {
            return '⚡';
        }
        if (strpos($line, 'CsvUploadStarted') !== false) {
            return '📤';
        }
        if (strpos($line, 'CsvUploadCompleted') !== false) {
            return '✅';
        }
        if (strpos($line, 'DuplicateEmailDetected') !== false) {
            return '🔄';
        }
        if (strpos($line, 'FIRING EVENT') !== false) {
            return '🚀';
        }
        if (strpos($line, 'EVENT LISTENER') !== false) {
            return '🎯';
        }
        return '📋';
    }
}
