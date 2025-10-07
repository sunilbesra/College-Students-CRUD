<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FormSubmission>
 */
class FormSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $operation = $this->faker->randomElement(['create', 'update', 'delete']);
        
        return [
            'operation' => $operation,
            'student_id' => $operation !== 'create' ? $this->faker->regexify('[a-f0-9]{24}') : null,
            'data' => $this->generateStudentData($operation),
            'status' => $this->faker->randomElement(['queued', 'processing', 'completed', 'failed']),
            'error_message' => $this->faker->optional(0.2)->sentence(),
            'source' => $this->faker->randomElement(['form', 'api', 'csv']),
            'user_id' => $this->faker->optional()->numberBetween(1, 100),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }

    /**
     * Generate student data based on operation type
     */
    private function generateStudentData(string $operation): array
    {
        if ($operation === 'delete') {
            return [
                'id' => $this->faker->regexify('[a-f0-9]{24}'),
                'name' => $this->faker->name(),
                'email' => $this->faker->email(),
            ];
        }

        $data = [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'date_of_birth' => $this->faker->date('Y-m-d', '2002-12-31'),
            'course' => $this->faker->randomElement([
                'Computer Science', 'Mathematics', 'Physics', 'Chemistry',
                'Biology', 'Engineering', 'Business Administration', 'Psychology'
            ]),
            'enrollment_date' => $this->faker->dateTimeBetween('2023-09-01', '2024-09-01')->format('Y-m-d'),
            'grade' => $this->faker->randomElement(['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C']),
        ];

        if ($this->faker->boolean(70)) { // 70% chance of having a profile image
            $data['profile_image'] = 'uploads/' . $this->faker->slug() . '.jpg';
        }

        return $data;
    }

    /**
     * Create a form submission for create operation
     */
    public function createOperation(): static
    {
        return $this->state(fn (array $attributes) => [
            'operation' => 'create',
            'student_id' => null,
            'data' => $this->generateStudentData('create'),
        ]);
    }

    /**
     * Create a form submission for update operation
     */
    public function updateOperation(): static
    {
        return $this->state(fn (array $attributes) => [
            'operation' => 'update',
            'student_id' => $this->faker->regexify('[a-f0-9]{24}'),
            'data' => $this->generateStudentData('update'),
        ]);
    }

    /**
     * Create a form submission for delete operation
     */
    public function deleteOperation(): static
    {
        return $this->state(fn (array $attributes) => [
            'operation' => 'delete',
            'student_id' => $this->faker->regexify('[a-f0-9]{24}'),
            'data' => $this->generateStudentData('delete'),
        ]);
    }

    /**
     * Create a queued form submission
     */
    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'queued',
            'error_message' => null,
        ]);
    }

    /**
     * Create a processing form submission
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'error_message' => null,
        ]);
    }

    /**
     * Create a completed form submission
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'error_message' => null,
        ]);
    }

    /**
     * Create a failed form submission
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => $this->faker->randomElement([
                'Validation failed: Email already exists in the system',
                'Validation failed: Phone number format is invalid',
                'Student not found for update operation',
                'Database connection error during processing',
                'Invalid date format in enrollment_date field',
                'Required field "name" is missing from data',
            ]),
        ]);
    }

    /**
     * Create form submission from form source
     */
    public function fromForm(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'form',
            'user_agent' => $this->faker->randomElement([
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
            ]),
        ]);
    }

    /**
     * Create form submission from API source
     */
    public function fromApi(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'api',
            'user_agent' => $this->faker->randomElement([
                'PostmanRuntime/7.28.0',
                'curl/7.68.0',
                'Insomnia/2021.7.2',
                'HTTPie/2.6.0',
            ]),
        ]);
    }

    /**
     * Create form submission from CSV source
     */
    public function fromCsv(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'csv',
            'user_agent' => $this->faker->randomElement([
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            ]),
        ]);
    }
}
