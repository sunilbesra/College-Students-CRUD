#!/bin/bash

# Form Submission System Demo Script
# This script demonstrates CRUD and CSV upload functionality for the form_submission table

set -e

echo "🚀 Starting Form Submission System Demo"
echo "======================================"

# Colors for better output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_step() {
    echo -e "${BLUE}[STEP]${NC} $1"
}

# Check if Laravel is running
check_laravel() {
    print_step "Checking if Laravel application is accessible..."
    if curl -s http://localhost:8000 > /dev/null 2>&1; then
        print_status "Laravel application is running on http://localhost:8000"
    else
        print_error "Laravel application is not accessible. Please start it with 'php artisan serve'"
        exit 1
    fi
}

# Check Beanstalkd
check_beanstalkd() {
    print_step "Checking Beanstalkd connection..."
    if nc -z localhost 11300 2>/dev/null; then
        print_status "Beanstalkd is running on port 11300"
    else
        print_error "Beanstalkd is not running. Please start it with 'beanstalkd -l 127.0.0.1 -p 11300'"
        exit 1
    fi
}

# Check MongoDB
check_mongodb() {
    print_step "Checking MongoDB connection..."
    if mongosh --quiet --eval "db.adminCommand('ping')" college > /dev/null 2>&1; then
        print_status "MongoDB is accessible"
    else
        print_error "MongoDB is not accessible. Please ensure MongoDB is running and configured correctly."
        exit 1
    fi
}

# Start Laravel queue worker in background
start_worker() {
    print_step "Starting Laravel queue worker..."
    
    # Kill any existing workers
    pkill -f "artisan queue:work" || true
    sleep 2
    
    # Start new worker in background
    php artisan queue:work beanstalkd --queue=form_submission_jobs,student_jobs,csv_jobs --timeout=300 --sleep=3 --tries=3 > queue_worker.log 2>&1 &
    WORKER_PID=$!
    
    print_status "Queue worker started with PID: $WORKER_PID"
    sleep 3
}

# Create sample CSV file for form submissions
create_sample_csv() {
    print_step "Creating sample CSV file for form submissions..."
    
    cat > sample_form_submissions.csv << 'EOF'
name,email,phone,address,date_of_birth,course,enrollment_date,grade,profile_image
John Doe,john.doe@example.com,+1234567890,"123 Main St, City, State",1995-06-15,Computer Science,2023-09-01,A,uploads/john.jpg
Jane Smith,jane.smith@example.com,+0987654321,"456 Oak Ave, Town, Province",1996-03-22,Mathematics,2023-09-01,A-,uploads/jane.jpg
Michael Johnson,mike.johnson@example.com,+1122334455,"789 Pine Rd, Village, County",1994-11-08,Physics,2023-09-01,B+,uploads/mike.jpg
Emily Davis,emily.davis@example.com,+5566778899,"321 Elm St, Hamlet, Region",1997-01-30,Chemistry,2023-09-01,A+,uploads/emily.jpg
Robert Wilson,robert.wilson@example.com,+9988776655,"654 Maple Dr, Borough, Territory",1995-09-12,Biology,2023-09-01,B,uploads/robert.jpg
EOF

    print_status "Sample CSV file created: sample_form_submissions.csv"
}

# Test CRUD operations via curl
test_crud_operations() {
    print_step "Testing Form Submission CRUD operations..."
    
    # Get CSRF token
    print_status "Getting CSRF token..."
    CSRF_TOKEN=$(curl -s -c cookies.txt http://localhost:8000/form-submissions/create | grep -oP 'name="_token" value="\K[^"]+' || echo "")
    
    if [ -z "$CSRF_TOKEN" ]; then
        print_error "Failed to get CSRF token"
        return 1
    fi
    
    print_status "CSRF Token obtained: ${CSRF_TOKEN:0:20}..."
    
    # Create a form submission
    print_status "Creating a new form submission..."
    CREATE_RESPONSE=$(curl -s -b cookies.txt -X POST http://localhost:8000/form-submissions \
        -d "_token=$CSRF_TOKEN" \
        -d "operation=create" \
        -d "source=form" \
        -d "data[name]=Test User" \
        -d "data[email]=test.user@example.com" \
        -d "data[phone]=+1234567890" \
        -d "data[course]=Computer Science" \
        -d "data[grade]=A" \
        -w "%{http_code}")
    
    if [[ "$CREATE_RESPONSE" == *"302"* ]]; then
        print_status "Form submission created successfully"
    else
        print_warning "Form submission creation response: $CREATE_RESPONSE"
    fi
    
    # Wait for processing
    sleep 5
    
    # Check form submissions list
    print_status "Checking form submissions list..."
    LIST_RESPONSE=$(curl -s http://localhost:8000/form-submissions -w "%{http_code}")
    
    if [[ "$LIST_RESPONSE" == *"200"* ]]; then
        print_status "Form submissions list accessible"
    else
        print_error "Failed to access form submissions list"
    fi
}

# Test CSV upload
test_csv_upload() {
    print_step "Testing CSV upload functionality..."
    
    # Get CSRF token for CSV upload
    CSRF_TOKEN=$(curl -s -c cookies.txt http://localhost:8000/form-submissions/csv/upload | grep -oP 'name="_token" value="\K[^"]+' || echo "")
    
    if [ -z "$CSRF_TOKEN" ]; then
        print_error "Failed to get CSRF token for CSV upload"
        return 1
    fi
    
    print_status "Uploading CSV file with form submissions..."
    UPLOAD_RESPONSE=$(curl -s -b cookies.txt -X POST http://localhost:8000/form-submissions/csv/process \
        -F "_token=$CSRF_TOKEN" \
        -F "operation=create" \
        -F "csv_file=@sample_form_submissions.csv" \
        -w "%{http_code}")
    
    if [[ "$UPLOAD_RESPONSE" == *"302"* ]]; then
        print_status "CSV upload successful"
    else
        print_warning "CSV upload response: $UPLOAD_RESPONSE"
    fi
    
    # Wait for processing
    sleep 10
}

# Monitor queue and database
monitor_processing() {
    print_step "Monitoring processing status..."
    
    # Check queue status
    print_status "Checking queue jobs..."
    php artisan queue:monitor beanstalkd:form_submission_jobs,beanstalkd:student_jobs --max=10 || true
    
    # Check form submissions in database
    print_status "Checking form submissions in database..."
    mongosh --quiet college --eval "
        print('Form Submissions Count: ' + db.form_submissions.countDocuments());
        print('By Status:');
        db.form_submissions.aggregate([
            {\$group: {_id: '\$status', count: {\$sum: 1}}}
        ]).forEach(doc => print('  ' + doc._id + ': ' + doc.count));
        print('By Operation:');
        db.form_submissions.aggregate([
            {\$group: {_id: '\$operation', count: {\$sum: 1}}}
        ]).forEach(doc => print('  ' + doc._id + ': ' + doc.count));
        print('By Source:');
        db.form_submissions.aggregate([
            {\$group: {_id: '\$source', count: {\$sum: 1}}}
        ]).forEach(doc => print('  ' + doc._id + ': ' + doc.count));
    "
    
    # Check students created
    print_status "Checking students in database..."
    mongosh --quiet college --eval "
        print('Students Count: ' + db.students.countDocuments());
        print('Recent Students:');
        db.students.find().sort({created_at: -1}).limit(3).forEach(doc => 
            print('  ' + doc.name + ' (' + doc.email + ')')
        );
    "
}

# Show statistics
show_statistics() {
    print_step "Fetching system statistics..."
    
    STATS_RESPONSE=$(curl -s http://localhost:8000/form-submissions/api/stats)
    if [ $? -eq 0 ]; then
        print_status "Form Submission Statistics:"
        echo "$STATS_RESPONSE" | python3 -m json.tool 2>/dev/null || echo "$STATS_RESPONSE"
    else
        print_error "Failed to fetch statistics"
    fi
}

# Cleanup function
cleanup() {
    print_step "Cleaning up..."
    
    # Kill queue worker
    if [ ! -z "$WORKER_PID" ]; then
        kill $WORKER_PID 2>/dev/null || true
        print_status "Queue worker stopped"
    fi
    
    # Remove temporary files
    rm -f cookies.txt sample_form_submissions.csv queue_worker.log
    
    print_status "Cleanup completed"
}

# Main execution
main() {
    # Trap cleanup on exit
    trap cleanup EXIT
    
    # Pre-flight checks
    check_laravel
    check_beanstalkd
    check_mongodb
    
    # Start worker
    start_worker
    
    # Create sample data
    create_sample_csv
    
    # Run tests
    test_crud_operations
    test_csv_upload
    
    # Monitor results
    sleep 15  # Wait for processing
    monitor_processing
    show_statistics
    
    print_step "Demo completed successfully!"
    print_status "You can now access:"
    print_status "  - Form Submissions: http://localhost:8000/form-submissions"
    print_status "  - Students: http://localhost:8000/students"
    print_status "  - CSV Upload: http://localhost:8000/form-submissions/csv/upload"
    print_status "  - Statistics API: http://localhost:8000/form-submissions/api/stats"
}

# Run if executed directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi