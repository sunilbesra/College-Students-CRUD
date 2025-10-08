#!/bin/bash

echo "🔍 Auto-Refresh Test"
echo "===================="

echo "📊 Current count:"
curl -s http://localhost:8000/form-submissions/api/latest

echo ""
echo "🌐 Browser Test:"
echo "1. Open http://localhost:8000/form-submissions"
echo "2. Press F12 for Developer Tools" 
echo "3. Go to Console tab"
echo "4. Upload a CSV and watch console messages"

read -p "Press Enter to continue..."

# Simple upload test
cat > test_simple.csv << 'EOF'
name,email,phone,gender,date_of_birth,course,enrollment_date,grade,profile_image_path,address
Simple Test,simple@test.com,9999999999,male,2002-09-15,Test Course,2023-05-01,B,/images/default.jpg,999 Test Ave
EOF

CSRF_TOKEN=$(curl -s -c cookies.txt http://localhost:8000/form-submissions/csv/upload | grep -oP 'name="_token" value="\K[^"]+' || echo "")
curl -s -b cookies.txt -X POST http://localhost:8000/form-submissions/csv/process \
    -F "_token=$CSRF_TOKEN" \
    -F "operation=create" \
    -F "csv_file=@test_simple.csv" > /dev/null

echo "✅ Test CSV uploaded"
echo "📊 New count:"
curl -s http://localhost:8000/form-submissions/api/latest

rm -f test_simple.csv cookies.txt