<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\FormSubmission;
use App\Jobs\ProcessFormSubmissionData;
use Illuminate\Support\Facades\Log;

class FormSubmissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating sample form submissions...');
        
        $sampleSubmissions = [
            [
                'operation' => 'create',
                'student_id' => null,
                'data' => [
                    'name' => 'Alice Johnson',
                    'email' => 'alice.johnson@example.com',
                    'phone' => '+1234567890',
                    'address' => '123 University Ave, College Town, State 12345',
                    'date_of_birth' => '1998-05-15',
                    'course' => 'Computer Science',
                    'enrollment_date' => '2024-09-01',
                    'grade' => 'A',
                    'profile_image' => 'uploads/alice.jpg'
                ],
                'status' => 'completed',
                'source' => 'form',
                'ip_address' => '192.168.1.100',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ],
            [
                'operation' => 'create',
                'student_id' => null,
                'data' => [
                    'name' => 'Bob Smith',
                    'email' => 'bob.smith@example.com',
                    'phone' => '+0987654321',
                    'address' => '456 Oak Street, Downtown, State 54321',
                    'date_of_birth' => '1997-11-22',
                    'course' => 'Mathematics',
                    'enrollment_date' => '2024-09-01',
                    'grade' => 'B+',
                    'profile_image' => 'uploads/bob.jpg'
                ],
                'status' => 'queued',
                'source' => 'csv',
                'ip_address' => '192.168.1.101',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36'
            ],
            [
                'operation' => 'update',
                'student_id' => '60f1b2c3d4e5f6789a0b1c2d', // Sample MongoDB ObjectId
                'data' => [
                    'name' => 'Charlie Brown Updated',
                    'email' => 'charlie.brown.new@example.com',
                    'phone' => '+1122334455',
                    'course' => 'Physics',
                    'grade' => 'A-'
                ],
                'status' => 'processing',
                'source' => 'api',
                'ip_address' => '192.168.1.102',
                'user_agent' => 'PostmanRuntime/7.28.0'
            ],
            [
                'operation' => 'create',
                'student_id' => null,
                'data' => [
                    'name' => 'Diana Wilson',
                    'email' => 'diana.wilson@example.com',
                    'phone' => '+5566778899',
                    'address' => '789 Pine Road, Suburb, State 98765',
                    'date_of_birth' => '1999-03-08',
                    'course' => 'Chemistry',
                    'enrollment_date' => '2024-09-01',
                    'grade' => 'A+',
                    'profile_image' => 'uploads/diana.jpg'
                ],
                'status' => 'failed',
                'error_message' => 'Validation failed: Email already exists in the system',
                'source' => 'form',
                'ip_address' => '192.168.1.103',
                'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36'
            ],
            [
                'operation' => 'delete',
                'student_id' => '60f1b2c3d4e5f6789a0b1c2e', // Sample MongoDB ObjectId
                'data' => [
                    'id' => '60f1b2c3d4e5f6789a0b1c2e',
                    'name' => 'Old Student Record',
                    'email' => 'old.student@example.com'
                ],
                'status' => 'completed',
                'source' => 'api',
                'ip_address' => '192.168.1.104',
                'user_agent' => 'curl/7.68.0'
            ],
            [
                'operation' => 'create',
                'student_id' => null,
                'data' => [
                    'name' => 'Eva Martinez',
                    'email' => 'eva.martinez@example.com',
                    'phone' => '+9988776655',
                    'address' => '321 Elm Street, Midtown, State 13579',
                    'date_of_birth' => '1998-07-19',
                    'course' => 'Biology',
                    'enrollment_date' => '2024-09-01',
                    'grade' => 'B',
                    'profile_image' => 'uploads/eva.jpg'
                ],
                'status' => 'queued',
                'source' => 'csv',
                'ip_address' => '192.168.1.105',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ];
        
        foreach ($sampleSubmissions as $submissionData) {
            $formSubmission = FormSubmission::create($submissionData);
            
            $this->command->info("Created form submission: {$formSubmission->_id} ({$submissionData['operation']} - {$submissionData['status']})");
            
            // For queued submissions, dispatch the job
            if ($submissionData['status'] === 'queued') {
                ProcessFormSubmissionData::dispatch($formSubmission->_id, $submissionData);
                $this->command->info("  → Job dispatched for processing");
            }
        }
        
        $this->command->info('Form submission seeding completed!');
        $this->command->info('Statistics:');
        $this->command->info('  - Total submissions: ' . FormSubmission::count());
        $this->command->info('  - Queued: ' . FormSubmission::where('status', 'queued')->count());
        $this->command->info('  - Processing: ' . FormSubmission::where('status', 'processing')->count());
        $this->command->info('  - Completed: ' . FormSubmission::where('status', 'completed')->count());
        $this->command->info('  - Failed: ' . FormSubmission::where('status', 'failed')->count());
    }
}
