<?php

namespace App\Services;

use App\Models\FormSubmission;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FormSubmissionValidator
{
    /**
     * Return validation rules for form submission student data.
     */
    public static function rules($ignoreId = null)
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['required', 'string', 'in:male,female'],
            'date_of_birth' => ['nullable', 'date', 'date_format:Y-m-d'],
            'course' => ['nullable', 'string', 'max:255'],
            'enrollment_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'grade' => ['nullable', 'string', 'max:10'],
            'profile_image_path' => ['nullable', 'string', 'max:500'],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Validate form submission data array and return validated data.
     * @throws ValidationException
     */
    public static function validate(array $data, $ignoreId = null): array
    {
        // Clean the data first
        $cleanedData = [];
        foreach ($data as $key => $value) {
            $cleanedValue = is_string($value) ? trim($value) : $value;
            if ($cleanedValue !== '' && $cleanedValue !== null) {
                $cleanedData[$key] = $cleanedValue;
            }
        }

        $validator = Validator::make($cleanedData, static::rules($ignoreId));
        
        // Add custom validation messages
        $validator->setCustomMessages([
            'name.required' => 'Student name is required.',
            'email.required' => 'Student email is required.',
            'email.email' => 'Please provide a valid email address.',
            'gender.required' => 'Gender selection is required.',
            'gender.in' => 'Gender must be either male or female.',
            'date_of_birth.date' => 'Date of birth must be a valid date.',
            'date_of_birth.date_format' => 'Date of birth must be in YYYY-MM-DD format.',
            'enrollment_date.date' => 'Enrollment date must be a valid date.',
            'enrollment_date.date_format' => 'Enrollment date must be in YYYY-MM-DD format.',
        ]);

        // Validate basic rules first
        $validatedData = $validator->validate();

        // Check for duplicate email in FormSubmission collection
        if (isset($validatedData['email'])) {
            $duplicateSubmission = static::findDuplicateEmail($validatedData['email'], $ignoreId);
            if ($duplicateSubmission) {
                throw ValidationException::withMessages([
                    'email' => ['This email address is already registered.']
                ]);
            }
        }

        return $validatedData;
    }

    /**
     * Map form submission data to student model format.
     * This handles any field name differences between form submissions and the student model.
     */
    public static function mapToStudentData(array $formData): array
    {
        return [
            'name' => $formData['name'] ?? null,
            'email' => $formData['email'] ?? null,
            'contact' => $formData['phone'] ?? null, // Map phone to contact for student model
            'address' => $formData['address'] ?? null,
            'dob' => $formData['date_of_birth'] ?? null, // Map date_of_birth to dob
            'course' => $formData['course'] ?? null,
            'profile_image' => $formData['profile_image_path'] ?? null, // Map profile_image_path to profile_image
            'enrollment_date' => $formData['enrollment_date'] ?? null,
            'grade' => $formData['grade'] ?? null,
            'gender' => $formData['gender'] ?? null, // Map gender from form data
            // Set default values for required student fields that don't exist in form submissions
            'college' => 'Unknown', // Default college value
            'enrollment_status' => 'full_time', // Default enrollment status
            'agreed_to_terms' => true, // Assume terms are agreed if submitting
        ];
    }

    /**
     * Validate form submission data for FormSubmission model only
     */
    public static function validateForFormSubmission(array $data, $ignoreId = null): array
    {
        return static::validate($data, $ignoreId);
    }

    /**
     * Check if email already exists in FormSubmission collection
     */
    public static function findDuplicateEmail(string $email, $ignoreId = null): ?FormSubmission
    {
        $query = FormSubmission::where('data.email', $email)
            ->whereIn('status', ['completed', 'processing']);
            
        if ($ignoreId) {
            $query->where('_id', '!=', $ignoreId);
        }
        
        return $query->first();
    }

    /**
     * Validate CSV batch data and return validation results
     */
    public static function validateCsvBatch(array $csvData): array
    {
        $results = [
            'valid' => [],
            'invalid' => [],
            'duplicates' => []
        ];

        $emailsInBatch = [];
        
        foreach ($csvData as $index => $row) {
            try {
                // Check for duplicate within the batch
                $email = trim($row['email'] ?? '');
                if (!empty($email)) {
                    if (isset($emailsInBatch[$email])) {
                        $results['duplicates'][] = [
                            'row' => $index + 2, // +2 for 1-based indexing and header row
                            'email' => $email,
                            'error' => "Duplicate email found in CSV at row {$emailsInBatch[$email]} and row " . ($index + 2)
                        ];
                        continue;
                    }
                    $emailsInBatch[$email] = $index + 2;

                    // Check for duplicate in database
                    if (static::findDuplicateEmail($email)) {
                        $results['duplicates'][] = [
                            'row' => $index + 2,
                            'email' => $email,
                            'error' => 'This email address is already registered in the system.'
                        ];
                        continue;
                    }
                }

                // Validate the row data
                $validatedRow = static::validate($row);
                $results['valid'][] = [
                    'row' => $index + 2,
                    'data' => $validatedRow
                ];
                
            } catch (ValidationException $e) {
                $results['invalid'][] = [
                    'row' => $index + 2,
                    'data' => $row,
                    'errors' => $e->errors()
                ];
            }
        }

        return $results;
    }

    /**
     * Get validation summary for CSV upload
     */
    public static function getCsvValidationSummary(array $validationResults): array
    {
        $validCount = count($validationResults['valid']);
        $invalidCount = count($validationResults['invalid']);
        $duplicateCount = count($validationResults['duplicates']);
        $totalCount = $validCount + $invalidCount + $duplicateCount;

        return [
            'total_rows' => $totalCount,
            'valid_rows' => $validCount,
            'invalid_rows' => $invalidCount,
            'duplicate_rows' => $duplicateCount,
            'can_process' => $validCount > 0,
            'has_errors' => ($invalidCount + $duplicateCount) > 0
        ];
    }
}