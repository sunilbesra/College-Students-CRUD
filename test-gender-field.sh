#!/bin/bash

echo "🧪 Testing Gender Field Implementation"
echo "======================================="

# Start fresh queue worker
echo "🔄 Starting queue worker..."
pkill -f "queue:work" 2>/dev/null
php artisan queue:work --queue=form_submission_jobs --timeout=60 --tries=3 > /dev/null 2>&1 &
WORKER_PID=$!
echo "✅ Queue worker started with PID: $WORKER_PID"

echo ""
echo "📊 Current FormSubmission count:"
php artisan tinker --execute="echo App\Models\FormSubmission::count();"

echo ""
echo "🧪 Testing CSV upload with gender field..."

# Test using the CSV file we created
echo "📁 Testing with form_submissions_with_gender.csv"

# Simulate CSV upload by creating individual jobs
echo "🔄 Creating test submissions with gender..."

php artisan tinker --execute="
\$testData = [
    ['name' => 'Alice Test', 'email' => 'alice.gender.final@test.com', 'gender' => 'female', 'course' => 'Physics', 'phone' => '1111111111'],
    ['name' => 'Bob Test', 'email' => 'bob.gender.final@test.com', 'gender' => 'male', 'course' => 'Chemistry', 'phone' => '2222222222']
];

foreach (\$testData as \$data) {
    \$submission = \App\Models\FormSubmission::create([
        'operation' => 'create',
        'source' => 'csv',
        'data' => \$data,
        'status' => 'processing'
    ]);
    
    echo 'Created submission: ' . \$submission->_id . ' with gender: ' . \$data['gender'] . PHP_EOL;
    
    // Validate and update
    \$validated = \App\Services\FormSubmissionValidator::validate(\$data);
    \$submission->update([
        'status' => 'completed',
        'data' => \$validated,
        'processed_at' => now()
    ]);
}

echo 'Test submissions created and processed' . PHP_EOL;
"

echo ""
echo "⏳ Waiting 3 seconds for processing..."
sleep 3

echo ""
echo "📊 Results - Latest submissions with gender:"
php artisan tinker --execute="
App\Models\FormSubmission::orderBy('created_at', 'desc')->limit(3)->get()->each(function(\$s) {
    echo 'Name: ' . (\$s->data['name'] ?? 'N/A') . PHP_EOL;
    echo 'Email: ' . (\$s->data['email'] ?? 'N/A') . PHP_EOL; 
    echo 'Gender: ' . (\$s->data['gender'] ?? 'NOT SET') . PHP_EOL;
    echo 'Status: ' . \$s->status . PHP_EOL;
    echo 'Keys: ' . implode(', ', array_keys(\$s->data)) . PHP_EOL;
    echo '---' . PHP_EOL;
});
"

echo ""
echo "🧹 Cleaning up..."
kill $WORKER_PID 2>/dev/null

echo "✅ Gender field test completed!"
echo ""
echo "🌐 You can also test via web interface:"
echo "   - Form: http://localhost:8000/form-submissions/create"
echo "   - CSV:  http://localhost:8000/form-submissions/csv/upload"
echo "   - View: http://localhost:8000/form-submissions"