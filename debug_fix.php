<?php
// Let's test our validation step by step to identify the exact problem

require_once 'bootstrap/app.php';

$app = $app ?? require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\FormSubmissionValidator;
use App\Models\FormSubmission;

echo "=== DEBUG: Step-by-step validation test ===\n";

// Test data that matches our failing case
$testData = [
    'name' => 'Debug Fix Test',
    'email' => 'debug.fix.test.' . time() . '@example.com',
    'phone' => '5555551111',
    'gender' => 'male',
    'course' => 'Computer Science',
    'date_of_birth' => '2000-01-01',
    'address' => '123 Test Street',
    'city' => 'Test City',
    'state' => 'Test State',
    'zip_code' => '12345',
    'enrollment_date' => '2024-01-01'
];

echo "Email being tested: " . $testData['email'] . "\n";

// Step 1: Test findDuplicateEmail directly
echo "\nStep 1: Testing findDuplicateEmail directly...\n";
$duplicate = FormSubmissionValidator::findDuplicateEmail($testData['email']);
if ($duplicate) {
    echo "❌ Found unexpected duplicate: " . $duplicate->_id . "\n";
} else {
    echo "✅ No duplicates found (correct)\n";
}

// Step 2: Test basic validation rules (without duplicate check)
echo "\nStep 2: Testing basic validation rules...\n";
$validator = \Illuminate\Support\Facades\Validator::make($testData, FormSubmissionValidator::rules());
if ($validator->fails()) {
    echo "❌ Basic validation failed:\n";
    foreach ($validator->errors()->all() as $error) {
        echo "   - $error\n";
    }
} else {
    echo "✅ Basic validation passed\n";
}

// Step 3: Test full validation (with duplicate check)
echo "\nStep 3: Testing full FormSubmissionValidator::validate()...\n";
try {
    $result = FormSubmissionValidator::validate($testData);
    echo "✅ Full validation passed!\n";
    echo "Validated email: " . $result['email'] . "\n";
} catch (Exception $e) {
    echo "❌ Full validation failed: " . $e->getMessage() . "\n";
    echo "This reveals where the problem is!\n";
}

echo "\n=== END DEBUG ===\n";