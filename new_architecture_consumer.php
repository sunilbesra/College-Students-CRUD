#!/usr/bin/env php
<?php

/**
 * Beanstalkd Form Submission Mirror Consumer - New Architecture
 * 
 * This script consumes JSON data from Beanstalkd tube and handles:
 * - Form submissions stored FIRST in tube
 * - CSV batch uploads stored FIRST in tube  
 * - Duplicate email validation via Beanstalk consumer
 * - Real-time processing feedback
 */

require_once __DIR__ . '/vendor/autoload.php';

use Pheanstalk\Pheanstalk;

echo "🔄 Form Submission Mirror Consumer - New Architecture\n";
echo "=" . str_repeat("=", 55) . "\n";
echo "📋 Features:\n";
echo "   • Processes mirrored JSON data from Beanstalk tube\n";
echo "   • Handles duplicate email validation\n";
echo "   • Shows validation results in real-time\n";
echo "=" . str_repeat("=", 55) . "\n\n";

$host = '127.0.0.1';
$port = 11300;
$tube = 'form_submission_json';

try {
    $pheanstalk = Pheanstalk::create($host, $port);
    $pheanstalk->watch($tube);
    $pheanstalk->ignore('default');
    
    echo "✅ Connected to Beanstalkd at {$host}:{$port}\n";
    echo "👁️  Watching tube: {$tube}\n\n";
    
    $processedCount = 0;
    
    while (true) {
        try {
            echo "⏳ Waiting for jobs in tube '{$tube}'...\n";
            
            // Reserve a job (blocks until available)
            $job = $pheanstalk->reserve();
            
            if ($job) {
                $processedCount++;
                echo "\n📦 Processing job #{$processedCount} (ID: {$job->getId()})\n";
                echo "   ⏰ " . date('Y-m-d H:i:s') . "\n";
                
                // Parse job data
                $payload = json_decode($job->getData(), true);
                
                if ($payload) {
                    process_mirror_job($payload, $job->getId());
                    
                    // Delete the job after processing
                    $pheanstalk->delete($job);
                    echo "   ✅ Job {$job->getId()} completed and deleted\n";
                    echo "   " . str_repeat("-", 50) . "\n";
                } else {
                    echo "   ❌ Invalid JSON payload in job {$job->getId()}\n";
                    $pheanstalk->bury($job);
                }
            }
            
        } catch (\Pheanstalk\Exception\ServerException $e) {
            echo "⚠️  Server error: " . $e->getMessage() . "\n";
            sleep(1);
        }
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

function process_mirror_job(array $payload, int $jobId): void 
{
    $action = $payload['action'] ?? 'unknown';
    $source = $payload['source'] ?? 'unknown';
    $mirrorId = $payload['mirror_id'] ?? 'unknown';
    
    echo "   🎯 Action: {$action}\n";
    echo "   📍 Source: {$source}\n";
    echo "   🆔 Mirror ID: {$mirrorId}\n";
    echo "   📦 Payload size: " . strlen(json_encode($payload)) . " bytes\n";
    
    switch ($action) {
        case 'form_submission_created':
            handle_form_submission($payload['data'] ?? []);
            break;
            
        case 'csv_batch_uploaded':
            handle_csv_batch($payload['data'] ?? []);
            break;
            
        case 'form_submission_processed':
            handle_processed_submission($payload['data'] ?? []);
            break;
            
        case 'csv_row_processed':
            handle_processed_csv_row($payload['data'] ?? []);
            break;
            
        case 'form_submission_requeued':
            handle_requeue_event($payload['data'] ?? []);
            break;
            
        case 'form_submission_deleted':
            handle_deletion_event($payload['data'] ?? []);
            break;
            
        case 'tube_creation_test':
            handle_test_event($payload);
            break;
            
        default:
            echo "   ⚠️  Unknown action: {$action}\n";
            break;
    }
}

function handle_form_submission(array $data): void
{
    echo "   📝 Processing NEW form submission (pre-validation)...\n";
    
    $operation = $data['operation'] ?? 'unknown';
    $source = $data['source'] ?? 'unknown';
    $email = $data['data']['email'] ?? 'N/A';
    $name = $data['data']['name'] ?? 'N/A';
    $phone = $data['data']['phone'] ?? 'N/A';
    $gender = $data['data']['gender'] ?? 'N/A';
    
    echo "      🏷️  Operation: {$operation}\n";
    echo "      📍 Source: {$source}\n";
    echo "      👤 Name: {$name}\n";
    echo "      📧 Email: {$email}\n";
    echo "      📱 Phone: {$phone}\n";
    echo "      ⚧  Gender: {$gender}\n";
    
    // Simulate duplicate validation (in real implementation, check MongoDB)
    $isDuplicate = simulate_duplicate_check($email);
    
    if ($isDuplicate) {
        echo "      ⚠️  DUPLICATE DETECTED: Email '{$email}' already exists\n";
        echo "      🚫 This submission would be rejected by validation\n";
    } else {
        echo "      ✅ VALIDATION PASSED: Email is unique, ready for processing\n";
        echo "      💾 This submission would be stored in MongoDB\n";
    }
}

function handle_csv_batch(array $data): void
{
    echo "   📊 Processing NEW CSV batch (pre-validation)...\n";
    
    $operation = $data['operation'] ?? 'unknown';
    $csvFile = $data['csv_file'] ?? 'unknown';
    $totalRows = $data['total_rows'] ?? 0;
    $batchData = $data['batch_data'] ?? [];
    
    echo "      🏷️  Operation: {$operation}\n";
    echo "      📄 CSV File: {$csvFile}\n";
    echo "      📊 Total Rows: {$totalRows}\n";
    echo "      📦 Batch Items: " . count($batchData) . "\n";
    
    $duplicates = 0;
    $valid = 0;
    
    foreach ($batchData as $index => $rowData) {
        $csvRow = $rowData['csv_row'] ?? ($index + 1);
        $email = $rowData['data']['email'] ?? 'N/A';
        $name = $rowData['data']['name'] ?? 'N/A';
        
        $isDuplicate = simulate_duplicate_check($email);
        
        if ($isDuplicate) {
            echo "        📍 Row {$csvRow}: {$name} ({$email}) - ⚠️  DUPLICATE\n";
            $duplicates++;
        } else {
            echo "        📍 Row {$csvRow}: {$name} ({$email}) - ✅ VALID\n";
            $valid++;
        }
    }
    
    echo "      📈 Validation Summary:\n";
    echo "        ✅ Valid rows: {$valid}\n";
    echo "        ⚠️  Duplicate rows: {$duplicates}\n";
    echo "      💾 {$valid} rows would be stored in MongoDB\n";
}

function handle_processed_submission(array $data): void
{
    echo "   ✅ Processing COMPLETED form submission...\n";
    
    $submissionId = $data['submission_id'] ?? 'unknown';
    $operation = $data['operation'] ?? 'unknown';
    $source = $data['source'] ?? 'unknown';
    $status = $data['status'] ?? 'unknown';
    $email = $data['data']['email'] ?? 'N/A';
    
    echo "      🆔 Submission ID: {$submissionId}\n";
    echo "      🏷️  Operation: {$operation}\n";
    echo "      📍 Source: {$source}\n";
    echo "      📧 Email: {$email}\n";
    echo "      🎯 Status: {$status}\n";
    echo "      💾 Submission has been processed and stored\n";
}

function handle_processed_csv_row(array $data): void
{
    echo "   ✅ Processing COMPLETED CSV row...\n";
    
    $submissionId = $data['submission_id'] ?? 'unknown';
    $csvRow = $data['csv_row'] ?? 'unknown';
    $csvFile = $data['csv_file'] ?? 'unknown';
    $email = $data['data']['email'] ?? 'N/A';
    $status = $data['status'] ?? 'unknown';
    
    echo "      🆔 Submission ID: {$submissionId}\n";
    echo "      📍 CSV Row: {$csvRow}\n";
    echo "      📄 CSV File: {$csvFile}\n";
    echo "      📧 Email: {$email}\n";
    echo "      🎯 Status: {$status}\n";
    echo "      💾 CSV row has been processed and stored\n";
}

function handle_requeue_event(array $data): void
{
    echo "   🔄 Processing requeue event...\n";
    $submissionId = $data['id'] ?? 'unknown';
    echo "      🆔 Submission ID: {$submissionId}\n";
    echo "      🔄 Submission requeued for reprocessing\n";
}

function handle_deletion_event(array $data): void
{
    echo "   🗑️  Processing deletion event...\n";
    $submissionId = $data['id'] ?? 'unknown';
    echo "      🆔 Submission ID: {$submissionId}\n";
    echo "      🗑️  Submission marked for deletion\n";
}

function handle_test_event(array $payload): void
{
    echo "   🧪 Processing test event...\n";
    $message = $payload['message'] ?? 'No message';
    echo "      📝 Message: {$message}\n";
    echo "      🧪 Test event processed successfully\n";
}

function simulate_duplicate_check(string $email): bool
{
    // Simulate duplicate checking (in real implementation, query MongoDB)
    // For demo purposes, consider emails with 'duplicate' or 'test' as duplicates
    $duplicateEmails = ['test@example.com', 'duplicate@test.com', 'existing@example.com'];
    
    return in_array(strtolower($email), $duplicateEmails);
}