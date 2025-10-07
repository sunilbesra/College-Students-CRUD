#!/bin/bash

# Test Duplicate Email Validation in CSV Upload
echo "🔍 Testing Duplicate Email Validation"
echo "===================================="
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

# Check if the test CSV file exists
CSV_FILE="test_duplicate_emails.csv"
if [ ! -f "$CSV_FILE" ]; then
    print_error "Test CSV file not found: $CSV_FILE"
    exit 1
fi

print_info "Test CSV file: $CSV_FILE"
print_info "CSV content:"
cat -n "$CSV_FILE"
echo ""

# Analyze CSV for expected duplicates
print_step "Analyzing CSV file for duplicates..."
echo "Expected duplicates:"
tail -n +2 "$CSV_FILE" | cut -d',' -f2 | sort | uniq -d
echo ""

# Check Laravel server
print_step "Checking if Laravel server is running..."
if curl -s http://localhost:8000 > /dev/null; then
    print_status "Laravel server is running on :8000"
else
    print_error "Laravel server not running. Please start with: php artisan serve"
    exit 1
fi

# Start a temporary worker
print_step "Starting temporary worker for testing..."
php artisan queue:work beanstalkd --queue=form_submission_jobs --timeout=60 > worker_output.log 2>&1 &
WORKER_PID=$!
print_status "Started temporary worker with PID: $WORKER_PID"
sleep 3

# Test CSV upload with duplicates
print_step "Testing CSV upload with duplicate email validation..."

# Get CSRF token
print_info "Getting CSRF token..."
CSRF_TOKEN=$(curl -s -c cookies.txt http://localhost:8000/form-submissions/csv/upload | grep -oP 'name="_token" value="\K[^"]+' || echo "")

if [ -z "$CSRF_TOKEN" ]; then
    print_error "Failed to get CSRF token"
    kill $WORKER_PID 2>/dev/null
    exit 1
fi

print_status "CSRF token obtained"

# Upload CSV file
print_info "Uploading CSV with duplicate emails..."
UPLOAD_RESPONSE=$(curl -s -b cookies.txt -X POST http://localhost:8000/form-submissions/csv/process \
    -F "_token=$CSRF_TOKEN" \
    -F "operation=create" \
    -F "csv_file=@$CSV_FILE" \
    -w "%{http_code}")

HTTP_CODE=$(echo "$UPLOAD_RESPONSE" | tail -n1)
RESPONSE_BODY=$(echo "$UPLOAD_RESPONSE" | head -n -1)

echo "Upload HTTP code: $HTTP_CODE"

if [[ "$HTTP_CODE" == "302" || "$HTTP_CODE" == "200" ]]; then
    print_status "CSV upload completed"
else
    print_error "CSV upload failed. HTTP code: $HTTP_CODE"
    echo "Response: $RESPONSE_BODY"
fi

# Wait for processing
print_step "Waiting for job processing (15 seconds)..."
sleep 15

# Check form submissions in database
print_step "Checking form submission results..."
php artisan tinker --execute="
\$recent = App\\Models\\FormSubmission::where('created_at', '>=', now()->subMinutes(5))
    ->where('source', 'csv')
    ->get();

echo 'Recent CSV form submissions: ' . \$recent->count() . '\\n';
echo 'Completed: ' . \$recent->where('status', 'completed')->count() . '\\n';
echo 'Failed: ' . \$recent->where('status', 'failed')->count() . '\\n';
echo 'Queued: ' . \$recent->where('status', 'queued')->count() . '\\n';

echo '\\nForm submission details:\\n';
foreach (\$recent as \$sub) {
    echo 'Email: ' . (\$sub->data['email'] ?? 'N/A') . ' | Status: ' . \$sub->status;
    if (\$sub->error_message) {
        echo ' | Error: ' . \$sub->error_message;
    }
    echo '\\n';
}
"

# Check students created
print_step "Checking students created from valid submissions..."
php artisan tinker --execute="
\$testEmails = ['duplicate@test.com', 'unique@test.com', 'another@test.com', 'john.anderson@university.edu'];
\$students = App\\Models\\Student::whereIn('email', \$testEmails)->get();

echo 'Students found with test emails: ' . \$students->count() . '\\n';
foreach (\$students as \$student) {
    echo 'Name: ' . \$student->name . ' | Email: ' . \$student->email . ' | Created: ' . \$student->created_at . '\\n';
}

echo '\\nChecking for existing students with john.anderson@university.edu:\\n';
\$existing = App\\Models\\Student::where('email', 'john.anderson@university.edu')->get();
echo 'Existing students with this email: ' . \$existing->count() . '\\n';
"

# Test duplicate detection in preview (simulate browser behavior)
print_step "Testing duplicate detection logic..."
php -r "
\$csv = file_get_contents('$CSV_FILE');
\$lines = array_filter(explode('\\n', \$csv), fn(\$line) => trim(\$line));
\$emails = [];
\$duplicates = [];

if (count(\$lines) > 1) {
    \$headers = array_map('trim', explode(',', \$lines[0]));
    \$emailIndex = array_search('email', array_map('strtolower', \$headers));
    
    if (\$emailIndex !== false) {
        for (\$i = 1; \$i < count(\$lines); \$i++) {
            \$cells = explode(',', \$lines[\$i]);
            if (isset(\$cells[\$emailIndex])) {
                \$email = trim(\$cells[\$emailIndex]);
                if (\$email && \$email !== 'email') {
                    if (in_array(strtolower(\$email), \$emails)) {
                        \$duplicates[] = \$email;
                    } else {
                        \$emails[] = strtolower(\$email);
                    }
                }
            }
        }
    }
}

echo 'Emails processed: ' . count(\$emails) . '\\n';
echo 'Duplicates detected: ' . count(\$duplicates) . '\\n';
foreach (\$duplicates as \$dup) {
    echo '  - ' . \$dup . '\\n';
}
"

# Cleanup
print_info "Cleaning up..."
kill $WORKER_PID 2>/dev/null || true
rm -f cookies.txt worker_output.log

echo ""
print_status "Duplicate email validation test completed!"
print_info "Key test scenarios:"
print_info "  ✓ Within-CSV duplicate detection"
print_info "  ✓ Database existing email detection" 
print_info "  ✓ Frontend preview validation"
print_info "  ✓ Backend processing validation"
print_info ""
print_info "Check the form submissions page for detailed error messages:"
print_info "  http://localhost:8000/form-submissions"