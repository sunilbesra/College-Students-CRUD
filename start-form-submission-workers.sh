#!/bin/bash

# Form Submission Queue Worker Script
# Starts dedicated workers for processing form submissions

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

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

# Check if Laravel application exists
if [ ! -f "artisan" ]; then
    print_error "Laravel artisan file not found. Please run from Laravel project root."
    exit 1
fi

# Check Beanstalkd connection
print_step "Checking Beanstalkd connection..."
if ! nc -z localhost 11300 2>/dev/null; then
    print_error "Beanstalkd is not running on port 11300"
    print_warning "Please start Beanstalkd with: beanstalkd -l 127.0.0.1 -p 11300"
    exit 1
fi

print_status "Beanstalkd is running"

# Kill any existing workers
print_step "Stopping any existing queue workers..."
pkill -f "artisan queue:work" || true
sleep 3

# Create logs directory
mkdir -p storage/logs/workers

# Function to start a worker
start_worker() {
    local queue_name=$1
    local worker_name=$2
    local log_file="storage/logs/workers/${worker_name}.log"
    
    print_status "Starting $worker_name worker for queue: $queue_name"
    
    php artisan queue:work beanstalkd \
        --queue="$queue_name" \
        --name="$worker_name" \
        --timeout=300 \
        --sleep=3 \
        --tries=3 \
        --max-time=3600 \
        --memory=512 \
        --verbose > "$log_file" 2>&1 &
    
    local pid=$!
    echo $pid > "storage/logs/workers/${worker_name}.pid"
    print_status "$worker_name started with PID: $pid (log: $log_file)"
}

# Start workers for different queues
print_step "Starting Form Submission Queue Workers..."

# Form submission processing worker
start_worker "form_submission_jobs" "form-submission-worker"

# Student processing worker (for existing functionality)
start_worker "student_jobs" "student-worker"

# CSV processing worker
start_worker "csv_jobs" "csv-worker"

# High priority worker for all queues
start_worker "form_submission_jobs,student_jobs,csv_jobs,default" "priority-worker"

print_step "All workers started successfully!"

# Show worker status
print_status "Active Workers:"
ps aux | grep "queue:work" | grep -v grep | while read line; do
    echo "  $line"
done

# Show queue status
print_step "Current Queue Status:"
php artisan queue:monitor beanstalkd:form_submission_jobs,beanstalkd:student_jobs,beanstalkd:csv_jobs --max=50 2>/dev/null || true

print_step "Worker Management Commands:"
echo "  Monitor queues: php artisan queue:monitor beanstalkd:form_submission_jobs"
echo "  View failed jobs: php artisan queue:failed"
echo "  Retry failed jobs: php artisan queue:retry all"
echo "  Clear failed jobs: php artisan queue:flush"
echo "  Stop all workers: pkill -f 'artisan queue:work'"

print_step "Log Files Location:"
echo "  Worker logs: storage/logs/workers/"
echo "  Main log: storage/logs/laravel.log"

print_step "Monitoring URLs:"
echo "  Form Submissions: http://localhost:8000/form-submissions"
echo "  Statistics API: http://localhost:8000/form-submissions/api/stats"

# Keep script running and monitor workers
print_step "Monitoring workers (Ctrl+C to stop)..."

# Function to handle shutdown
cleanup() {
    print_step "Shutting down workers..."
    pkill -f "artisan queue:work" || true
    rm -f storage/logs/workers/*.pid
    print_status "All workers stopped"
    exit 0
}

trap cleanup SIGINT SIGTERM

# Monitor loop
while true; do
    sleep 30
    
    # Check if workers are still running
    active_workers=0
    for pidfile in storage/logs/workers/*.pid; do
        if [ -f "$pidfile" ]; then
            pid=$(cat "$pidfile")
            if kill -0 "$pid" 2>/dev/null; then
                active_workers=$((active_workers + 1))
            else
                print_warning "Worker with PID $pid has stopped"
                rm -f "$pidfile"
            fi
        fi
    done
    
    if [ $active_workers -eq 0 ]; then
        print_error "All workers have stopped. Exiting..."
        exit 1
    fi
    
    # Show brief status every 5 minutes
    if [ $(($(date +%s) % 300)) -eq 0 ]; then
        print_status "Workers running: $active_workers"
    fi
done