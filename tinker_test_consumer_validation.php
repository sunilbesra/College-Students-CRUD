// Test consumer-level duplicate validation
// Run this in: php artisan tinker

// First submission (should succeed)
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

dispatch(new \App\Jobs\ProcessFormSubmissionData(null, $formData1));

// Wait for processing
sleep(5);

// Second submission with same email (should be rejected)
$formData2 = [
    'operation' => 'create',
    'source' => 'form_submission',
    'data' => [
        'name' => 'Jane Smith',
        'email' => 'testduplicateconsumer@example.com', // Same email
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

dispatch(new \App\Jobs\ProcessFormSubmissionData(null, $formData2));

echo "Check logs to verify consumer-level validation is working!";