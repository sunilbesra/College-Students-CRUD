#!/bin/bash

# Unified Student Processing Worker Script
# This script starts the Beanstalkd queue workers for both CSV uploads and form submissions

echo "🎓 Starting Unified Student Processing Workers"
echo "=============================================="
echo ""

# Show configuration
echo "📋 Queue Configuration:"
echo "  • CSV Uploads: csv_jobs"
echo "  • Form Submissions: student_jobs" 
echo "  • Connection: beanstalkd"
echo ""

# Menu for queue selection
echo "Select processing mode:"
echo "1) Process both CSV and form queues (recommended)"
echo "2) Process only CSV uploads"
echo "3) Process only form submissions" 
echo ""

read -p "Enter your choice (1-3): " choice

case $choice in
    1)
        echo "🚀 Starting workers for both queues..."
        QUEUE_MODE="both"
        ;;
    2)
        echo "🚀 Starting worker for CSV uploads only..."
        QUEUE_MODE="csv_jobs"
        ;;
    3)
        echo "🚀 Starting worker for form submissions only..."
        QUEUE_MODE="student_jobs"
        ;;
    *)
        echo "Invalid choice. Using default (both queues)..."
        QUEUE_MODE="both"
        ;;
esac

echo ""
echo "Press Ctrl+C to stop the workers"
echo "================================"
echo ""

# Change to the project directory
cd "$(dirname "$0")"

# Start the queue workers
php artisan students:process-queue --queue=$QUEUE_MODE --timeout=0

echo ""
echo "✅ Queue workers stopped"