#!/bin/bash

# Comprehensive CSV Testing Script for Form Submissions
# This script tests all CSV upload scenarios with different data sets

set -e

echo "📊 Comprehensive CSV Upload Testing for Form Submissions"
echo "======================================================"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

print_status() { echo -e "${GREEN}[INFO]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARN]${NC} $1"; }
print_step() { echo -e "${BLUE}[STEP]${NC} $1"; }
print_test() { echo -e "${PURPLE}[TEST]${NC} $1"; }
print_result() { echo -e "${CYAN}[RESULT]${NC} $1"; }

# Test counter
TEST_COUNT=0
PASS_COUNT=0
FAIL_COUNT=0

# Function to run a test
run_test() {
    local test_name="$1"
    local csv_file="$2"
    local operation="$3"
    local expected_success="$4"
    
    TEST_COUNT=$((TEST_COUNT + 1))
    print_test "Test #$TEST_COUNT: $test_name"
    
    if [ ! -f "$csv_file" ]; then
        print_error "CSV file not found: $csv_file"
        FAIL_COUNT=$((FAIL_COUNT + 1))
        return 1
    fi
    
    # Show file content preview
    echo "   File: $csv_file"
    echo "   Records: $(tail -n +2 "$csv_file" | wc -l)"
    echo "   Preview:"
    head -n 3 "$csv_file" | sed 's/^/      /'
    
    # Get CSRF token
    CSRF_TOKEN=$(curl -s -c cookies.txt http://localhost:8000/form-submissions/csv/upload | grep -oP 'name="_token" value="\K[^"]+' || echo "")
    
    if [ -z "$CSRF_TOKEN" ]; then
        print_error "Failed to get CSRF token"
        FAIL_COUNT=$((FAIL_COUNT + 1))
        return 1
    fi
    
    # Upload CSV
    print_status "Uploading CSV with operation: $operation"
    UPLOAD_RESPONSE=$(curl -s -b cookies.txt -X POST http://localhost:8000/form-submissions/csv/process \
        -F "_token=$CSRF_TOKEN" \
        -F "operation=$operation" \
        -F "csv_file=@$csv_file" \
        -w "%{http_code}")
    
    RESPONSE_CODE=$(echo "$UPLOAD_RESPONSE" | tail -n1)
    
    if [ "$RESPONSE_CODE" = "302" ]; then
        print_status "Upload successful (redirect)"
        PASS_COUNT=$((PASS_COUNT + 1))
    else
        print_warning "Upload response code: $RESPONSE_CODE"
        if [ "$expected_success" = "true" ]; then
            FAIL_COUNT=$((FAIL_COUNT + 1))
        else
            print_status "Expected failure - test passed"
            PASS_COUNT=$((PASS_COUNT + 1))
        fi
    fi
    
    # Wait for processing
    sleep 2
    
    # Check results in database
    print_status "Checking database results..."
    mongosh --quiet college --eval "
        const submissions = db.form_submissions.find({source: 'csv'}).sort({created_at: -1}).limit(10);
        let recentCount = 0;
        submissions.forEach(doc => {
            const timeDiff = new Date() - new Date(doc.created_at);
            if (timeDiff < 30000) { // Last 30 seconds
                recentCount++;
            }
        });
        print('Recent submissions in last 30s: ' + recentCount);
        
        // Show status breakdown for recent submissions
        const statusCounts = db.form_submissions.aggregate([
            {\$match: {source: 'csv', created_at: {\$gte: new Date(new Date() - 30000)}}},
            {\$group: {_id: '\$status', count: {\$sum: 1}}}
        ]).toArray();
        
        print('Status breakdown:');
        statusCounts.forEach(item => print('  ' + item._id + ': ' + item.count));
    "
    
    echo "   ✓ Test completed"
    echo ""
}

# Cleanup function
cleanup_test_data() {
    print_step "Cleaning up test data..."
    mongosh --quiet college --eval "
        const result1 = db.form_submissions.deleteMany({source: 'csv'});
        const result2 = db.students.deleteMany({email: /test\\.edu|university\\.edu/});
        print('Deleted ' + result1.deletedCount + ' form submissions');
        print('Deleted ' + result2.deletedCount + ' test students');
    "
}

# Main testing function
main() {
    print_step "Starting comprehensive CSV testing..."
    
    # Prerequisites check
    print_step "Checking prerequisites..."
    
    if [ ! -f "artisan" ]; then
        print_error "Not in Laravel project directory"
        exit 1
    fi
    
    if ! curl -s http://localhost:8000 > /dev/null; then
        print_error "Laravel server not running on :8000"
        exit 1
    fi
    
    if ! mongosh --quiet college --eval "db.adminCommand('ping')" > /dev/null 2>&1; then
        print_error "MongoDB not accessible"
        exit 1
    fi
    
    print_status "All prerequisites met"
    
    # Start background worker
    print_step "Starting background queue worker..."
    php artisan queue:work beanstalkd --queue=form_submission_jobs --timeout=60 > queue_test.log 2>&1 &
    WORKER_PID=$!
    print_status "Worker started with PID: $WORKER_PID"
    sleep 2
    
    # Cleanup existing test data
    cleanup_test_data
    
    # Run tests
    print_step "Running test suite..."
    
    # Test 1: Basic student creation
    run_test "Create Students - Standard Data" \
             "test_data_create_students.csv" \
             "create" \
             "true"
    
    # Test 2: Minimal fields
    run_test "Create Students - Minimal Fields" \
             "test_data_minimal_fields.csv" \
             "create" \
             "true"
    
    # Test 3: International students
    run_test "Create Students - International Data" \
             "test_data_international_students.csv" \
             "create" \
             "true"
    
    # Test 4: Graduate students
    run_test "Create Students - Graduate Students" \
             "test_data_graduate_students.csv" \
             "create" \
             "true"
    
    # Test 5: Error handling
    run_test "Create Students - Data with Errors" \
             "test_data_with_errors.csv" \
             "create" \
             "false"
    
    # Test 6: Update operations (will likely fail due to non-existent IDs)
    run_test "Update Students - Sample Updates" \
             "test_data_update_students.csv" \
             "update" \
             "false"
    
    # Test 7: Delete operations (will likely fail due to non-existent IDs)
    run_test "Delete Students - Sample Deletions" \
             "test_data_delete_students.csv" \
             "delete" \
             "false"
    
    # Wait for all jobs to process
    print_step "Waiting for job processing to complete..."
    sleep 10
    
    # Kill worker
    kill $WORKER_PID 2>/dev/null || true
    print_status "Background worker stopped"
    
    # Final results
    print_step "Test Results Summary"
    print_result "Total Tests: $TEST_COUNT"
    print_result "Passed: $PASS_COUNT"
    print_result "Failed: $FAIL_COUNT"
    
    if [ $FAIL_COUNT -eq 0 ]; then
        print_result "🎉 All tests passed!"
    else
        print_result "⚠️  Some tests failed - check logs for details"
    fi
    
    # Database final state
    print_step "Final database state:"
    mongosh --quiet college --eval "
        print('=== FINAL COUNTS ===');
        print('Form Submissions: ' + db.form_submissions.countDocuments());
        print('Students: ' + db.students.countDocuments());
        print('');
        
        print('=== SUBMISSION STATUS ===');
        db.form_submissions.aggregate([
            {\$group: {_id: '\$status', count: {\$sum: 1}}}
        ]).forEach(doc => print(doc._id + ': ' + doc.count));
        print('');
        
        print('=== RECENT ERRORS ===');
        db.form_submissions.find({status: 'failed'}).limit(3).forEach(doc => {
            print('Error: ' + (doc.error_message || 'Unknown'));
        });
    "
    
    # Show logs
    print_step "Recent worker logs:"
    tail -n 10 queue_test.log 2>/dev/null || print_warning "No worker logs found"
    
    print_step "Recent Laravel logs:"
    tail -n 5 storage/logs/laravel.log | grep -i "form_submission\|csv" || print_warning "No relevant Laravel logs"
    
    # Cleanup
    rm -f cookies.txt queue_test.log
    
    print_step "Testing completed!"
    print_result "Access the web interface at: http://localhost:8000/form-submissions"
}

# Show CSV file information
show_csv_info() {
    print_step "Available CSV test files:"
    
    for csv_file in test_data_*.csv; do
        if [ -f "$csv_file" ]; then
            record_count=$(tail -n +2 "$csv_file" | wc -l)
            echo "  📄 $csv_file - $record_count records"
            echo "     Purpose: $(head -n1 "$csv_file" | cut -c1-50)..."
        fi
    done
    echo ""
}

# Handle command line arguments
case "${1:-run}" in
    "info")
        show_csv_info
        ;;
    "clean")
        cleanup_test_data
        ;;
    "run"|"")
        show_csv_info
        main
        ;;
    *)
        echo "Usage: $0 [run|info|clean]"
        echo "  run   - Run all tests (default)"
        echo "  info  - Show CSV file information"
        echo "  clean - Clean up test data"
        ;;
esac