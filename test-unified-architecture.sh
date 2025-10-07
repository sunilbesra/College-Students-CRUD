#!/bin/bash

# Test Script for Unified Student Processing Architecture
echo "🧪 Testing Unified Student Processing Architecture"
echo "================================================"
echo ""

cd "$(dirname "$0")"

# Test 1: Check if required services are running
echo "1. Checking required services..."

# Check Beanstalkd
if nc -z localhost 11300 2>/dev/null; then
    echo "   ✅ Beanstalkd is running on port 11300"
else
    echo "   ❌ Beanstalkd is not running. Please start it first."
    exit 1
fi

# Check MongoDB
if nc -z localhost 27017 2>/dev/null; then
    echo "   ✅ MongoDB is running on port 27017"
else
    echo "   ❌ MongoDB is not running. Please start it first."
    exit 1
fi

# Test 2: Verify Laravel configuration
echo ""
echo "2. Verifying Laravel configuration..."

# Check if we're in a Laravel project
if [ ! -f "artisan" ]; then
    echo "   ❌ Not in a Laravel project directory"
    exit 1
fi
echo "   ✅ Laravel project detected"

# Check .env file
if [ ! -f ".env" ]; then
    echo "   ❌ .env file not found"
    exit 1
fi
echo "   ✅ Environment configuration found"

# Test 3: Verify queue configuration
echo ""
echo "3. Verifying queue configuration..."

if grep -q "QUEUE_CONNECTION=beanstalkd" .env; then
    echo "   ✅ Beanstalkd connection configured"
else
    echo "   ⚠️  Beanstalkd connection not configured in .env"
fi

if grep -q "BEANSTALKD_QUEUE=csv_jobs" .env; then
    echo "   ✅ CSV jobs queue configured"
else
    echo "   ⚠️  CSV jobs queue not configured"
fi

if grep -q "BEANSTALKD_STUDENT_QUEUE=student_jobs" .env; then
    echo "   ✅ Student jobs queue configured"
else
    echo "   ⚠️  Student jobs queue not configured"
fi

# Test 4: Check unified job class
echo ""
echo "4. Verifying unified job implementation..."

if [ -f "app/Jobs/ProcessStudentData.php" ]; then
    echo "   ✅ ProcessStudentData job found"
    
    # Check for syntax errors
    if php -l app/Jobs/ProcessStudentData.php > /dev/null 2>&1; then
        echo "   ✅ ProcessStudentData job syntax is valid"
    else
        echo "   ❌ ProcessStudentData job has syntax errors"
        exit 1
    fi
else
    echo "   ❌ ProcessStudentData job not found"
    exit 1
fi

# Test 5: Check unified command
echo ""
echo "5. Verifying unified queue command..."

if php artisan students:process-queue --help > /dev/null 2>&1; then
    echo "   ✅ Unified queue command is available"
else
    echo "   ❌ Unified queue command not available"
    exit 1
fi

# Test 6: Verify models
echo ""
echo "6. Verifying data models..."

if [ -f "app/Models/Student.php" ]; then
    echo "   ✅ Student model found"
else
    echo "   ❌ Student model not found"
fi

if [ -f "app/Models/CsvJob.php" ]; then
    echo "   ✅ CsvJob model found"
else
    echo "   ❌ CsvJob model not found"
fi

# Test 7: Check sample data
echo ""
echo "7. Verifying sample data..."

if [ -f "sample_students.csv" ]; then
    echo "   ✅ Sample CSV file found"
    LINES=$(wc -l < sample_students.csv)
    echo "   📊 Sample CSV contains $LINES lines"
else
    echo "   ⚠️  Sample CSV file not found"
fi

# Test 8: Architecture flow test
echo ""
echo "8. Architecture Flow Summary:"
echo ""
echo "   📋 Form Submissions Flow:"
echo "      Form Data → StudentController → ProcessStudentData Job → student_jobs Queue → MongoDB"
echo ""
echo "   📄 CSV Upload Flow:"  
echo "      CSV File → CsvController → CsvJob Records → CsvBatchQueued Event → ProcessStudentData Jobs → csv_jobs Queue → MongoDB"
echo ""
echo "   🔄 Both flows use:"
echo "      • Same ProcessStudentData job"
echo "      • Same StudentValidator"
echo "      • Same Student model for MongoDB"
echo "      • Same event system (StudentCreated, StudentUpdated, StudentDeleted)"
echo ""

# Test 9: Quick functionality test
echo "9. Quick functionality test..."

echo "   🧪 Testing Artisan commands..."
if php artisan list | grep -q "students:process-queue"; then
    echo "      ✅ students:process-queue command registered"
else
    echo "      ❌ students:process-queue command not registered"
fi

if php artisan list | grep -q "csv:process-queue"; then
    echo "      ✅ csv:process-queue command available (legacy)"
else
    echo "      ⚠️  csv:process-queue command not available"
fi

echo ""
echo "🎉 Architecture Verification Complete!"
echo ""
echo "📋 Next Steps:"
echo "   1. Start the unified workers: ./start-student-workers.sh"
echo "   2. Start the web server: php artisan serve"
echo "   3. Test form submissions at: http://localhost:8000/students/create"
echo "   4. Test CSV uploads at: http://localhost:8000/upload-csv"
echo ""
echo "📚 Documentation:"
echo "   • Unified Architecture: UNIFIED_ARCHITECTURE.md"
echo "   • CSV Upload Guide: CSV_UPLOAD_README.md"
echo ""

# Final recommendation
echo "💡 Recommended workflow:"
echo "   Terminal 1: ./start-student-workers.sh (choose option 1 for both queues)"
echo "   Terminal 2: php artisan serve"
echo "   Browser: http://localhost:8000"