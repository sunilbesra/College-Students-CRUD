#!/bin/bash

# Test Form Submission CSV Upload with Student Data Fields
echo "🎓 Testing Form Submission CSV Upload System"
echo "=============================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

print_step() {
    echo -e "${BLUE}🔄 $1${NC}"
}

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    print_error "Please run this script from the Laravel project directory"
    exit 1
fi

# Check if the CSV file exists
CSV_FILE="form_submissions_student_data.csv"
if [ ! -f "$CSV_FILE" ]; then
    print_error "CSV file not found: $CSV_FILE"
    exit 1
fi

print_info "CSV file found: $CSV_FILE"
print_info "First few lines of CSV:"
head -n 3 "$CSV_FILE"
echo ""

# Check Laravel server
print_step "Checking if Laravel server is running..."
if curl -s http://localhost:8000 > /dev/null; then
    print_status "Laravel server is running on :8000"
else
    print_error "Laravel server not running. Please start with: php artisan serve"
    exit 1
fi

# Check if workers are running
print_step "Checking queue workers..."
WORKER_COUNT=$(ps aux | grep "queue:work" | grep -v grep | wc -l)
if [ $WORKER_COUNT -gt 0 ]; then
    print_status "Queue workers are running ($WORKER_COUNT workers)"
else
    print_warning "No queue workers detected. Starting a temporary worker for testing..."
    php artisan queue:work beanstalkd --queue=form_submission_jobs --timeout=60 > worker_output.log 2>&1 &
    WORKER_PID=$!
    print_status "Started temporary worker with PID: $WORKER_PID"
    sleep 3
fi

# Check MongoDB connection
print_step "Checking MongoDB connection..."
php artisan tinker --execute="
try {
    \$count = App\\Models\\FormSubmission::count();
    echo \"MongoDB connected. Form submissions count: \$count\n\";
} catch (Exception \$e) {
    echo \"MongoDB error: \" . \$e->getMessage() . \"\n\";
    exit(1);
}
"

if [ $? -eq 0 ]; then
    print_status "MongoDB connection successful"
else
    print_error "MongoDB connection failed"
    exit 1
fi

# Test CSV upload via web interface simulation
print_step "Testing CSV upload functionality..."

# Get CSRF token
print_info "Getting CSRF token..."
CSRF_TOKEN=$(curl -s -c cookies.txt http://localhost:8000/form-submissions/csv/upload | grep -oP 'name="_token" value="\K[^"]+' || echo "")

if [ -z "$CSRF_TOKEN" ]; then
    print_error "Failed to get CSRF token. Is Laravel server running properly?"
    [ ! -z "$WORKER_PID" ] && kill $WORKER_PID 2>/dev/null
    exit 1
fi

print_status "CSRF token obtained: ${CSRF_TOKEN:0:20}..."

# Upload CSV file
print_info "Uploading CSV file..."
UPLOAD_RESPONSE=$(curl -s -b cookies.txt -X POST http://localhost:8000/form-submissions/csv/process \
    -F "_token=$CSRF_TOKEN" \
    -F "operation=create" \
    -F "csv_file=@$CSV_FILE" \
    -w "%{http_code}")

HTTP_CODE=$(echo "$UPLOAD_RESPONSE" | tail -n1)
RESPONSE_BODY=$(echo "$UPLOAD_RESPONSE" | head -n -1)

echo "Upload HTTP code: $HTTP_CODE"

if [[ "$HTTP_CODE" == "302" ]]; then
    print_status "CSV upload successful (redirected)"
elif [[ "$HTTP_CODE" == "200" ]]; then
    print_status "CSV upload successful"
else
    print_error "CSV upload failed. HTTP code: $HTTP_CODE"
    echo "Response: $RESPONSE_BODY"
fi

# Wait for processing
print_step "Waiting for job processing (30 seconds)..."
sleep 30

# Check form submissions in database
print_step "Checking form submissions in MongoDB..."
php artisan tinker --execute="
\$recent = App\\Models\\FormSubmission::where('source', 'csv')
    ->where('created_at', '>=', now()->subMinutes(5))
    ->get();

echo \"Recent CSV form submissions: \" . \$recent->count() . \"\\n\";

foreach (\$recent as \$submission) {
    echo \"ID: \" . \$submission->_id . \"\\n\";
    echo \"Operation: \" . \$submission->operation . \"\\n\";
    echo \"Status: \" . \$submission->status . \"\\n\";
    echo \"Email: \" . (\$submission->data['email'] ?? 'N/A') . \"\\n\";
    echo \"Name: \" . (\$submission->data['name'] ?? 'N/A') . \"\\n\";
    if (\$submission->error_message) {
        echo \"Error: \" . \$submission->error_message . \"\\n\";
    }
    echo \"---\\n\";
}
"

# Check students created from form submissions
print_step "Checking students created from form submissions..."
php artisan tinker --execute="
\$students = App\\Models\\Student::where('created_at', '>=', now()->subMinutes(5))
    ->get();

echo \"Recent students: \" . \$students->count() . \"\\n\";

foreach (\$students as \$student) {
    echo \"ID: \" . \$student->_id . \"\\n\";
    echo \"Name: \" . \$student->name . \"\\n\";
    echo \"Email: \" . \$student->email . \"\\n\";
    echo \"Course: \" . (\$student->course ?? 'N/A') . \"\\n\";
    echo \"Phone: \" . (\$student->contact ?? 'N/A') . \"\\n\";
    echo \"---\\n\";
}
"

# Check queue status
print_step "Checking queue status..."
php artisan queue:monitor beanstalkd:form_submission_jobs --max=10 2>/dev/null || print_warning "Queue monitor unavailable"

# Show statistics
print_step "Final statistics..."
php artisan tinker --execute="
echo \"=== Form Submission Statistics ===\\n\";
echo \"Total: \" . App\\Models\\FormSubmission::count() . \"\\n\";
echo \"Queued: \" . App\\Models\\FormSubmission::where('status', 'queued')->count() . \"\\n\";
echo \"Processing: \" . App\\Models\\FormSubmission::where('status', 'processing')->count() . \"\\n\";
echo \"Completed: \" . App\\Models\\FormSubmission::where('status', 'completed')->count() . \"\\n\";
echo \"Failed: \" . App\\Models\\FormSubmission::where('status', 'failed')->count() . \"\\n\";

echo \"\\n=== Student Statistics ===\\n\";
echo \"Total Students: \" . App\\Models\\Student::count() . \"\\n\";
"

# Cleanup
if [ ! -z "$WORKER_PID" ]; then
    print_info "Stopping temporary worker..."
    kill $WORKER_PID 2>/dev/null
    rm -f worker_output.log
fi

rm -f cookies.txt

echo ""
print_status "Test completed!"
print_info "You can view the results at:"
print_info "  - Form Submissions: http://localhost:8000/form-submissions"
print_info "  - Students: http://localhost:8000/students"
print_info "  - CSV Upload: http://localhost:8000/form-submissions/csv/upload"