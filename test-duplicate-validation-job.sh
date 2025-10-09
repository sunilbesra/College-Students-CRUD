#!/bin/bash

# Test ProcessFormSubmissionData job with duplicate validation
# This script tests that duplicate validation works in the job instead of controller

echo "=========================================="
echo "Testing ProcessFormSubmissionData Job Duplicate Validation"
echo "=========================================="

cd /home/sunil/Desktop/Sunil/Students

echo ""
echo "Step 1: Getting initial counts..."
INITIAL_COUNT=$(php artisan tinker --execute="echo \App\Models\FormSubmission::count();")
echo "Initial FormSubmission count: $INITIAL_COUNT"

echo ""
echo "Step 2: Getting an existing email to test duplicate detection..."
EXISTING_EMAIL=$(php artisan tinker --execute="
\$submission = \App\Models\FormSubmission::where('status', 'completed')
    ->where('operation', 'create')
    ->first();
if (\$submission && isset(\$submission->data['email'])) {
    echo \$submission->data['email'];
} else {
    echo 'test@example.com';
}
")
echo "Testing with existing email: $EXISTING_EMAIL"

echo ""
echo "Step 3: Testing single form submission with duplicate email..."
echo "Dispatching ProcessFormSubmissionData job with duplicate email..."

php artisan tinker --execute="
// Test single form submission with duplicate email
\$submissionData = [
    'operation' => 'create',
    'student_id' => null,
    'data' => [
        'name' => 'Test Duplicate User',
        'email' => '$EXISTING_EMAIL',
        'phone' => '9876543210',
        'address' => '123 Test Street',
        'gender' => 'male'
    ],
    'source' => 'test',
    'ip_address' => '127.0.0.1',
    'user_agent' => 'Test/1.0',
    'submitted_at' => now()->toDateTimeString()
];

// Dispatch to ProcessFormSubmissionData - null submissionId for new processing
\App\Jobs\ProcessFormSubmissionData::dispatch(null, \$submissionData);

echo 'Job dispatched for single form submission with duplicate email';
"

echo ""
echo "Step 4: Wait for job processing..."
sleep 3

echo ""
echo "Step 5: Testing CSV batch with duplicate emails..."
echo "Creating test CSV data with duplicates..."

# Create a test CSV with duplicate emails
cat > test_duplicate_validation.csv << EOF
name,email,phone,address,gender
John Duplicate,$EXISTING_EMAIL,1234567890,123 Main St,male
Jane New,jane.new@example.com,2345678901,456 Oak Ave,female
Bob Another,$EXISTING_EMAIL,3456789012,789 Pine Rd,male
Alice Fresh,alice.fresh@example.com,4567890123,321 Elm St,female
EOF

echo "Created test CSV with duplicate emails"

echo ""
echo "Step 6: Processing CSV through job..."

php artisan tinker --execute="
// Create batch data similar to controller
\$csvData = [
    ['name' => 'John Duplicate', 'email' => '$EXISTING_EMAIL', 'phone' => '1234567890', 'address' => '123 Main St', 'gender' => 'male'],
    ['name' => 'Jane New', 'email' => 'jane.new@example.com', 'phone' => '2345678901', 'address' => '456 Oak Ave', 'gender' => 'female'],
    ['name' => 'Bob Another', 'email' => '$EXISTING_EMAIL', 'phone' => '3456789012', 'address' => '789 Pine Rd', 'gender' => 'male'],
    ['name' => 'Alice Fresh', 'email' => 'alice.fresh@example.com', 'phone' => '4567890123', 'address' => '321 Elm St', 'gender' => 'female']
];

\$batchData = [];
foreach (\$csvData as \$index => \$rowData) {
    \$batchData[] = [
        'operation' => 'create',
        'student_id' => null,
        'data' => \$rowData,
        'source' => 'csv',
        'csv_row' => \$index + 1
    ];
}

\$batchSubmissionData = [
    'operation' => 'create',
    'source' => 'csv',
    'batch_data' => \$batchData,
    'ip_address' => '127.0.0.1',
    'user_agent' => 'Test/1.0',
    'submitted_at' => now()->toDateTimeString(),
    'csv_file' => 'test_duplicate_validation.csv',
    'total_rows' => count(\$batchData)
];

// Dispatch batch job
\App\Jobs\ProcessFormSubmissionData::dispatch(null, \$batchSubmissionData);

echo 'Batch job dispatched for CSV with ' . count(\$batchData) . ' rows (including duplicates)';
"

echo ""
echo "Step 7: Wait for batch processing..."
sleep 5

echo ""
echo "Step 8: Checking results..."

php artisan tinker --execute="
echo 'Final Results:' . PHP_EOL;

\$finalCount = \App\Models\FormSubmission::count();
echo 'Final FormSubmission count: ' . \$finalCount . PHP_EOL;
echo 'New submissions created: ' . (\$finalCount - $INITIAL_COUNT) . PHP_EOL;

echo PHP_EOL . 'Breakdown by status:' . PHP_EOL;
echo 'Completed: ' . \App\Models\FormSubmission::where('status', 'completed')->count() . PHP_EOL;
echo 'Failed: ' . \App\Models\FormSubmission::where('status', 'failed')->count() . PHP_EOL;
echo 'Processing: ' . \App\Models\FormSubmission::where('status', 'processing')->count() . PHP_EOL;

echo PHP_EOL . 'Recent failed submissions (duplicates):' . PHP_EOL;
\$failedSubmissions = \App\Models\FormSubmission::where('status', 'failed')
    ->where('created_at', '>=', now()->subMinutes(5))
    ->get();
    
foreach (\$failedSubmissions as \$submission) {
    echo 'ID: ' . substr(\$submission->_id, -8) . 
         ' | Email: ' . (\$submission->data['email'] ?? 'N/A') . 
         ' | Error: ' . substr(\$submission->error_message ?? 'N/A', 0, 50) . '...' .
         ' | CSV Row: ' . (\$submission->csv_row ?? 'N/A') . PHP_EOL;
}

echo PHP_EOL . 'Recent successful submissions:' . PHP_EOL;
\$recentSubmissions = \App\Models\FormSubmission::where('status', 'completed')
    ->where('created_at', '>=', now()->subMinutes(5))
    ->get();
    
foreach (\$recentSubmissions as \$submission) {
    echo 'ID: ' . substr(\$submission->_id, -8) . 
         ' | Email: ' . (\$submission->data['email'] ?? 'N/A') . 
         ' | CSV Row: ' . (\$submission->csv_row ?? 'N/A') . PHP_EOL;
}
"

echo ""
echo "Step 9: Check duplicate notifications..."
php artisan tinker --execute="
echo 'Recent duplicate check jobs (ProcessDuplicateEmailCheck):' . PHP_EOL;
echo 'Check Beanstalk queue for duplicate processing jobs...' . PHP_EOL;
"

echo ""
echo "Step 10: Clean up test file..."
rm -f test_duplicate_validation.csv

echo ""
echo "=========================================="
echo "Test Complete!"
echo "=========================================="
echo ""
echo "Expected Results:"
echo "- Single form submission with duplicate email should be marked as FAILED"
echo "- CSV processing should create 2 successful submissions (jane.new, alice.fresh)"
echo "- CSV processing should create 2 failed submissions (john.duplicate, bob.another)"
echo "- Failed submissions should have error_message indicating duplicate email"
echo "- ProcessDuplicateEmailCheck jobs should be queued for async processing"
echo ""
echo "Summary: Duplicate validation now happens in ProcessFormSubmissionData job,"
echo "not in the controller. Controller just dispatches jobs."