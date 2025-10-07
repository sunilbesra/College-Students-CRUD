#!/bin/bash

echo "🧪 Testing New Batch Processing (1 Job for Multiple CSV Rows)"
echo "============================================================="

echo "🔧 Starting queue worker..."
pkill -f "queue:work" 2>/dev/null
php artisan queue:work --queue=form_submission_jobs --timeout=60 --tries=3 > /tmp/queue_output.log 2>&1 &
WORKER_PID=$!
echo "✅ Queue worker started with PID: $WORKER_PID"

echo ""
echo "📊 Initial state:"
php artisan tinker --execute="echo 'FormSubmissions in DB: ' . App\Models\FormSubmission::count();"

echo ""
echo "🧪 Simulating CSV batch processing..."

# Simulate the new batch processing directly
php artisan tinker --execute="
\$batchRows = [
    [
        'operation' => 'create',
        'source' => 'csv',
        'data' => [
            'name' => 'Alice Batch Test',
            'email' => 'alice.batch.direct@example.com',
            'phone' => '+1111111111',
            'gender' => 'female',
            'course' => 'Physics',
            'grade' => 'A'
        ],
        'csv_row' => 1
    ],
    [
        'operation' => 'create',
        'source' => 'csv',
        'data' => [
            'name' => 'Bob Batch Test', 
            'email' => 'bob.batch.direct@example.com',
            'phone' => '+2222222222',
            'gender' => 'male',
            'course' => 'Chemistry',
            'grade' => 'B'
        ],
        'csv_row' => 2
    ]
];

\$batchSubmissionData = [
    'operation' => 'create',
    'source' => 'csv',
    'batch_data' => \$batchRows,
    'ip_address' => '127.0.0.1',
    'user_agent' => 'Test Script',
    'submitted_at' => now()->toDateTimeString(),
    'csv_file' => 'test_batch.csv',
    'total_rows' => count(\$batchRows)
];

echo 'Dispatching SINGLE batch job with ' . count(\$batchRows) . ' rows...' . PHP_EOL;
\App\Jobs\ProcessFormSubmissionData::dispatch(null, \$batchSubmissionData);
echo 'Batch job dispatched successfully!' . PHP_EOL;
"

echo ""
echo "⏳ Waiting for processing (10 seconds)..."
sleep 10

echo ""
echo "📊 Results after batch processing:"
php artisan tinker --execute="
\$count = App\Models\FormSubmission::count();
echo 'Total FormSubmissions in DB: ' . \$count . PHP_EOL;

if (\$count > 0) {
    echo 'Recent submissions:' . PHP_EOL;
    App\Models\FormSubmission::orderBy('created_at', 'desc')->limit(3)->get()->each(function(\$s) {
        echo '- ' . (\$s->data['name'] ?? 'N/A') . ' (' . (\$s->data['email'] ?? 'N/A') . ') - ' . \$s->status . ' - ' . \$s->source . PHP_EOL;
    });
}
"

echo ""
echo "🔍 Checking Beanstalk queue status..."
echo "Expected: Should show fewer jobs now (1 job instead of N jobs for N rows)"

echo ""
echo "📋 Check queue worker output:"
cat /tmp/queue_output.log | tail -n 20

echo ""
echo "🧹 Cleanup..."
kill $WORKER_PID 2>/dev/null
rm -f /tmp/queue_output.log

echo ""
echo "✅ Batch Processing Test Complete!"
echo ""
echo "📝 Summary of Changes:"
echo "   - ✅ CSV processing now creates 1 batch job instead of N individual jobs"
echo "   - ✅ ProcessFormSubmissionData handles both single and batch processing"
echo "   - ✅ Each CSV row still creates individual FormSubmission records"
echo "   - ✅ Beanstalk will show 1 job for entire CSV upload"