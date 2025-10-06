<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentValidator
{
    /**
     * Return validation rules. If $ignoreId is provided, the unique rule will ignore that id.
     */
    public static function rules($ignoreId = null)
    {
        $uniqueRule = Rule::unique('students', 'email');
        if ($ignoreId) {
            // For Mongo _id use the default column name, when using SQL the primary key is id
            $uniqueRule = $uniqueRule->ignore($ignoreId);
        }

        return [
            'name' => ['required', 'max:255'],
            'email' => ['required', 'email', $uniqueRule],
            'contact' => ['required'],
            'profile_image' => ['nullable', 'string'], // In jobs we accept a path/string
            'address' => ['required'],
            'college' => ['required'],
            'gender' => ['nullable', 'in:male,female,other'],
            'dob' => ['nullable', 'date'],
            'enrollment_status' => ['nullable', 'in:full_time,part_time'],
            'course' => ['nullable', 'string', 'max:255'],
            'agreed_to_terms' => ['nullable', 'accepted'],
        ];
    }

    /**
     * Validate data array and return validated data or throw ValidationException.
     * @throws ValidationException
     */
    public static function validate(array $data, $ignoreId = null): array
    {
        $validator = Validator::make($data, static::rules($ignoreId));
        return $validator->validate();
    }
}
