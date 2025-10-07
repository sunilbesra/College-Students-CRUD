<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            FormSubmissionSeeder::class,
        ]);

        // Create additional test data using factories
        if (app()->environment(['local', 'testing'])) {
            $this->command->info('Creating additional test data with factories...');
            
            // Create some form submissions using factory
            \App\Models\FormSubmission::factory(10)->createOperation()->queued()->create();
            \App\Models\FormSubmission::factory(5)->updateOperation()->completed()->create();
            \App\Models\FormSubmission::factory(3)->deleteOperation()->failed()->create();
            \App\Models\FormSubmission::factory(8)->fromCsv()->processing()->create();
            \App\Models\FormSubmission::factory(12)->fromApi()->completed()->create();
            
            $this->command->info('Factory data created successfully!');
        }
        
        // \App\Models\User::factory(10)->create();
        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
