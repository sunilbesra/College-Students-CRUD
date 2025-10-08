#!/bin/bash

# Auto-Refresh Demo Script for Form Submissions CSV Upload
# This script demonstrates the real-time table update functionality

echo "🔄 Auto-Refresh Demo for Form Submissions"
echo "=========================================="
echo ""

# Color functions for output
print_step() { echo -e "\n\033[1;34m📋 STEP: $1\033[0m"; }
print_status() { echo -e "\033[1;32m✅ $1\033[0m"; }
print_warning() { echo -e "\033[1;33m⚠️  $1\033[0m"; }
print_info() { echo -e "\033[1;36mℹ️  $1\033[0m"; }

print_step "Demo Instructions"
print_info "1. Open your browser to: http://localhost:8000/form-submissions"
print_info "2. Notice the 'Auto-refresh ON' indicator in the header"
print_info "3. Keep the browser tab open and visible"
print_info "4. This script will upload CSV files automatically"
print_info "5. Watch the table update WITHOUT refreshing the page!"
echo ""

read -p "Press Enter when you have the browser open and ready..."

# Create demo CSV files
print_step "Creating demo CSV files"

# File 1
cat > demo_batch_1.csv << 'EOF'
name,email,phone,gender,date_of_birth,course,enrollment_date,grade,profile_image_path,address
Demo Student 1,demo1@autorefresh.com,1111111111,male,1998-01-10,Computer Science,2023-01-15,A,/images/default.jpg,111 Auto Refresh St
Demo Student 2,demo2@autorefresh.com,2222222222,female,1999-02-20,Mathematics,2023-01-15,B+,/images/default.jpg,222 Auto Refresh Ave
EOF

# File 2
cat > demo_batch_2.csv << 'EOF'
name,email,phone,gender,date_of_birth,course,enrollment_date,grade,profile_image_path,address
Demo Student 3,demo3@autorefresh.com,3333333333,male,1997-03-30,Physics,2023-02-01,A-,/images/default.jpg,333 Auto Refresh Blvd
Demo Student 4,demo4@autorefresh.com,4444444444,female,1998-04-15,Chemistry,2023-02-01,B,/images/default.jpg,444 Auto Refresh Lane
Demo Student 5,demo5@autorefresh.com,5555555555,male,1999-05-25,Biology,2023-02-01,A+,/images/default.jpg,555 Auto Refresh Circle
EOF

print_status "Demo CSV files created"

# Function to upload CSV
upload_csv() {
    local filename=$1
    local batch_name=$2
    
    print_step "Uploading $batch_name ($filename)"
    
    # Get CSRF token
    CSRF_TOKEN=$(curl -s -c cookies.txt http://localhost:8000/form-submissions/csv/upload | grep -oP 'name="_token" value="\K[^"]+' || echo "")
    
    if [ -z "$CSRF_TOKEN" ]; then
        print_warning "Failed to get CSRF token"
        return 1
    fi
    
    # Upload CSV
    RESPONSE=$(curl -s -b cookies.txt -X POST http://localhost:8000/form-submissions/csv/process \
        -F "_token=$CSRF_TOKEN" \
        -F "operation=create" \
        -F "csv_file=@$filename" \
        -w "%{http_code}")
    
    if [[ "$RESPONSE" == *"302"* ]]; then
        print_status "$batch_name uploaded successfully!"
        print_info "📱 Check your browser - the table should update automatically!"
    else
        print_warning "Upload may have failed. Response: $RESPONSE"
    fi
}

# Demo sequence
print_step "Starting Auto-Refresh Demo Sequence"
print_info "Watch your browser carefully - new data will appear automatically!"
echo ""

sleep 2

# Upload first batch
upload_csv "demo_batch_1.csv" "Batch 1 (2 students)"

print_info "⏳ Waiting 8 seconds for you to observe the auto-refresh..."
print_info "   Look for:"
print_info "   • Table data appearing without page refresh"
print_info "   • Notification popup in top-right corner"
print_info "   • Auto-refresh indicator spinning"
sleep 8

# Upload second batch
upload_csv "demo_batch_2.csv" "Batch 2 (3 students)"

print_info "⏳ Waiting 8 seconds for final observation..."
sleep 8

# Show final stats
print_step "Checking Final Results"

# Get current stats
STATS_RESPONSE=$(curl -s http://localhost:8000/form-submissions/api/stats)
if [ $? -eq 0 ]; then
    print_status "Current Statistics:"
    echo "$STATS_RESPONSE" | python3 -c "
import sys, json
try:
    data = json.load(sys.stdin)
    print(f'  Total Submissions: {data[\"total\"]}')
    print(f'  Completed: {data[\"completed\"]}')
    print(f'  CSV Uploads: {data[\"by_source\"][\"csv\"]}')
except:
    print('  Unable to parse stats')
"
fi

# Cleanup
print_step "Cleaning up demo files"
rm -f demo_batch_1.csv demo_batch_2.csv cookies.txt
print_status "Demo files cleaned up"

print_step "Demo completed!"
print_status "🎯 Key Features Demonstrated:"
echo "  ✅ Real-time table updates without page refresh"
echo "  ✅ Auto-refresh polling every 3 seconds"
echo "  ✅ Visual notification when new data arrives"
echo "  ✅ Loading indicators during refresh"
echo "  ✅ Manual refresh and pause/resume controls"
echo ""
print_info "💡 Pro Tips:"
echo "  • Use the Pause button to stop auto-refresh if needed"
echo "  • Use the Manual Refresh button for immediate updates"
echo "  • The system maintains your current filters and pagination"
echo "  • Auto-refresh works for all types of new form submissions"