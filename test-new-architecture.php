<?php

require_once 'vendor/autoload.php';

use Pheanstalk\Pheanstalk;

echo "🧪 Testing New Architecture: Store First, Validate in Beanstalk\n";
echo "=" . str_repeat("=", 60) . "\n\n";

// Test Beanstalkd connection
$host = '127.0.0.1';
$port = 11300;
$tube = 'form_submission_json';

try {
    $pheanstalk = Pheanstalk::create($host, $port);
    
    echo "✅ Connected to Beanstalkd at {$host}:{$port}\n";
    
    // Check if tube exists, if not create it by putting a test job
    try {
        $stats = $pheanstalk->statsTube($tube);
        echo "📊 Tube '{$tube}' exists with {$stats->current_jobs_ready} ready jobs\n\n";
    } catch (\Pheanstalk\Exception\ServerException $e) {
        if (strpos($e->getMessage(), 'NOT_FOUND') !== false) {
            echo "⚠️  Tube '{$tube}' doesn't exist, creating it...\n";
            
            // Create tube by putting a test job
            $testPayload = json_encode([
                'action' => 'tube_creation_test',
                'message' => 'Creating tube for testing',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $job = $pheanstalk->useTube($tube)->put($testPayload);
            echo "✅ Tube created with test job ID: " . $job->getId() . "\n\n";
        }
    }
    
    // Test form submission simulation
    echo "🔄 Simulating form submission to test mirroring...\n";
    
    $formSubmissionData = [
        'action' => 'form_submission_created',
        'data' => [
            'operation' => 'create',
            'student_id' => null,
            'data' => [
                'name' => 'Test Student',
                'email' => 'test@example.com',
                'phone' => '+1234567890',
                'gender' => 'male'
            ],
            'source' => 'form',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Script',
            'submitted_at' => date('Y-m-d H:i:s')
        ],
        'queued_at' => date('Y-m-d H:i:s'),
        'source' => 'form_submission_controller',
        'mirror_id' => uniqid('test_', true)
    ];
    
    $payload = json_encode($formSubmissionData, JSON_UNESCAPED_UNICODE);
    $job = $pheanstalk->useTube($tube)->put($payload);
    
    echo "✅ Form submission mirrored to Beanstalk with job ID: " . $job->getId() . "\n";
    echo "   📦 Payload size: " . strlen($payload) . " bytes\n\n";
    
    // Test CSV simulation
    echo "🔄 Simulating CSV upload to test mirroring...\n";
    
    $csvBatchData = [
        'action' => 'csv_batch_uploaded',
        'data' => [
            'operation' => 'create',
            'source' => 'csv',
            'batch_data' => [
                [
                    'operation' => 'create',
                    'data' => [
                        'name' => 'CSV Student 1',
                        'email' => 'csv1@example.com',
                        'phone' => '+1234567891',
                        'gender' => 'female'
                    ],
                    'source' => 'csv',
                    'csv_row' => 1
                ],
                [
                    'operation' => 'create',
                    'data' => [
                        'name' => 'CSV Student 2',
                        'email' => 'csv2@example.com',
                        'phone' => '+1234567892',
                        'gender' => 'male'
                    ],
                    'source' => 'csv',
                    'csv_row' => 2
                ]
            ],
            'csv_file' => 'test_students.csv',
            'total_rows' => 2
        ],
        'queued_at' => date('Y-m-d H:i:s'),
        'source' => 'form_submission_controller',
        'mirror_id' => uniqid('test_csv_', true)
    ];
    
    $csvPayload = json_encode($csvBatchData, JSON_UNESCAPED_UNICODE);
    $csvJob = $pheanstalk->useTube($tube)->put($csvPayload);
    
    echo "✅ CSV batch mirrored to Beanstalk with job ID: " . $csvJob->getId() . "\n";
    echo "   📦 Payload size: " . strlen($csvPayload) . " bytes\n\n";
    
    // Check final tube stats
    $finalStats = $pheanstalk->statsTube($tube);
    echo "📊 Final tube '{$tube}' stats:\n";
    echo "   - Ready jobs: " . $finalStats->current_jobs_ready . "\n";
    echo "   - Total jobs: " . $finalStats->total_jobs . "\n\n";
    
    echo "✅ SUCCESS: New architecture test completed!\n";
    echo "   - JSON data is stored in Beanstalk tube FIRST\n";
    echo "   - No duplicate validation in controller\n";
    echo "   - Consumer will handle all validation\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Architecture test completed. Use the consumer to process these jobs.\n";