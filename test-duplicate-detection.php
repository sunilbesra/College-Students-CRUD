<?php

require_once 'vendor/autoload.php';

use Pheanstalk\Pheanstalk;

echo "🧪 Testing New Architecture with Duplicates\n";
echo "=" . str_repeat("=", 45) . "\n\n";

$host = '127.0.0.1';
$port = 11300;
$tube = 'form_submission_json';

try {
    $pheanstalk = Pheanstalk::create($host, $port);
    
    echo "✅ Connected to Beanstalkd at {$host}:{$port}\n";
    
    // Test 1: Form submission with unique email
    echo "\n🔄 Test 1: Form submission with unique email...\n";
    
    $uniqueSubmission = [
        'action' => 'form_submission_created',
        'data' => [
            'operation' => 'create',
            'student_id' => null,
            'data' => [
                'name' => 'Unique Student',
                'email' => 'unique@example.com',
                'phone' => '+1234567890',
                'gender' => 'female'
            ],
            'source' => 'form',
            'ip_address' => '127.0.0.1',
            'submitted_at' => date('Y-m-d H:i:s')
        ],
        'queued_at' => date('Y-m-d H:i:s'),
        'source' => 'form_submission_controller',
        'mirror_id' => uniqid('unique_', true)
    ];
    
    $job1 = $pheanstalk->useTube($tube)->put(json_encode($uniqueSubmission));
    echo "✅ Unique email submission stored (Job ID: {$job1->getId()})\n";
    
    // Test 2: Form submission with duplicate email
    echo "\n🔄 Test 2: Form submission with DUPLICATE email...\n";
    
    $duplicateSubmission = [
        'action' => 'form_submission_created',
        'data' => [
            'operation' => 'create',
            'student_id' => null,
            'data' => [
                'name' => 'Duplicate Student',
                'email' => 'test@example.com', // This will be detected as duplicate
                'phone' => '+9876543210',
                'gender' => 'male'
            ],
            'source' => 'form',
            'ip_address' => '127.0.0.1',
            'submitted_at' => date('Y-m-d H:i:s')
        ],
        'queued_at' => date('Y-m-d H:i:s'),
        'source' => 'form_submission_controller',
        'mirror_id' => uniqid('duplicate_', true)
    ];
    
    $job2 = $pheanstalk->useTube($tube)->put(json_encode($duplicateSubmission));
    echo "✅ Duplicate email submission stored (Job ID: {$job2->getId()})\n";
    
    // Test 3: CSV batch with mixed unique and duplicate emails
    echo "\n🔄 Test 3: CSV batch with mixed emails...\n";
    
    $mixedCsvBatch = [
        'action' => 'csv_batch_uploaded',
        'data' => [
            'operation' => 'create',
            'source' => 'csv',
            'batch_data' => [
                [
                    'operation' => 'create',
                    'data' => [
                        'name' => 'Valid CSV Student',
                        'email' => 'valid.csv@example.com',
                        'phone' => '+1111111111',
                        'gender' => 'male'
                    ],
                    'source' => 'csv',
                    'csv_row' => 1
                ],
                [
                    'operation' => 'create',
                    'data' => [
                        'name' => 'Duplicate CSV Student',
                        'email' => 'duplicate@test.com', // This will be detected as duplicate
                        'phone' => '+2222222222',
                        'gender' => 'female'
                    ],
                    'source' => 'csv',
                    'csv_row' => 2
                ],
                [
                    'operation' => 'create',
                    'data' => [
                        'name' => 'Another Valid Student',
                        'email' => 'another.valid@example.com',
                        'phone' => '+3333333333',
                        'gender' => 'female'
                    ],
                    'source' => 'csv',
                    'csv_row' => 3
                ]
            ],
            'csv_file' => 'test_mixed_duplicates.csv',
            'total_rows' => 3
        ],
        'queued_at' => date('Y-m-d H:i:s'),
        'source' => 'form_submission_controller',
        'mirror_id' => uniqid('mixed_csv_', true)
    ];
    
    $job3 = $pheanstalk->useTube($tube)->put(json_encode($mixedCsvBatch));
    echo "✅ Mixed CSV batch stored (Job ID: {$job3->getId()})\n";
    
    // Check tube stats
    $stats = $pheanstalk->statsTube($tube);
    echo "\n📊 Tube stats after adding test jobs:\n";
    echo "   - Ready jobs: {$stats->current_jobs_ready}\n";
    echo "   - Total jobs: {$stats->total_jobs}\n\n";
    
    echo "✅ Test jobs created successfully!\n";
    echo "🔄 Now run the consumer to see duplicate detection:\n";
    echo "   php new_architecture_consumer.php\n\n";
    
    echo "Expected Results:\n";
    echo "  📧 unique@example.com     → ✅ VALID (will be processed)\n";
    echo "  📧 test@example.com      → ⚠️  DUPLICATE (will be rejected)\n";
    echo "  📧 valid.csv@example.com → ✅ VALID (will be processed)\n";
    echo "  📧 duplicate@test.com    → ⚠️  DUPLICATE (will be rejected)\n";
    echo "  📧 another.valid@example.com → ✅ VALID (will be processed)\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 55) . "\n";
echo "Duplicate detection test setup completed!\n";