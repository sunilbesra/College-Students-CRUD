<?php
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Queue;
use App\Jobs\ProcessFormSubmissionData;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Http\Kernel');

// First, submit a form with a new email
$formData1 = [
    'operation' => 'create',
    'source' => 'form_submission',
    'data' => [
        'name' => 'John Doe',
        'email' => 'testduplicateconsumer@example.com',
        'phone' => '1234567890',
        'address' => '123 Main St',
        'gender' => 'male',
        'date_of_birth' => '1990-01-01',
        'registration_date' => date('Y-m-d'),
        'is_international' => false
    ],
    'ip_address' => '127.0.0.1',
    'user_agent' => 'Test Browser'
];

echo "Submitting first form (should succeed)...\n";
dispatch(new ProcessFormSubmissionData(null, $formData1));

// Wait a moment for processing
sleep(3);

// Now submit another form with the same email (should be caught by consumer validation)
$formData2 = [
    'operation' => 'create',
    'source' => 'form_submission',
    'data' => [
        'name' => 'Jane Smith',
        'email' => 'testduplicateconsumer@example.com', // Same email as first submission
        'phone' => '0987654321',
        'address' => '456 Oak Ave',
        'gender' => 'female',
        'date_of_birth' => '1992-05-15',
        'registration_date' => date('Y-m-d'),
        'is_international' => false
    ],
    'ip_address' => '127.0.0.1',
    'user_agent' => 'Test Browser'
];

echo "Submitting second form with duplicate email (should be rejected by consumer)...\n";
dispatch(new ProcessFormSubmissionData(null, $formData2));

echo "Test submissions queued. Check logs to verify consumer-level duplicate validation is working.\n";
echo "Expected: First submission succeeds, second submission is rejected due to duplicate email.\n";
?>