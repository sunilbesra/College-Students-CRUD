#!/bin/bash

# Debug Auto-Refresh Script
# This script helps debug the auto-refresh functionality

echo "🔍 Auto-Refresh Debug Script"
echo "============================="
echo ""

print_step() { echo -e "\n\033[1;34m📋 $1\033[0m"; }
print_status() { echo -e "\033[1;32m✅ $1\033[0m"; }
print_info() { echo -e "\033[1;36mℹ️  $1\033[0m"; }

print_step "Step 1: Open Browser Console"
print_info "1. Open http://localhost:8000/form-submissions in your browser"
print_info "2. Press F12 to open Developer Tools"
print_info "3. Go to Console tab"
print_info "4. Look for these log messages:"
echo "   - 'Auto-refresh system initializing...'"
echo "   - 'Initial submission count: X'"
echo "   - 'Starting auto-refresh polling...'"
echo "   - 'Checking submissions - Current: X, Last: Y'"
echo ""

read -p "Press Enter when you have the console open and can see the logs..."

print_step "Step 2: Current API Status"
CURRENT_API=$(curl -s http://localhost:8000/form-submissions/api/latest)
echo "Current API response: $CURRENT_API"

print_step "Step 3: Upload Test CSV"
print_info "Now I'll upload a test CSV file..."

# Create test file
cat > debug_auto_refresh.csv << 'EOF'
name,email,phone,gender,date_of_birth,course,enrollment_date,grade,profile_image_path,address
Debug Auto Refresh,debug_auto@example.com,8888888888,female,2001-12-25,Debug Course,2024-01-01,A+,/images/default.jpg,Auto Refresh Debug St
EOF

# Upload it
CSRF_TOKEN=$(curl -s -c cookies.txt http://localhost:8000/form-submissions/csv/upload | grep -oP 'name="_token" value="\K[^"]+' || echo "")

if [ -z "$CSRF_TOKEN" ]; then
    echo "❌ Failed to get CSRF token"
    exit 1
fi

curl -s -b cookies.txt -X POST http://localhost:8000/form-submissions/csv/process \
    -F "_token=$CSRF_TOKEN" \
    -F "operation=create" \
    -F "csv_file=@debug_auto_refresh.csv" > /dev/null

print_status "Test CSV uploaded!"

print_step "Step 4: Check API Response Change"
sleep 2
NEW_API=$(curl -s http://localhost:8000/form-submissions/api/latest)
echo "New API response: $NEW_API"

print_step "Step 5: What to Watch in Browser Console"
print_info "In the next 10 seconds, you should see:"
echo "   - 'Checking submissions - Current: X+1, Last: X'"
echo "   - 'New submissions detected! Refreshing table...'"
echo "   - 'Refreshing table...'"
echo "   - 'Fetching data from: [URL]'"
echo "   - 'Updating table content...'"
echo "   - 'Table refresh completed'"
echo ""
print_info "AND you should see:"
echo "   - A popup notification in the top-right"
echo "   - The debug indicator updating with new timestamp"
echo "   - The table showing the new record without manual refresh"

print_step "Waiting 10 seconds for auto-refresh to trigger..."
for i in {10..1}; do
    echo -n "$i... "
    sleep 1
done
echo ""

print_step "Debug Complete"
print_info "If you didn't see the expected behavior, check:"
echo "   1. Browser console for JavaScript errors"
echo "   2. Network tab in dev tools for failed requests"
echo "   3. The debug indicator timestamp updating"
echo "   4. Try clicking the 'Manual Refresh' button to test the mechanism"

# Cleanup
rm -f debug_auto_refresh.csv cookies.txt

print_status "Debug script completed!"