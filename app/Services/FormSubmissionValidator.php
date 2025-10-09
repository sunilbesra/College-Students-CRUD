<?php

namespace App\Services;

use App\Models\FormSubmission;
use App\Events\DuplicateEmailDetected;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FormSubmissionValidator
{
    public static function rules($ignoreId = null)
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^[\+]?[0-9]{10,15}$/', 'max:20'],
            'gender' => ['required', 'string', 'in:male,female'],
            'date_of_birth' => ['nullable', 'date', 'date_format:Y-m-d'],
            'course' => ['nullable', 'string', 'max:255'],
            'enrollment_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'grade' => ['nullable', 'string', 'max:10'],
            'profile_image_path' => ['nullable', 'string', 'max:500'],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public static function csvRules($ignoreId = null)
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^[\+]?[0-9]{10,15}$/', 'max:20'],
            'gender' => ['required', 'string', 'in:male,female'],
            'date_of_birth' => ['required', 'date', 'date_format:Y-m-d'],
            'course' => ['required', 'string', 'max:255'],
            'enrollment_date' => ['required', 'date', 'date_format:Y-m-d'],
            'grade' => ['nullable', 'string', 'max:10'],
            'profile_image_path' => ['nullable', 'string', 'max:500'],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }
    // Validate form submission data
    public static function validate(array $data, $ignoreId = null): array
    {
        $validator = Validator::make($data, static::rules($ignoreId), [
            'name.required' => 'Student name is required.',
            'email.required' => 'Student email is required.',
            'email.email' => 'Please provide a valid email address.',
            'phone.regex' => 'Phone number must be 10-15 digits, optionally starting with +.',
            'phone.max' => 'Phone number must not exceed 20 characters.',
            'gender.required' => 'Gender selection is required.',
            'gender.in' => 'Gender must be either male or female.',
            'date_of_birth.date' => 'Date of birth must be a valid date.',
            'date_of_birth.date_format' => 'Date of birth must be in YYYY-MM-DD format.',
            'enrollment_date.date' => 'Enrollment date must be a valid date.',
            'enrollment_date.date_format' => 'Enrollment date must be in YYYY-MM-DD format.',
        ]);

        $validatedData = $validator->validate();

        if (isset($validatedData['email'])) {
            Log::debug('FormSubmissionValidator::validate - Checking for duplicate email', [
                'email' => $validatedData['email'],
                'ignore_id' => $ignoreId ? (string) $ignoreId : null,
                'method' => 'validate'
            ]);
            
            $duplicateSubmission = static::findDuplicateEmail($validatedData['email'], $ignoreId);
            if ($duplicateSubmission) {
                Log::warning('FormSubmissionValidator::validate - Duplicate email detected', [
                    'email' => $validatedData['email'],
                    'duplicate_submission_id' => (string) $duplicateSubmission->_id,
                    'ignore_id' => $ignoreId ? (string) $ignoreId : null,
                    'method' => 'validate'
                ]);
                
                throw ValidationException::withMessages([
                    'email' => ['This email address is already registered (DEBUG: from FormSubmissionValidator validate method).']
                ]);
            }
        }

        return $validatedData;
    }
    // Validate CSV row data
    public static function validateCsv(array $data, $ignoreId = null): array
    {
        $cleanedData = [];
        foreach ($data as $key => $value) {
            $cleanedValue = is_string($value) ? trim($value) : $value;
            if ($cleanedValue !== '' && $cleanedValue !== null) {
                $cleanedData[$key] = $cleanedValue;
            }
        }

        $validator = Validator::make($cleanedData, static::csvRules($ignoreId), [
            'name.required' => 'Student name is required.',
            'email.required' => 'Student email is required.',
            'email.email' => 'Please provide a valid email address.',
            'phone.required' => 'Phone number is required for CSV uploads.',
            'phone.regex' => 'Phone number must be 10-15 digits, optionally starting with +.',
            'phone.max' => 'Phone number must not exceed 20 characters.',
            'gender.required' => 'Gender selection is required.',
            'gender.in' => 'Gender must be either male or female.',
            'date_of_birth.required' => 'Date of birth is required for CSV uploads.',
            'course.required' => 'Course is required for CSV uploads.',
            'enrollment_date.required' => 'Enrollment date is required for CSV uploads.',
        ]);

        $validatedData = $validator->validate();

        if (isset($validatedData['email'])) {
            Log::debug('FormSubmissionValidator::validateCsv - Checking for duplicate email', [
                'email' => $validatedData['email'],
                'ignore_id' => $ignoreId ? (string) $ignoreId : null,
                'method' => 'validateCsv'
            ]);
            
            $duplicateSubmission = static::findDuplicateEmail($validatedData['email'], $ignoreId);
            if ($duplicateSubmission) {
                Log::warning('FormSubmissionValidator::validateCsv - Duplicate email detected', [
                    'email' => $validatedData['email'],
                    'duplicate_submission_id' => (string) $duplicateSubmission->_id,
                    'ignore_id' => $ignoreId ? (string) $ignoreId : null,
                    'method' => 'validateCsv'
                ]);
                
                throw ValidationException::withMessages([
                    'email' => ['This email address is already registered (DEBUG: from FormSubmissionValidator validateCsv method).']
                ]);
            }
        }

        return $validatedData;
    }

    // Check for existing submission with the same email
    public static function findDuplicateEmail(string $email, $ignoreId = null): ?FormSubmission
    {
        $query = FormSubmission::where('data.email', $email)
            ->where('status', 'completed');
            
        if ($ignoreId) {
            // Convert ignoreId to string for consistent comparison
            $ignoreIdString = is_object($ignoreId) ? (string) $ignoreId : (string) $ignoreId;
            
            // Get all matching submissions and filter manually for MongoDB compatibility
            $allSubmissions = FormSubmission::where('data.email', $email)
                ->where('status', 'completed')
                ->get();
                
            foreach ($allSubmissions as $submission) {
                if ((string) $submission->_id !== $ignoreIdString) {
                    Log::debug('Duplicate email found', [
                        'email' => $email,
                        'existing_submission_id' => (string) $submission->_id,
                        'ignore_id' => $ignoreIdString,
                        'duplicate_found' => true
                    ]);
                    return $submission;
                }
            }
            
            Log::debug('No duplicate email found (with ignore)', [
                'email' => $email,
                'ignore_id' => $ignoreIdString,
                'total_matching' => $allSubmissions->count()
            ]);
            return null;
        }
        
        $duplicate = $query->first();
        if ($duplicate) {
            Log::debug('Duplicate email found (no ignore)', [
                'email' => $email,
                'existing_submission_id' => (string) $duplicate->_id,
                'duplicate_found' => true
            ]);
        } else {
            Log::debug('No duplicate email found (no ignore)', [
                'email' => $email,
                'duplicate_found' => false
            ]);
        }
        
        return $duplicate;
    }

    // Validate a batch of CSV rows 
    public static function validateCsvBatch(array $csvData): array
    {
        $results = [
            'valid' => [],
            'invalid' => [],
            'duplicates' => [],
            'summary' => [
                'total_rows' => count($csvData),
                'valid_rows' => 0,
                'invalid_rows' => 0,
                'duplicate_rows' => 0,
                'can_process' => false,
                'has_errors' => false
            ]
        ];

        $processedEmails = [];

        foreach ($csvData as $rowIndex => $rowData) {
            $rowNumber = $rowIndex + 1;

            try {
                $email = $rowData['email'] ?? '';
                
                if ($email && isset($processedEmails[$email])) {
                    $results['duplicates'][] = [
                        'row' => $rowNumber,
                        'email' => $email,
                        'data' => $rowData,
                        'error' => "Duplicate email within CSV (first seen on row {$processedEmails[$email]})"
                    ];
                    $results['summary']['duplicate_rows']++;
                    continue;
                }

                if ($email) {
                    if (static::findDuplicateEmail($email)) {
                        $results['duplicates'][] = [
                            'row' => $rowNumber,
                            'email' => $email,
                            'data' => $rowData,
                            'error' => 'This email address is already registered in the system.'
                        ];
                        $results['summary']['duplicate_rows']++;
                        continue;
                    }
                    
                    $processedEmails[$email] = $rowNumber;
                }

                $validatedData = static::validateCsv($rowData);
                
                $results['valid'][] = [
                    'row' => $rowNumber,
                    'data' => $validatedData
                ];
                $results['summary']['valid_rows']++;

            } catch (ValidationException $e) {
                $results['invalid'][] = [
                    'row' => $rowNumber,
                    'data' => $rowData,
                    'errors' => $e->errors()
                ];
                $results['summary']['invalid_rows']++;
            }
        }

        $results['summary']['can_process'] = $results['summary']['invalid_rows'] === 0 && $results['summary']['duplicate_rows'] === 0;
        $results['summary']['has_errors'] = $results['summary']['invalid_rows'] > 0 || $results['summary']['duplicate_rows'] > 0;

        return $results;
    }

    /**
     * Get CSV validation summary from validation results
     */
    public static function getCsvValidationSummary(array $validationResults): array
    {
        return $validationResults['summary'] ?? [
            'total_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'duplicate_rows' => 0,
            'can_process' => false,
            'has_errors' => true
        ];
    }

    /**
     * Check if the current data contains any validation errors for preview
     */
    public static function previewValidation(array $data): array
    {
        $errors = [];
        
        try {
            static::validate($data);
        } catch (ValidationException $e) {
            $errors = $e->errors();
        }

        return $errors;
    }

    /**
     * Get validation rules as array for frontend display
     */
    public static function getRulesForDisplay(): array
    {
        return [
            'name' => 'Required, maximum 255 characters',
            'email' => 'Required, valid email format, maximum 255 characters',
            'phone' => 'Optional for forms, required for CSV, 10-15 digits, optionally starting with +',
            'gender' => 'Required, either "male" or "female"',
            'date_of_birth' => 'Optional for forms, required for CSV, YYYY-MM-DD format',
            'course' => 'Optional for forms, required for CSV, maximum 255 characters',
            'enrollment_date' => 'Optional for forms, required for CSV, YYYY-MM-DD format',
            'grade' => 'Optional, maximum 10 characters',
            'profile_image_path' => 'Optional, maximum 500 characters',
            'address' => 'Optional, maximum 1000 characters'
        ];
    }
}
