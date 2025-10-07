#!/bin/bash

# Form Submission CSV Upload Test Script
# This script tests the CSV upload functionality step by step

set -e

echo "🧪 Form Submission CSV Upload Test"
echo "=================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_step() {
    echo -e "${BLUE}[STEP]${NC} $1"
}

# Check prerequisites
print_step "Checking prerequisites..."

# Check Laravel
if [ ! -f "artisan" ]; then
    print_error "Laravel artisan not found. Run from project root."
    exit 1
fi

# Check MongoDB
if ! mongosh --quiet college --eval "db.adminCommand('ping')" > /dev/null 2>&1; then
    print_error "MongoDB connection failed"
    exit 1
fi

# Check Beanstalkd
if ! nc -z localhost 11300 2>/dev/null; then
    print_warning "Beanstalkd not running. Starting queue worker manually..."
fi

print_status "Prerequisites checked"

# Clean up old data
print_step "Cleaning up old test data..."
mongosh --quiet college --eval "
    db.form_submissions.deleteMany({});
    db.students.deleteMany({email: /test@example\.com/});
    print('Cleanup completed');
"

# Create test CSV file
print_step "Creating test CSV file..."
cat > test_form_submission.csv << 'EOF'
name,email,phone,course,grade,address
Test User 1,test1@example.com,+1234567890,Computer Science,A,"123 Test St"
Test User 2,test2@example.com,+0987654321,Mathematics,B+,"456 Test Ave"  
Test User 3,test3@example.com,+1122334455,Physics,A-,"789 Test Rd"
EOF

print_status "Test CSV file created with 3 records"

# Show CSV content
print_step "CSV file content:"
cat test_form_submission.csv

# Manual creation test
print_step "Testing manual form submission creation..."

php -r "
require 'vendor/autoload.php';

\$app = require_once 'bootstrap/app.php';
\$kernel = \$app->make(Illuminate\Contracts\Console\Kernel::class);
\$kernel->bootstrap();

use App\Models\FormSubmission;
use App\Jobs\ProcessFormSubmissionData;

\$testData = [
    'operation' => 'create',
    'student_id' => null,
    'data' => [
        'name' => 'Manual Test User',
        'email' => 'manual.test@example.com',
        'phone' => '+9999999999',
        'course' => 'Test Course',
        'grade' => 'A+'
    ],
    'source' => 'api',
    'ip_address' => '127.0.0.1',
    'user_agent' => 'Test Script',
    'status' => 'queued'
];

try {
    \$submission = FormSubmission::create(\$testData);
    echo \"✓ Form submission created: {\$submission->_id}\n\";
    echo \"Data type: \" . gettype(\$submission->data) . \"\n\";
    echo \"Data content: \" . json_encode(\$submission->data) . \"\n\";
    
    // Dispatch job
    ProcessFormSubmissionData::dispatch(\$submission->_id, \$testData);
    echo \"✓ Job dispatched\n\";
    
} catch (Exception \$e) {
    echo \"✗ Error: \" . \$e->getMessage() . \"\n\";
    exit(1);
}
"

# Process the job immediately
print_step "Processing queued job..."
timeout 30s php artisan queue:work --once --queue=form_submission_jobs || print_warning "Queue processing timeout or no jobs"

# Check results
print_step "Checking manual creation results..."
mongosh --quiet college --eval "
    console.log('Form submissions:');
    db.form_submissions.find().forEach(doc => {
        console.log('ID:', doc._id.toString());
        console.log('Operation:', doc.operation);  
        console.log('Status:', doc.status);
        console.log('Data type:', typeof doc.data);
        console.log('Data keys:', Object.keys(doc.data || {}));
        console.log('---');
    });
    
    console.log('Students created:');
    db.students.find({email: /test@example\.com/}).forEach(doc => {
        console.log('Name:', doc.name, 'Email:', doc.email);
    });
"

# Start worker for CSV test
print_step "Starting background worker for CSV test..."
php artisan queue:work beanstalkd --queue=form_submission_jobs --timeout=60 > queue_output.log 2>&1 &
WORKER_PID=$!
print_status "Worker started with PID: $WORKER_PID"
sleep 2

# Simulate CSV upload
print_step "Simulating CSV upload via curl..."

# Get CSRF token
CSRF_TOKEN=$(curl -s -c cookies.txt http://localhost:8000/form-submissions/csv/upload | grep -oP 'name="_token" value="\K[^"]+' || echo "")

if [ -z "$CSRF_TOKEN" ]; then
    print_error "Failed to get CSRF token. Is Laravel server running on :8000?"
    kill $WORKER_PID 2>/dev/null || true
    exit 1
fi

print_status "CSRF token obtained: ${CSRF_TOKEN:0:20}..."

# Upload CSV
print_status "Uploading CSV file..."
UPLOAD_RESPONSE=$(curl -s -b cookies.txt -X POST http://localhost:8000/form-submissions/csv/process \
    -F "_token=$CSRF_TOKEN" \
    -F "operation=create" \
    -F "csv_file=@test_form_submission.csv" \
    -w "\n%{http_code}")

echo "Upload response code: $(echo "$UPLOAD_RESPONSE" | tail -n1)"

# Wait for processing
print_step "Waiting for job processing..."
sleep 10

# Kill worker
kill $WORKER_PID 2>/dev/null || true

# Check final results
print_step "Final results check..."
mongosh --quiet college --eval "
    const formSubmissions = db.form_submissions.countDocuments();
    const students = db.students.countDocuments();
    
    console.log('=== FINAL RESULTS ===');
    console.log('Form Submissions:', formSubmissions);
    console.log('Students Created:', students);
    
    console.log('\n=== FORM SUBMISSIONS DETAIL ===');
    db.form_submissions.find().forEach(doc => {
        console.log('ID:', doc._id.toString().substring(0, 8));
        console.log('Status:', doc.status);
        console.log('Operation:', doc.operation); 
        console.log('Source:', doc.source);
        console.log('Data valid:', doc.data && typeof doc.data === 'object');
        console.log('Error:', doc.error_message || 'none');
        console.log('---');
    });
    
    console.log('\n=== STUDENTS CREATED ===');
    db.students.find().forEach(doc => {
        console.log('Name:', doc.name);
        console.log('Email:', doc.email);
        console.log('Course:', doc.course);
        console.log('---');
    });
"

# Show logs
print_step "Recent queue worker logs:"
tail -n 20 queue_output.log || print_warning "No queue logs found"

print_step "Recent Laravel logs:"
tail -n 10 storage/logs/laravel.log | grep -E "(form_submission|ProcessForm|CSV)" || print_warning "No relevant logs found"

# Cleanup
rm -f cookies.txt test_form_submission.csv queue_output.log

print_step "Test completed! Check results above."