#!/bin/bash

# CSV Queue Worker Script
# This script starts the Beanstalkd queue worker for processing CSV uploads

echo "🚀 Starting CSV Queue Worker..."
echo "Queue: csv_jobs"
echo "Connection: beanstalkd"
echo ""
echo "Press Ctrl+C to stop the worker"
echo "================================"
echo ""

# Change to the project directory
cd "$(dirname "$0")"

# Start the queue worker
php artisan csv:process-queue --timeout=0

echo ""
echo "✅ Queue worker stopped"