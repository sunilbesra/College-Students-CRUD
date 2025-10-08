<?php

require 'bootstrap/app.php';
use App\Models\FormSubmission;
use App\Services\FormSubmissionValidator;

$app = $app ?? require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing FormSubmissionValidator...\n";

$testSubmission = FormSubmission::find('68e51c357b5b02fea6022472');
if (!$testSubmission) {
    echo "Submission not found!\n";
    exit(1);
}

echo "Submission ID: {$testSubmission->_id}\n";
echo "Submission ID type: " . gettype($testSubmission->_id) . "\n";
echo "Email: {$testSubmission->data['email']}\n";

// Test validation WITHOUT ignoreId
echo "\nTesting validation WITHOUT ignoreId (should fail):\n";
try {
    $result = FormSubmissionValidator::validate($testSubmission->data);
    echo "✅ Validation passed (unexpected)\n";
} catch (Exception $e) {
    echo "❌ Validation failed: " . $e->getMessage() . "\n";
}

// Test validation WITH ignoreId
echo "\nTesting validation WITH ignoreId (should pass):\n";
try {
    $result = FormSubmissionValidator::validate($testSubmission->data, $testSubmission->_id);
    echo "✅ Validation passed - The fix is working!\n";
} catch (Exception $e) {
    echo "❌ Validation failed: " . $e->getMessage() . "\n";
}

// Check what type of ID we're dealing with
echo "\nObjectId debug info:\n";
echo "Raw ID: " . var_export($testSubmission->_id, true) . "\n";
echo "String ID: " . (string) $testSubmission->_id . "\n";

if (is_object($testSubmission->_id)) {
    echo "ObjectId class: " . get_class($testSubmission->_id) . "\n";
}