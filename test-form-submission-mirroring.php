<?php

require_once 'vendor/autoload.php';

use Pheanstalk\Pheanstalk;

echo "🧪 Testing Form Submission Mirroring to Beanstalkd\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// Test Beanstalkd connection
$host = '127.0.0.1';
$port = 11300;
$tube = 'form_submission_json';

try {
    $pheanstalk = Pheanstalk::create($host, $port);
    
    echo "✅ Connected to Beanstalkd at {$host}:{$port}\n";
    
    // Check tube stats before
    $statsBefore = $pheanstalk->statsTube($tube);
    echo "📊 Tube '{$tube}' stats before:\n";
    echo "   - Current jobs ready: " . $statsBefore->current_jobs_ready . "\n";
    echo "   - Total jobs: " . $statsBefore->total_jobs . "\n\n";
    
    echo "🔄 Now submit a form or upload CSV to test mirroring...\n\n";
    
    // Wait and check again after 5 seconds
    sleep(5);
    
    $statsAfter = $pheanstalk->statsTube($tube);
    echo "📊 Tube '{$tube}' stats after waiting:\n";
    echo "   - Current jobs ready: " . $statsAfter->current_jobs_ready . "\n";
    echo "   - Total jobs: " . $statsAfter->total_jobs . "\n\n";
    
    if ($statsAfter->current_jobs_ready > $statsBefore->current_jobs_ready) {
        echo "✅ NEW JOBS DETECTED! Mirroring is working!\n";
        
        // Peek at the latest job
        $job = $pheanstalk->useTube($tube)->peekReady();
        if ($job) {
            $payload = json_decode($job->getData(), true);
            echo "📦 Latest job payload:\n";
            echo "   - Action: " . ($payload['action'] ?? 'unknown') . "\n";
            echo "   - Source: " . ($payload['source'] ?? 'unknown') . "\n";
            echo "   - Mirror ID: " . ($payload['mirror_id'] ?? 'unknown') . "\n";
            echo "   - Queued at: " . ($payload['queued_at'] ?? 'unknown') . "\n";
            echo "   - Payload size: " . strlen($job->getData()) . " bytes\n";
        }
    } else {
        echo "⚠️  No new jobs detected. Please submit a form or upload CSV.\n";
    }
    
} catch (\Pheanstalk\Exception\ConnectionException $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Test completed. Check Laravel logs for detailed mirroring info.\n";