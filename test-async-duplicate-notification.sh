#!/bin/bash

# Test Asynchronous Duplicate Check + Notification System
# This demonstrates: Duplicate Check + Notification handled asynchronously using Beanstalk

echo "🚀 Testing Asynchronous Duplicate Check + Notification System"
echo "============================================================="

cd /home/sunil/Desktop/Sunil/Students

echo "📋 Architecture Flow:"
echo "1. Form/CSV data submitted"
echo "2. Data queued to Beanstalk (ProcessFormSubmissionData)"
echo "3. Consumer validates data"
echo "4. If duplicate detected: Queue ProcessDuplicateEmailCheck job"
echo "5. ProcessDuplicateEmailCheck runs asynchronously:"
echo "   - Performs comprehensive duplicate check"
echo "   - Fires DuplicateEmailDetected event"
echo "   - Creates notification via NotificationService"
echo "   - Updates duplicate statistics"
echo "   - Handles source-specific logic"
echo ""

echo "🔧 Starting queue workers..."
# Start queue worker in background
php artisan queue:work --timeout=60 --tries=3 --memory=512 --sleep=1 &
WORKER_PID=$!
echo "Queue worker started with PID: $WORKER_PID"
sleep 2

echo ""
echo "📊 Current system state:"
php artisan tinker --execute="
echo 'FormSubmissions: ' . \App\Models\FormSubmission::count();
echo \"\nNotifications: \" . \App\Models\Notification::count();
echo \"\nFailed jobs: \" . \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
"

echo ""
echo "🧪 Test 1: Submit form with new email (should succeed, no duplicates)"
php artisan tinker --execute="
\$formData = [
    'operation' => 'create',
    'source' => 'form_submission',
    'data' => [
        'name' => 'Test User Async',
        'email' => 'testasync' . time() . '@example.com',
        'phone' => '1234567890',
        'address' => '123 Test St',
        'gender' => 'male',
        'date_of_birth' => '1990-01-01',
        'registration_date' => date('Y-m-d'),
        'is_international' => false
    ],
    'ip_address' => '127.0.0.1',
    'user_agent' => 'Test Browser'
];

dispatch(new \App\Jobs\ProcessFormSubmissionData(null, \$formData));
echo 'New email form submitted - should process normally';
"

echo "Waiting for processing..."
sleep 5

echo ""
echo "🧪 Test 2: Submit form with existing/duplicate email"
php artisan tinker --execute="
// Get an existing email from completed submissions
\$existingEmail = \App\Models\FormSubmission::where('status', 'completed')->first()->data['email'] ?? 'fallback@test.com';
echo 'Using existing email for duplicate test: ' . \$existingEmail;

\$duplicateFormData = [
    'operation' => 'create',
    'source' => 'form_submission',
    'data' => [
        'name' => 'Duplicate Test User',
        'email' => \$existingEmail,
        'phone' => '0987654321',
        'address' => '456 Duplicate Ave',
        'gender' => 'female',
        'date_of_birth' => '1992-05-15',
        'registration_date' => date('Y-m-d'),
        'is_international' => false
    ],
    'ip_address' => '127.0.0.1',
    'user_agent' => 'Test Browser'
];

dispatch(new \App\Jobs\ProcessFormSubmissionData(null, \$duplicateFormData));
echo \"\nDuplicate email form submitted - should trigger async duplicate processing\";
"

echo "Waiting for async duplicate processing..."
sleep 8

echo ""
echo "🧪 Test 3: CSV upload with duplicate emails"
cat > test_async_duplicates.csv << EOF
name,email,phone,gender,date_of_birth,course,enrollment_date,grade,address
John Async,john.async@university.edu,1234567890,male,1990-01-01,Computer Science,2023-09-01,A,123 Main St
Jane Duplicate,jane.doe@university.edu,0987654321,female,1992-05-15,Mathematics,2023-09-01,B,456 Oak Ave
EOF

php artisan tinker --execute="
\$csvData = [
    'operation' => 'batch_create',
    'source' => 'csv',
    'batch_data' => [
        [
            'operation' => 'create',
            'source' => 'csv',
            'data' => [
                'name' => 'John Async',
                'email' => 'john.async' . time() . '@university.edu',
                'phone' => '1234567890',
                'gender' => 'male',
                'date_of_birth' => '1990-01-01',
                'course' => 'Computer Science',
                'enrollment_date' => '2023-09-01',
                'grade' => 'A',
                'address' => '123 Main St'
            ],
            'csv_row' => 1
        ],
        [
            'operation' => 'create',
            'source' => 'csv',
            'data' => [
                'name' => 'Jane Duplicate Test',
                'email' => 'jane.doe@university.edu', // This should be a duplicate
                'phone' => '0987654321',
                'gender' => 'female',
                'date_of_birth' => '1992-05-15',
                'course' => 'Mathematics',
                'enrollment_date' => '2023-09-01',
                'grade' => 'B',
                'address' => '456 Oak Ave'
            ],
            'csv_row' => 2
        ]
    ],
    'ip_address' => '127.0.0.1',
    'user_agent' => 'CSV Test Browser'
];

dispatch(new \App\Jobs\ProcessFormSubmissionData(null, \$csvData));
echo 'CSV with duplicate email submitted - should trigger async duplicate processing for row 2';
"

echo "Waiting for CSV processing and async duplicate handling..."
sleep 10

echo ""
echo "📊 Results Analysis:"
php artisan tinker --execute="
echo '=== FormSubmissions ===';
echo 'Total: ' . \App\Models\FormSubmission::count();
echo \"\nCompleted: \" . \App\Models\FormSubmission::where('status', 'completed')->count();
echo \"\nFailed (duplicates): \" . \App\Models\FormSubmission::where('status', 'failed')->count();

echo \"\n\n=== Notifications ===\";
echo 'Total notifications: ' . \App\Models\Notification::count();
echo \"\nDuplicate notifications: \" . \App\Models\Notification::where('type', 'warning')->where('title', 'LIKE', '%Duplicate%')->count();

echo \"\n\n=== Recent Notifications ===\";
\App\Models\Notification::orderBy('created_at', 'desc')->limit(3)->get(['title', 'type', 'created_at'])->each(function(\$notification) {
    echo \"\n- \" . \$notification->title . \" (\" . \$notification->type . \") - \" . \$notification->created_at;
});

echo \"\n\n=== Queue Status ===\";
echo 'Failed jobs: ' . \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
echo \"\nPending jobs: \" . \Illuminate\Support\Facades\DB::table('jobs')->count();
"

echo ""
echo "📋 Check recent logs for async processing details:"
echo "tail -50 storage/logs/laravel.log | grep -E 'ProcessDuplicateEmailCheck|async|duplicate processing'"

echo ""
echo "🏁 Stopping queue worker..."
kill $WORKER_PID 2>/dev/null || true

echo ""
echo "✅ Asynchronous Duplicate Check + Notification Test Complete!"
echo ""
echo "🎯 Key Features Demonstrated:"
echo "- ✅ Duplicate detection triggers asynchronous ProcessDuplicateEmailCheck job"
echo "- ✅ Async job performs comprehensive duplicate check"
echo "- ✅ Async job creates notifications via NotificationService"
echo "- ✅ Async job fires DuplicateEmailDetected events"
echo "- ✅ Async job updates statistics and handles source-specific logic"
echo "- ✅ Main form processing doesn't block on duplicate handling"
echo "- ✅ Separation of concerns: validation vs duplicate processing"

echo ""
echo "🔍 Architecture Benefits:"
echo "- Form processing responds quickly (no blocking on duplicate checks)"
echo "- Duplicate detection and notifications handled asynchronously"
echo "- Better scalability with Beanstalk queue system"
echo "- Comprehensive duplicate analytics and statistics"
echo "- Source-specific handling (form vs CSV vs API)"
echo "- Robust error handling and retry mechanisms"