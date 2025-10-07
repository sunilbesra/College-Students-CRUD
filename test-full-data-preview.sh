#!/bin/bash

echo "🎯 Testing Full Data Preview in Table"
echo "===================================="

echo "🔧 Starting queue worker..."
pkill -f "queue:work" 2>/dev/null
php artisan queue:work --queue=form_submission_jobs --timeout=60 --tries=3 > /dev/null 2>&1 &
WORKER_PID=$!

echo "📊 Current submissions: $(php artisan tinker --execute="echo App\Models\FormSubmission::count();")"

echo ""
echo "🧪 Creating comprehensive test data for full preview..."

php artisan tinker --execute="
// Create submissions with complete data for preview testing
\$testData = [
    [
        'name' => 'Emma Wilson',
        'email' => 'emma.wilson@university.edu', 
        'phone' => '+1-555-0123',
        'gender' => 'female',
        'date_of_birth' => '1998-03-15',
        'course' => 'Computer Science',
        'enrollment_date' => '2024-09-01',
        'grade' => 'A+',
        'profile_image_path' => 'uploads/profiles/emma.jpg',
        'address' => '123 University Ave, Tech City, CA 90210'
    ],
    [
        'name' => 'James Rodriguez',
        'email' => 'james.rodriguez@university.edu',
        'phone' => '+1-555-0456', 
        'gender' => 'male',
        'date_of_birth' => '1997-07-22',
        'course' => 'Mechanical Engineering',
        'enrollment_date' => '2024-09-01',
        'grade' => 'B+',
        'profile_image_path' => 'uploads/profiles/james.jpg',
        'address' => '456 Engineering Blvd, Innovation District, CA 90211'
    ],
    [
        'name' => 'Sofia Chen',
        'email' => 'sofia.chen@university.edu',
        'phone' => '+1-555-0789',
        'gender' => 'female', 
        'date_of_birth' => '1999-12-08',
        'course' => 'Biomedical Sciences',
        'enrollment_date' => '2024-09-01',
        'grade' => 'A',
        'profile_image_path' => 'uploads/profiles/sofia.jpg',
        'address' => '789 Science Park Dr, Research Campus, CA 90212'
    ]
];

foreach (\$testData as \$index => \$data) {
    \$submission = App\Models\FormSubmission::create([
        'operation' => 'create',
        'source' => 'form',
        'data' => \$data,
        'status' => 'completed',
        'processed_at' => now(),
        'ip_address' => '192.168.1.' . (100 + \$index),
        'user_agent' => 'Mozilla/5.0 Test Browser'
    ]);
    
    echo 'Created: ' . \$data['name'] . ' (' . \$data['gender'] . ') - ' . \$data['course'] . PHP_EOL;
}

echo 'Test data created successfully!' . PHP_EOL;
"

echo ""
echo "⏳ Processing..."
sleep 3

echo ""
echo "📊 Results:"
php artisan tinker --execute="
\$total = App\Models\FormSubmission::count();
echo 'Total submissions: ' . \$total . PHP_EOL;
echo '' . PHP_EOL;
echo 'Recent submissions with full data:' . PHP_EOL;

App\Models\FormSubmission::orderBy('created_at', 'desc')->limit(3)->get()->each(function(\$s) {
    echo '👤 ' . (\$s->data['name'] ?? 'N/A') . PHP_EOL;
    echo '   📧 ' . (\$s->data['email'] ?? 'N/A') . PHP_EOL;
    echo '   📱 ' . (\$s->data['phone'] ?? 'N/A') . PHP_EOL;
    echo '   👫 ' . ucfirst(\$s->data['gender'] ?? 'N/A') . PHP_EOL;
    echo '   🎓 ' . (\$s->data['course'] ?? 'N/A') . PHP_EOL;
    echo '   📝 Grade: ' . (\$s->data['grade'] ?? 'N/A') . PHP_EOL;
    echo '   🏠 ' . (\$s->data['address'] ?? 'N/A') . PHP_EOL;
    echo '   ---' . PHP_EOL;
});
"

echo ""
echo "🧹 Cleanup..."
kill $WORKER_PID 2>/dev/null

echo ""
echo "✅ Full Data Preview Test Complete!"
echo ""
echo "📝 Enhanced Table Features:"
echo "   👤 Profile images (40x40 rounded)"
echo "   📛 Name prominently displayed"
echo "   🏷️  Gender badges (blue for male, pink for female)"
echo "   📧 Email with envelope icon"  
echo "   📱 Phone with phone icon"
echo "   🎓 Course and grade badges"
echo "   📅 Birth date and enrollment date"
echo "   🏠 Address (truncated to 50 chars)"
echo "   🎨 Clean, organized layout"
echo ""
echo "🌐 View the enhanced table at: http://localhost:8000/form-submissions"