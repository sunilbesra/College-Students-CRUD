#!/bin/bash

# CSV Upload Demo Script
# This script demonstrates the CSV upload functionality

echo "🎓 CSV Upload Demo for Student Management System"
echo "=============================================="
echo ""

# Change to the project directory
cd "$(dirname "$0")"

echo "📋 Demo Overview:"
echo "1. A sample CSV file has been created: sample_students.csv"
echo "2. The system will process CSV uploads asynchronously using Beanstalkd queues"
echo "3. You can monitor progress in real-time on the upload page"
echo ""

echo "🔧 Prerequisites Check:"

# Check if Laravel is ready
if [ ! -f "artisan" ]; then
    echo "❌ Not in a Laravel project directory"
    exit 1
fi

echo "✅ Laravel project found"

# Check if .env exists and has required config
if [ ! -f ".env" ]; then
    echo "❌ .env file not found"
    exit 1
fi

echo "✅ Configuration file found"

# Check if MongoDB is configured
if ! grep -q "DB_CONNECTION=mongodb" .env; then
    echo "⚠️  MongoDB not configured in .env"
else
    echo "✅ MongoDB configured"
fi

# Check if Beanstalkd queue is configured
if grep -q "QUEUE_CONNECTION=beanstalkd" .env && grep -q "BEANSTALKD_QUEUE=csv_jobs" .env; then
    echo "✅ Beanstalkd queue configured for CSV jobs"
else
    echo "⚠️  Beanstalkd queue not properly configured"
fi

echo ""
echo "🚀 Getting Started:"
echo "1. Start the development server:"
echo "   php artisan serve"
echo ""
echo "2. In a new terminal, start the unified student processing workers:"
echo "   ./start-student-workers.sh"
echo "   (or use the legacy CSV-only worker: ./start-csv-worker.sh)"
echo ""
echo "3. Open your browser and navigate to:"
echo "   http://localhost:8000"
echo ""
echo "4. Click 'Upload CSV' and upload the sample_students.csv file"
echo ""
echo "5. Monitor the processing progress on the upload page"
echo ""

echo "📄 Sample CSV Content:"
echo "The sample_students.csv file contains:"
head -6 sample_students.csv 2>/dev/null || echo "Sample CSV file not found. Please run this script from the project directory."

echo ""
echo "🔍 CSV File Format Requirements:"
echo "- Header row is required with these columns:"
echo "  name, email, contact, address, college, gender, dob, enrollment_status, course, agreed_to_terms"
echo "- email must be unique"
echo "- name, email, contact, address, college are required fields"
echo "- gender: male, female, other (optional)"
echo "- enrollment_status: full_time, part_time (optional)"
echo "- dob format: YYYY-MM-DD (optional)"
echo "- agreed_to_terms: 1, true, yes, on (optional)"
echo ""

echo "⚡ Unified Processing Features:"
echo "- Both CSV uploads and form submissions use the same queue architecture"
echo "- Form → Beanstalkd → Laravel Consumer → Validation → MongoDB"
echo "- CSV → Beanstalkd → Laravel Consumer → Validation → MongoDB"
echo "- Asynchronous processing using Beanstalkd queues"
echo "- Real-time progress monitoring for CSV uploads"
echo "- Individual validation and error handling"
echo "- Automatic retry on failures with exponential backoff"
echo "- Unified logging and error reporting"
echo "- External mirroring to Aurora/other consumers"
echo ""

echo "🛠️  Troubleshooting:"
echo "- If jobs are not processing, ensure Beanstalkd is running: systemctl status beanstalkd"
echo "- Check Laravel logs: tail -f storage/logs/laravel.log"
echo "- Verify queue connection: php artisan queue:monitor beanstalkd"
echo ""

echo "📚 For detailed documentation, see: CSV_UPLOAD_README.md"
echo ""

# Offer to start services
read -p "Would you like to start the development server now? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "Starting Laravel development server..."
    echo "Open another terminal and run: ./start-student-workers.sh"
    echo "Then visit: http://localhost:8000"
    echo ""
    echo "The unified workers will process:"
    echo "• Student form submissions (create/update/delete)"
    echo "• CSV upload processing"
    echo "• Both use the same validation and MongoDB storage"
    php artisan serve
fi