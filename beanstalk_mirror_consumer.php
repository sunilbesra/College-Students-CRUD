#!/usr/bin/env php
<?php

/**
 * Beanstalkd Form Submission Mirror Consumer
 * 
 * This script consumes mirrored form submission events from the Beanstalkd tube
 * and processes them for external systems, analytics, etc.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Pheanstalk\Pheanstalk;

// Configuration
$config = [
    'beanstalkd_host' => '127.0.0.1',
    'beanstalkd_port' => 11300,
    'tube_name' => 'form_submission_json',
    'timeout' => 60, // seconds to wait for jobs
    'max_jobs' => 100, // maximum jobs to process before exit (0 = infinite)
    'verbose' => true
];

function log_message($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] [{$level}] {$message}\n";
}

function process_mirror_job($payload, $jobId) {
    log_message("Processing mirror job #{$jobId}");
    
    try {
        $data = json_decode($payload, true);
        
        if (!$data) {
            throw new Exception("Invalid JSON payload");
        }
        
        $action = $data['action'] ?? 'unknown';
        $submissionId = $data['submission_id'] ?? null;
        $queuedAt = $data['queued_at'] ?? null;
        
        log_message("Action: {$action}, Submission ID: {$submissionId}");
        
        // Process based on action type
        switch ($action) {
            case 'form_submission_requeued':
                log_message("📄 Processing form submission requeue event");
                // Handle requeue logic (e.g., send notification, update external CRM)
                handle_requeue_event($data);
                break;
                
            case 'form_submission_deleted':
                log_message("🗑️ Processing form submission deletion event");
                // Handle deletion logic (e.g., cleanup external systems)
                handle_deletion_event($data);
                break;
                
            default:
                log_message("⚠️ Unknown action: {$action}");
                // Log unknown actions for debugging
                handle_unknown_action($data);
        }
        
        log_message("✅ Successfully processed job #{$jobId}");
        return true;
        
    } catch (Exception $e) {
        log_message("❌ Error processing job #{$jobId}: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

function handle_requeue_event($data) {
    // Example: Send notification about requeued submission
    $submissionId = $data['submission_id'] ?? 'unknown';
    $operation = $data['data']['operation'] ?? 'unknown';
    
    log_message("  → Form submission {$submissionId} was requeued for {$operation} operation");
    
    // Add your custom logic here:
    // - Send email notification
    // - Update external CRM system  
    // - Log to analytics database
    // - Trigger webhook to external service
}

function handle_deletion_event($data) {
    // Example: Clean up external systems when form submission is deleted
    $submissionId = $data['submission_id'] ?? 'unknown';
    $studentId = $data['data']['student_id'] ?? null;
    
    log_message("  → Form submission {$submissionId} was deleted");
    if ($studentId) {
        log_message("  → Associated student ID: {$studentId}");
    }
    
    // Add your custom logic here:
    // - Remove from external CRM
    // - Clean up file attachments
    // - Update analytics counters
    // - Archive data to backup system
}

function handle_unknown_action($data) {
    // Log unknown actions for debugging
    log_message("  → Unknown action data: " . json_encode($data, JSON_PRETTY_PRINT));
}

// Main consumer loop
function run_consumer($config) {
    log_message("🚀 Starting Form Submission Mirror Consumer");
    log_message("Connecting to Beanstalkd at {$config['beanstalkd_host']}:{$config['beanstalkd_port']}");
    
    try {
        $pheanstalk = Pheanstalk::create($config['beanstalkd_host'], $config['beanstalkd_port']);
        log_message("✅ Connected to Beanstalkd successfully");
        
        // Watch the tube
        $pheanstalk->watch($config['tube_name']);
        log_message("👀 Watching tube: {$config['tube_name']}");
        
        $jobCount = 0;
        $maxJobs = $config['max_jobs'];
        
        while ($maxJobs === 0 || $jobCount < $maxJobs) {
            try {
                log_message("⏳ Waiting for jobs (timeout: {$config['timeout']}s)...");
                
                // Reserve a job from the tube
                $job = $pheanstalk->reserve($config['timeout']);
                
                if (!$job) {
                    log_message("⏰ No jobs available, continuing...");
                    continue;
                }
                
                $jobId = $job->getId();
                $payload = $job->getData();
                
                log_message("📥 Reserved job #{$jobId}");
                
                // Process the job
                $success = process_mirror_job($payload, $jobId);
                
                if ($success) {
                    // Job processed successfully, delete it
                    $pheanstalk->delete($job);
                    log_message("🗑️ Job #{$jobId} deleted from queue");
                } else {
                    // Job failed, bury it for manual inspection
                    $pheanstalk->bury($job);
                    log_message("⚰️ Job #{$jobId} buried due to processing error");
                }
                
                $jobCount++;
                
            } catch (\Pheanstalk\Exception\DeadlineSoonException $e) {
                log_message("⏰ Job deadline approaching, releasing job", 'WARN');
                if (isset($job)) {
                    $pheanstalk->release($job);
                }
            }
        }
        
        log_message("🏁 Consumer finished processing {$jobCount} jobs");
        
    } catch (Exception $e) {
        log_message("💥 Fatal error: " . $e->getMessage(), 'FATAL');
        exit(1);
    }
}

// Handle graceful shutdown
function handle_shutdown() {
    log_message("🛑 Received shutdown signal, exiting gracefully...");
    exit(0);
}

// Register signal handlers
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, 'handle_shutdown');
    pcntl_signal(SIGINT, 'handle_shutdown');
}

// Run the consumer
if ($argc > 1) {
    $command = $argv[1];
    
    if ($command === '--help' || $command === '-h') {
        echo "Form Submission Mirror Consumer\n\n";
        echo "Usage: php beanstalk_mirror_consumer.php [options]\n\n";
        echo "Options:\n";
        echo "  --help, -h     Show this help message\n";
        echo "  --stats        Show tube statistics\n";
        echo "  --peek         Peek at ready jobs without consuming\n";
        echo "  --consume      Run the consumer (default)\n\n";
        exit(0);
    }
    
    if ($command === '--stats') {
        $pheanstalk = Pheanstalk::create($config['beanstalkd_host'], $config['beanstalkd_port']);
        $stats = $pheanstalk->statsTube($config['tube_name']);
        
        echo "📊 Tube Statistics for '{$config['tube_name']}':\n";
        foreach ($stats as $key => $value) {
            echo "  {$key}: {$value}\n";
        }
        exit(0);
    }
    
    if ($command === '--peek') {
        $pheanstalk = Pheanstalk::create($config['beanstalkd_host'], $config['beanstalkd_port']);
        
        try {
            $job = $pheanstalk->useTube($config['tube_name'])->peekReady();
            echo "👀 Next ready job:\n";
            echo "  Job ID: " . $job->getId() . "\n";
            echo "  Payload: " . $job->getData() . "\n";
        } catch (Exception $e) {
            echo "❌ No ready jobs found or error: " . $e->getMessage() . "\n";
        }
        exit(0);
    }
}

// Default action: run consumer
run_consumer($config);