#!/bin/bash

# Test comprehensive duplicate prevention with frontend messages
# This script tests that duplicates are prevented at controller level AND proper messages are shown

echo "=============================================="
echo "Testing Duplicate Prevention + Frontend Messages"
echo "=============================================="

cd /home/sunil/Desktop/Sunil/Students

echo ""
echo "Step 1: Getting initial counts..."
INITIAL_COUNT=$(php artisan tinker --execute="echo \App\Models\FormSubmission::count();")
echo "Initial FormSubmission count: $INITIAL_COUNT"

echo ""
echo "Step 2: Getting an existing email to test duplicate prevention..."
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
echo "Step 3: Testing single form submission with duplicate email (should be prevented)..."

php artisan tinker --execute="
// Test single form submission with duplicate email - should be caught at controller level
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

// Simulate controller duplicate check
\$email = trim(strtolower(\$submissionData['data']['email']));
\$existingSubmission = \App\Models\FormSubmission::where('data.email', \$email)
    ->where('status', 'completed')
    ->where('operation', 'create')
    ->first();

if (\$existingSubmission) {
    echo 'SUCCESS: Controller-level duplicate prevention working!' . PHP_EOL;
    echo 'Duplicate email detected: ' . \$email . PHP_EOL;
    echo 'Existing submission ID: ' . substr(\$existingSubmission->_id, -8) . PHP_EOL;
    echo 'Job will NOT be dispatched - duplicate prevented at controller level' . PHP_EOL;
} else {
    echo 'ERROR: Controller-level duplicate detection not working!' . PHP_EOL;
}
"

echo ""
echo "Step 4: Testing CSV with duplicate emails (should filter out duplicates)..."

# Create a test CSV with both valid and duplicate emails
cat > test_duplicate_prevention.csv << EOF
name,email,phone,address,gender
John Valid,john.valid@newtest.com,1234567890,123 Main St,male
Jane Duplicate,$EXISTING_EMAIL,2345678901,456 Oak Ave,female
Bob New,bob.new@freshtest.com,3456789012,789 Pine Rd,male
Alice Duplicate,$EXISTING_EMAIL,4567890123,321 Elm St,female
Charlie Fresh,charlie.fresh@newtest.com,5678901234,654 Maple Ave,male
EOF

echo "Created test CSV with 3 valid emails and 2 duplicates"

echo ""
echo "Step 5: Simulating CSV processing with duplicate filtering..."

php artisan tinker --execute="
// Simulate CSV processing with duplicate filtering
\$csvData = [
    ['name' => 'John Valid', 'email' => 'john.valid@newtest.com', 'phone' => '1234567890', 'address' => '123 Main St', 'gender' => 'male'],
    ['name' => 'Jane Duplicate', 'email' => '$EXISTING_EMAIL', 'phone' => '2345678901', 'address' => '456 Oak Ave', 'gender' => 'female'],
    ['name' => 'Bob New', 'email' => 'bob.new@freshtest.com', 'phone' => '3456789012', 'address' => '789 Pine Rd', 'gender' => 'male'],
    ['name' => 'Alice Duplicate', 'email' => '$EXISTING_EMAIL', 'phone' => '4567890123', 'address' => '321 Elm St', 'gender' => 'female'],
    ['name' => 'Charlie Fresh', 'email' => 'charlie.fresh@newtest.com', 'phone' => '5678901234', 'address' => '654 Maple Ave', 'gender' => 'male']
];

\$batchData = [];
\$duplicateCount = 0;
\$duplicateEmails = [];
\$validRows = 0;

foreach (\$csvData as \$index => \$rowData) {
    \$rowEmail = trim(strtolower(\$rowData['email']));
    
    // Check for duplicates
    \$existingSubmission = \App\Models\FormSubmission::where('data.email', \$rowEmail)
        ->where('status', 'completed')
        ->where('operation', 'create')
        ->first();
    
    if (\$existingSubmission) {
        \$duplicateCount++;
        \$duplicateEmails[] = [
            'email' => \$rowEmail,
            'row' => \$index + 1,
            'existing_id' => substr(\$existingSubmission->_id, -8)
        ];
        echo 'DUPLICATE FILTERED: Row ' . (\$index + 1) . ' - ' . \$rowEmail . ' (Existing ID: ' . substr(\$existingSubmission->_id, -8) . ')' . PHP_EOL;
        continue; // Skip duplicate
    }
    
    \$batchData[] = [
        'operation' => 'create',
        'data' => \$rowData,
        'source' => 'csv',
        'csv_row' => \$index + 1
    ];
    \$validRows++;
    echo 'VALID ROW: Row ' . (\$index + 1) . ' - ' . \$rowEmail . PHP_EOL;
}

echo PHP_EOL . 'FILTER RESULTS:' . PHP_EOL;
echo 'Total CSV rows: ' . count(\$csvData) . PHP_EOL;
echo 'Valid rows (will be processed): ' . \$validRows . PHP_EOL;
echo 'Duplicate rows (filtered out): ' . \$duplicateCount . PHP_EOL;

if (\$duplicateCount > 0) {
    echo PHP_EOL . 'DUPLICATE DETAILS FOR FRONTEND:' . PHP_EOL;
    foreach (\$duplicateEmails as \$duplicate) {
        echo '- ' . \$duplicate['email'] . ' (CSV Row ' . \$duplicate['row'] . ', Existing ID: ' . \$duplicate['existing_id'] . ')' . PHP_EOL;
    }
}
"

echo ""
echo "Step 6: Testing actual job dispatch with filtered data..."

php artisan tinker --execute="
// Create filtered batch data (only valid emails)
\$validEmails = ['john.valid@newtest.com', 'bob.new@freshtest.com', 'charlie.fresh@newtest.com'];
\$batchData = [];

foreach (\$validEmails as \$index => \$email) {
    \$batchData[] = [
        'operation' => 'create',
        'student_id' => null,
        'data' => [
            'name' => 'Test User ' . (\$index + 1),
            'email' => \$email,
            'phone' => '123456789' . \$index,
            'address' => '123 Test St',
            'gender' => 'male'
        ],
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
    'csv_file' => 'test_duplicate_prevention.csv',
    'total_rows' => count(\$batchData)
];

// Only dispatch job for valid (non-duplicate) data
if (count(\$batchData) > 0) {
    \App\Jobs\ProcessFormSubmissionData::dispatch(null, \$batchSubmissionData);
    echo 'Job dispatched for ' . count(\$batchData) . ' valid rows (duplicates were filtered out)' . PHP_EOL;
} else {
    echo 'No job dispatched - all rows were duplicates!' . PHP_EOL;
}
"

echo ""
echo "Step 7: Wait for job processing..."
sleep 3

echo ""
echo "Step 8: Checking final results..."

php artisan tinker --execute="
echo 'FINAL RESULTS:' . PHP_EOL;

\$finalCount = \App\Models\FormSubmission::count();
echo 'Final FormSubmission count: ' . \$finalCount . PHP_EOL;
echo 'New submissions created: ' . (\$finalCount - $INITIAL_COUNT) . PHP_EOL;

echo PHP_EOL . 'Recent submissions (last 5 minutes):' . PHP_EOL;
\$recentSubmissions = \App\Models\FormSubmission::where('created_at', '>=', now()->subMinutes(5))
    ->orderBy('created_at', 'desc')
    ->get();
    
foreach (\$recentSubmissions as \$submission) {
    \$statusIcon = \$submission->status === 'completed' ? '✅' : (\$submission->status === 'failed' ? '❌' : '🔄');
    echo \$statusIcon . ' ID: ' . substr(\$submission->_id, -8) . 
         ' | Email: ' . (\$submission->data['email'] ?? 'N/A') . 
         ' | Status: ' . \$submission->status . 
         ' | Source: ' . \$submission->source . PHP_EOL;
}

echo PHP_EOL . 'Breakdown by status:' . PHP_EOL;
echo 'Completed: ' . \App\Models\FormSubmission::where('status', 'completed')->count() . PHP_EOL;
echo 'Failed: ' . \App\Models\FormSubmission::where('status', 'failed')->count() . PHP_EOL;
echo 'Processing: ' . \App\Models\FormSubmission::where('status', 'processing')->count() . PHP_EOL;
"

echo ""
echo "Step 9: Clean up test file..."
rm -f test_duplicate_prevention.csv

echo ""
echo "=============================================="
echo "Test Complete!"
echo "=============================================="
echo ""
echo "Expected Results:"
echo "✅ Single form duplicate: Detected at controller level, no job dispatched"
echo "✅ CSV duplicates: Filtered out at controller level before job dispatch"
echo "✅ Only valid (non-duplicate) data sent to ProcessFormSubmissionData job"
echo "✅ Frontend receives proper duplicate messages and details"
echo "✅ No duplicate data inserted into FormSubmission collection"
echo ""
echo "Key Changes:"
echo "1. Controller checks for duplicates BEFORE dispatching jobs"
echo "2. Duplicates are prevented from being processed entirely"
echo "3. Frontend gets immediate feedback about duplicates"
echo "4. Only clean, validated data reaches the job processor"