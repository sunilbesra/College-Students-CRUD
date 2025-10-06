<?php

use Illuminate\Validation\Rule;

if (! function_exists('student_validate')) {
    /**
     * Validate student data array.
     * Throws \Illuminate\Validation\ValidationException on failure.
     *
     * @param array $data
     * @param mixed $ignoreId
     * @return array
     */
    function student_validate(array $data, $ignoreId = null): array
    {
        $uniqueRule = Rule::unique('students', 'email');
        if ($ignoreId) {
            $uniqueRule = $uniqueRule->ignore($ignoreId);
        }

        $rules = [
            'name' => ['required', 'max:255'],
            'email' => ['required', 'email', $uniqueRule],
            'contact' => ['required'],
            'profile_image' => ['required'],
            'address' => ['required'],
            'college' => ['required'],
            'gender' => ['nullable', 'in:male,female,other'],
            'dob' => ['nullable', 'date'],
            'enrollment_status' => ['nullable', 'in:full_time,part_time'],
            'course' => ['nullable', 'string', 'max:255'],
            'agreed_to_terms' => ['nullable', 'accepted'],
        ];

        return \Illuminate\Support\Facades\Validator::make($data, $rules)->validate();
    }
}
