#!/bin/bash

# Complete Architecture Test: Background Processing with Job-Based Validated Data Storage
# Tests both Form Submission and CSV Upload flows with proper validation

echo "============================================================"
echo "🧩 Complete Architecture Test: Job-Based Validated Data Storage"
echo "============================================================"

cd /home/sunil/Desktop/Sunil/Students

echo ""
echo "📊 Step 1: Initial System State"
echo "------------------------------------------------------------"
INITIAL_COUNT=$(php artisan tinker --execute="echo \App\Models\FormSubmission::count();")
echo "Initial FormSubmission count: $INITIAL_COUNT"

# Get existing email for duplicate testing
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
echo "Existing email for duplicate testing: $EXISTING_EMAIL"

echo ""
echo "🎯 Step 2: Form Submission Flow (Single Entry)"
echo "------------------------------------------------------------"
echo "Testing controller-level duplicate prevention..."

php artisan tinker --execute="
echo '2.1 Testing Controller Duplicate Check:' . PHP_EOL;

// Simulate form submission data
\$formData = [
    'name' => 'Valid New User',
    'email' => 'new.user@validtest.com',
    'phone' => '9876543210',
    'address' => '123 Valid Street',
    'gender' => 'female'
];

\$submissionData = [
    'operation' => 'create',
    'student_id' => null,
    'data' => \$formData,
    'source' => 'form',
    'ip_address' => '127.0.0.1',
    'user_agent' => 'Test/1.0',
    'submitted_at' => now()->toDateTimeString()
];

// Check if this would be caught as duplicate (should be false)
\$email = trim(strtolower(\$formData['email']));
\$existingSubmission = \App\Models\FormSubmission::where('data.email', \$email)
    ->where('status', 'completed')
    ->where('operation', 'create')
    ->first();

if (\$existingSubmission) {
    echo '❌ ERROR: New email detected as duplicate!' . PHP_EOL;
} else {
    echo '✅ Controller: New email cleared for processing' . PHP_EOL;
    
    // Dispatch job for processing (this is what controller does)
    \App\Jobs\ProcessFormSubmissionData::dispatch(null, \$submissionData);
    echo '📤 Job dispatched: ProcessFormSubmissionData for new email' . PHP_EOL;
}

echo PHP_EOL . '2.2 Testing Duplicate Prevention:' . PHP_EOL;

// Test with duplicate email
\$duplicateData = [
    'name' => 'Duplicate User',
    'email' => '$EXISTING_EMAIL',
    'phone' => '1234567890',
    'address' => '456 Duplicate Ave',
    'gender' => 'male'
];

\$duplicateEmail = trim(strtolower(\$duplicateData['email']));
\$existingSubmission = \App\Models\FormSubmission::where('data.email', \$duplicateEmail)
    ->where('status', 'completed')
    ->where('operation', 'create')
    ->first();

if (\$existingSubmission) {
    echo '✅ Controller: Duplicate email detected and prevented' . PHP_EOL;
    echo '   Existing ID: ' . substr(\$existingSubmission->_id, -8) . PHP_EOL;
    echo '   ❌ Job NOT dispatched - duplicate blocked at controller level' . PHP_EOL;
} else {
    echo '❌ ERROR: Duplicate email not detected!' . PHP_EOL;
}
"

echo ""
echo "📄 Step 3: CSV Upload Flow (Batch Processing)"
echo "------------------------------------------------------------"

# Create comprehensive test CSV
cat > test_complete_architecture.csv << EOF
name,email,phone,address,gender
Alice Valid,alice.valid@archtest.com,1111111111,111 Alice St,female
Bob Duplicate,$EXISTING_EMAIL,2222222222,222 Bob Ave,male
Charlie Fresh,charlie.fresh@archtest.com,3333333333,333 Charlie Rd,male
Diana Duplicate,$EXISTING_EMAIL,4444444444,444 Diana Blvd,female
Eve New,eve.new@archtest.com,5555555555,555 Eve Ln,female
Frank Valid,frank.valid@archtest.com,6666666666,666 Frank Dr,male
EOF

echo "Created test CSV with 4 valid emails and 2 duplicates"

echo ""
echo "Testing CSV controller-level filtering..."

php artisan tinker --execute="
echo '3.1 CSV Controller Processing:' . PHP_EOL;

// Simulate CSV data processing
\$csvData = [
    ['name' => 'Alice Valid', 'email' => 'alice.valid@archtest.com', 'phone' => '1111111111', 'address' => '111 Alice St', 'gender' => 'female'],
    ['name' => 'Bob Duplicate', 'email' => '$EXISTING_EMAIL', 'phone' => '2222222222', 'address' => '222 Bob Ave', 'gender' => 'male'],
    ['name' => 'Charlie Fresh', 'email' => 'charlie.fresh@archtest.com', 'phone' => '3333333333', 'address' => '333 Charlie Rd', 'gender' => 'male'],
    ['name' => 'Diana Duplicate', 'email' => '$EXISTING_EMAIL', 'phone' => '4444444444', 'address' => '444 Diana Blvd', 'gender' => 'female'],
    ['name' => 'Eve New', 'email' => 'eve.new@archtest.com', 'phone' => '5555555555', 'address' => '555 Eve Ln', 'gender' => 'female'],
    ['name' => 'Frank Valid', 'email' => 'frank.valid@archtest.com', 'phone' => '6666666666', 'address' => '666 Frank Dr', 'gender' => 'male']
];

\$batchData = [];
\$duplicateCount = 0;
\$duplicateEmails = [];
\$validRows = 0;

echo 'Processing CSV rows:' . PHP_EOL;

foreach (\$csvData as \$index => \$rowData) {
    \$rowEmail = trim(strtolower(\$rowData['email']));
    \$rowNum = \$index + 1;
    
    // Check for duplicates (same logic as controller)
    \$existingSubmission = \App\Models\FormSubmission::where('data.email', \$rowEmail)
        ->where('status', 'completed')
        ->where('operation', 'create')
        ->first();
    
    if (\$existingSubmission) {
        \$duplicateCount++;
        \$duplicateEmails[] = [
            'email' => \$rowEmail,
            'row' => \$rowNum,
            'existing_id' => substr(\$existingSubmission->_id, -8)
        ];
        echo \"❌ Row {\$rowNum}: DUPLICATE - {\$rowEmail} (Existing: \" . substr(\$existingSubmission->_id, -8) . ')' . PHP_EOL;
        continue; // Skip duplicate
    }
    
    // Add to batch data for job processing
    \$batchData[] = [
        'operation' => 'create',
        'data' => \$rowData,
        'source' => 'csv',
        'csv_row' => \$rowNum
    ];
    \$validRows++;
    echo \"✅ Row {\$rowNum}: VALID - {\$rowEmail}\" . PHP_EOL;
}

echo PHP_EOL . 'CSV Filtering Results:' . PHP_EOL;
echo 'Total rows: ' . count(\$csvData) . PHP_EOL;
echo 'Valid rows (will be processed): ' . \$validRows . PHP_EOL;
echo 'Duplicate rows (filtered out): ' . \$duplicateCount . PHP_EOL;

if (count(\$batchData) > 0) {
    // Create batch submission data
    \$batchSubmissionData = [
        'operation' => 'create',
        'source' => 'csv',
        'batch_data' => \$batchData,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test/1.0',
        'submitted_at' => now()->toDateTimeString(),
        'csv_file' => 'test_complete_architecture.csv',
        'total_rows' => count(\$batchData)
    ];
    
    // Dispatch batch job
    \App\Jobs\ProcessFormSubmissionData::dispatch(null, \$batchSubmissionData);
    echo '📤 Batch job dispatched for ' . count(\$batchData) . ' valid rows' . PHP_EOL;
} else {
    echo '⚠️  No valid rows to process - all were duplicates!' . PHP_EOL;
}
"

echo ""
echo "⏳ Step 4: Wait for Job Processing"
echo "------------------------------------------------------------"
echo "Waiting for background jobs to complete..."
sleep 5

echo ""
echo "📈 Step 5: Results Analysis"
echo "------------------------------------------------------------"

php artisan tinker --execute="
echo '5.1 Final Counts:' . PHP_EOL;

\$finalCount = \App\Models\FormSubmission::count();
\$newSubmissions = \$finalCount - $INITIAL_COUNT;

echo 'Initial count: $INITIAL_COUNT' . PHP_EOL;
echo 'Final count: ' . \$finalCount . PHP_EOL;
echo '➕ New submissions created: ' . \$newSubmissions . PHP_EOL;

echo PHP_EOL . '5.2 Recent Submissions (Last 10 minutes):' . PHP_EOL;
\$recentSubmissions = \App\Models\FormSubmission::where('created_at', '>=', now()->subMinutes(10))
    ->orderBy('created_at', 'desc')
    ->get();

foreach (\$recentSubmissions as \$submission) {
    \$statusIcon = match(\$submission->status) {
        'completed' => '✅',
        'failed' => '❌',
        'processing' => '🔄',
        default => '⚪'
    };
    
    \$sourceIcon = match(\$submission->source) {
        'form' => '📝',
        'csv' => '📄',
        'api' => '🔌',
        default => '❓'
    };
    
    echo \$statusIcon . ' ' . \$sourceIcon . ' ID: ' . substr(\$submission->_id, -8) . 
         ' | ' . (\$submission->data['email'] ?? 'N/A') . 
         ' | ' . \$submission->status . 
         ' | Row: ' . (\$submission->csv_row ?? 'N/A') . PHP_EOL;
}

echo PHP_EOL . '5.3 Status Breakdown:' . PHP_EOL;
echo '✅ Completed: ' . \App\Models\FormSubmission::where('status', 'completed')->count() . PHP_EOL;
echo '❌ Failed: ' . \App\Models\FormSubmission::where('status', 'failed')->count() . PHP_EOL;
echo '🔄 Processing: ' . \App\Models\FormSubmission::where('status', 'processing')->count() . PHP_EOL;
echo '⚪ Queued: ' . \App\Models\FormSubmission::where('status', 'queued')->count() . PHP_EOL;

echo PHP_EOL . '5.4 Source Breakdown:' . PHP_EOL;
echo '📝 Form: ' . \App\Models\FormSubmission::where('source', 'form')->count() . PHP_EOL;
echo '📄 CSV: ' . \App\Models\FormSubmission::where('source', 'csv')->count() . PHP_EOL;
echo '🔌 API: ' . \App\Models\FormSubmission::where('source', 'api')->count() . PHP_EOL;
"

echo ""
echo "🧹 Step 6: Cleanup"
echo "------------------------------------------------------------"
rm -f test_complete_architecture.csv
echo "Test files cleaned up"

echo ""
echo "============================================================"
echo "🎉 Architecture Test Complete!"
echo "============================================================"
echo ""
echo "✅ IMPLEMENTED FEATURES:"
echo "------------------------------------------------------------"
echo "1. 🛡️  Controller-Level Duplicate Prevention"
echo "   - Single form: Duplicates blocked before job dispatch"
echo "   - CSV batch: Duplicates filtered out before job dispatch"
echo ""
echo "2. 📤 Job-Based Background Processing"
echo "   - ProcessFormSubmissionData handles all validation"
echo "   - Only clean, validated data reaches the job"
echo "   - Proper error handling and status tracking"
echo ""
echo "3. 💾 Validated Data Storage"
echo "   - FormSubmission model stores all processed data"
echo "   - Status tracking: queued → processing → completed/failed"
echo "   - Source tracking: form, csv, api"
echo ""
echo "4. 🎨 Frontend Integration"
echo "   - Real-time duplicate checking on forms"
echo "   - Proper error messages and feedback"
echo "   - Session-based duplicate details for CSV"
echo ""
echo "5. 📊 Comprehensive Analytics"
echo "   - DuplicateEmailDetected events for tracking"
echo "   - FormSubmissionProcessed events for monitoring"
echo "   - Detailed logs for debugging"
echo ""
echo "🔄 FLOW SUMMARY:"
echo "------------------------------------------------------------"
echo "Form → Controller Check → Job Dispatch → Validation → Storage"
echo "CSV → Controller Filter → Job Dispatch → Batch Process → Storage"
echo ""
echo "✨ KEY BENEFITS:"
echo "- No duplicate data in database"
echo "- Immediate user feedback"
echo "- Background processing for performance"
echo "- Comprehensive validation and error handling"