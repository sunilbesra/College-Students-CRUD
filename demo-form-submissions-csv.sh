#!/bin/bash

# Form Submissions CSV Upload Demo Script
# Demonstrates the CSV upload functionality for form_submissions table

echo "🎓 Form Submissions CSV Upload Demo"
echo "====================================="
echo ""

# Color functions for output
print_step() { echo -e "\n\033[1;34m📋 STEP: $1\033[0m"; }
print_status() { echo -e "\033[1;32m✅ $1\033[0m"; }
print_warning() { echo -e "\033[1;33m⚠️  $1\033[0m"; }
print_error() { echo -e "\033[1;31m❌ $1\033[0m"; }

# Prerequisites check
print_step "Checking Prerequisites"

# Check Laravel
if ! php artisan --version > /dev/null 2>&1; then
    print_error "Laravel not found. Please run from project directory."
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

# Clean up old test data
print_step "Cleaning up old test data"
mongosh --quiet college --eval "
    db.form_submissions.deleteMany({'data.email': /csv_demo.*@example\.com/});
    db.students.deleteMany({'email': /csv_demo.*@example\.com/});
    print('Cleanup completed');
"

# Create sample CSV file
print_step "Creating sample CSV file for form submissions"
cat > form_submissions_demo.csv << 'EOF'
name,email,phone,gender,date_of_birth,course,enrollment_date,grade,profile_image_path,address
John CSV Demo,csv_demo_1@example.com,1234567890,male,1995-01-15,Computer Science,2020-09-01,A,/images/default-avatar.jpg,123 CSV Demo Street
Jane CSV Demo,csv_demo_2@example.com,9876543210,female,1996-03-22,Mathematics,2020-09-01,B+,/images/default-avatar.jpg,456 CSV Demo Avenue
Mike CSV Demo,csv_demo_3@example.com,1122334455,male,1995-07-10,Physics,2020-09-01,A-,/images/default-avatar.jpg,789 CSV Demo Road
Sarah CSV Demo,csv_demo_4@example.com,2233445566,female,1997-12-05,Chemistry,2021-01-15,A,/images/default-avatar.jpg,321 CSV Demo Lane
Alex CSV Demo,csv_demo_5@example.com,3344556677,male,1996-08-20,Biology,2020-08-30,B,/images/default-avatar.jpg,654 CSV Demo Circle
EOF

print_status "Sample CSV file created: form_submissions_demo.csv"
echo ""
echo "📄 CSV Content Preview:"
head -3 form_submissions_demo.csv
echo "... (5 total records)"

# Start queue worker in background
print_step "Starting queue worker for form submissions"
php artisan queue:work beanstalkd --queue=form_submission_jobs --tries=3 --timeout=60 --verbose > queue_worker.log 2>&1 &
WORKER_PID=$!
print_status "Queue worker started with PID: $WORKER_PID"

# Wait a moment for worker to initialize
sleep 2

# Get CSRF token
print_step "Preparing CSV upload"
CSRF_TOKEN=$(curl -s -c cookies.txt http://localhost:8000/form-submissions/csv/upload | grep -oP 'name="_token" value="\K[^"]+' || echo "")

if [ -z "$CSRF_TOKEN" ]; then
    print_error "Failed to get CSRF token. Is Laravel server running on :8000?"
    kill $WORKER_PID 2>/dev/null || true
    exit 1
fi

print_status "CSRF token obtained: ${CSRF_TOKEN:0:20}..."

# Upload CSV
print_step "Uploading CSV file for form submissions"
UPLOAD_RESPONSE=$(curl -s -b cookies.txt -X POST http://localhost:8000/form-submissions/csv/process \
    -F "_token=$CSRF_TOKEN" \
    -F "operation=create" \
    -F "csv_file=@form_submissions_demo.csv" \
    -w "\n%{http_code}")

RESPONSE_CODE=$(echo "$UPLOAD_RESPONSE" | tail -n1)
echo "Upload response code: $RESPONSE_CODE"

if [[ "$RESPONSE_CODE" == "302" ]]; then
    print_status "CSV upload successful (redirected to form submissions list)"
else
    print_warning "Unexpected response code: $RESPONSE_CODE"
fi

# Wait for processing
print_step "Waiting for job processing"
sleep 10

# Check form submissions
print_step "Verifying form submissions were created"
SUBMISSIONS_COUNT=$(mongosh --quiet college --eval "db.form_submissions.countDocuments({'data.email': /csv_demo.*@example\.com/})")
print_status "Form submissions created: $SUBMISSIONS_COUNT"

# Check if students were created (if the unified architecture is working)
STUDENTS_COUNT=$(mongosh --quiet college --eval "db.students.countDocuments({'email': /csv_demo.*@example\.com/})")
print_status "Students created: $STUDENTS_COUNT"

# Show recent form submissions
print_step "Displaying recent form submissions"
mongosh --quiet college --eval "
    db.form_submissions.find(
        {'data.email': /csv_demo.*@example\.com/},
        {operation: 1, 'data.name': 1, 'data.email': 1, source: 1, status: 1, created_at: 1}
    ).sort({created_at: -1}).forEach(doc => 
        print('  ' + doc.data.name + ' (' + doc.data.email + ') - ' + doc.status)
    );
"

# Show statistics
print_step "Form Submissions Statistics"
STATS_RESPONSE=$(curl -s http://localhost:8000/form-submissions/api/stats)
if [ $? -eq 0 ]; then
    echo "$STATS_RESPONSE" | python3 -m json.tool 2>/dev/null || echo "$STATS_RESPONSE"
else
    print_error "Failed to fetch statistics"
fi

# Kill queue worker
print_step "Stopping queue worker"
kill $WORKER_PID 2>/dev/null || true
print_status "Queue worker stopped"

# Cleanup
rm -f cookies.txt queue_worker.log

print_step "Demo completed successfully!"
print_status "📋 Features demonstrated:"
echo "  ✅ CSV file upload interface"
echo "  ✅ CSV validation and processing"
echo "  ✅ Form submission record creation"
echo "  ✅ Queue-based background processing"
echo "  ✅ Status tracking and error handling"
echo "  ✅ Statistics and monitoring"
echo ""
print_status "🌐 Access points:"
echo "  • Form Submissions List: http://localhost:8000/form-submissions"
echo "  • CSV Upload Page: http://localhost:8000/form-submissions/csv/upload"
echo "  • Statistics API: http://localhost:8000/form-submissions/api/stats"
echo ""
print_status "📁 Sample file created: form_submissions_demo.csv"
echo "  You can use this file to test the upload interface manually."