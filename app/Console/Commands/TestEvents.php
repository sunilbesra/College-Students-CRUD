<?php

namespace App\Console\Commands;

use App\Events\FormSubmissionCreated;
use App\Events\FormSubmissionProcessed;
use App\Events\DuplicateEmailDetected;
use App\Models\FormSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;

class TestEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:test 
                            {--sync : Run events synchronously instead of queued}
                            {--listeners : Show registered event listeners}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test form submission events and listeners';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('listeners')) {
            $this->showRegisteredListeners();
            return;
        }

        $this->info('🧪 Testing Form Submission Events');
        $this->info('==================================');

        // Show current queue configuration
        $this->line('Queue Driver: ' . config('queue.default'));
        $this->line('Sync Mode: ' . ($this->option('sync') ? 'Yes' : 'No'));
        $this->newLine();

        // Create test submission
        $this->info('1. Creating test form submission...');
        $formSubmission = FormSubmission::create([
            'operation' => 'create',
            'data' => [
                'name' => 'Event Test User',
                'email' => 'eventtest@example.com',
                'gender' => 'male',
                'phone' => '+1234567890'
            ],
            'source' => 'form',
            'status' => 'queued'
        ]);
        
        $this->line("✅ Created submission: {$formSubmission->_id}");

        // Test FormSubmissionCreated event
        $this->info("\n2. Firing FormSubmissionCreated event...");
        Log::info('🎯 COMMAND TEST: Firing FormSubmissionCreated', [
            'command' => 'events:test',
            'submission_id' => $formSubmission->_id
        ]);
        
        if ($this->option('sync')) {
            // Temporarily disable queuing for this test
            config(['queue.default' => 'sync']);
        }
        
        event(new FormSubmissionCreated($formSubmission, $formSubmission->data, 'form'));
        $this->line("✅ FormSubmissionCreated event dispatched");

        // Test FormSubmissionProcessed event
        $this->info("\n3. Firing FormSubmissionProcessed event...");
        Log::info('🎯 COMMAND TEST: Firing FormSubmissionProcessed', [
            'command' => 'events:test',
            'submission_id' => $formSubmission->_id
        ]);
        
        event(new FormSubmissionProcessed($formSubmission, 'completed'));
        $this->line("✅ FormSubmissionProcessed event dispatched");

        // Test DuplicateEmailDetected event
        $this->info("\n4. Firing DuplicateEmailDetected event...");
        Log::info('🎯 COMMAND TEST: Firing DuplicateEmailDetected', [
            'command' => 'events:test',
            'email' => 'duplicate@test.com'
        ]);
        
        event(new DuplicateEmailDetected(
            'duplicate@test.com',
            'form',
            $formSubmission->_id,
            ['name' => 'Duplicate Test', 'email' => 'duplicate@test.com']
        ));
        $this->line("✅ DuplicateEmailDetected event dispatched");

        $this->newLine();
        
        if ($this->option('sync')) {
            $this->info('✨ Events processed synchronously');
        } else {
            $this->info('📫 Events queued for processing');
            $this->line('💡 Make sure queue worker is running: php artisan queue:work');
        }
        
        $this->newLine();
        $this->info('📋 Check results with:');
        $this->line('• php artisan events:monitor --tail=10');
        $this->line('• php artisan form:stats');
        
        if (!$this->option('sync')) {
            $this->line('• Wait a few seconds for queue processing');
        }
        
        return 0;
    }

    /**
     * Show registered event listeners
     */
    private function showRegisteredListeners(): void
    {
        $this->info('📋 Registered Event Listeners');
        $this->info('=============================');

        $listeners = Event::getRawListeners();
        
        foreach ($listeners as $event => $eventListeners) {
            if (strpos($event, 'App\\Events') !== false) {
                $this->line("\n🎯 <fg=yellow>{$event}</>");
                foreach ($eventListeners as $listener) {
                    if (is_string($listener)) {
                        $this->line("  └─ <fg=green>{$listener}</>");
                    } elseif (is_array($listener) && isset($listener[0])) {
                        $class = is_object($listener[0]) ? get_class($listener[0]) : $listener[0];
                        $method = $listener[1] ?? 'handle';
                        $this->line("  └─ <fg=green>{$class}@{$method}</>");
                    } else {
                        $this->line("  └─ <fg=blue>Closure</>");
                    }
                }
            }
        }
        
        $this->newLine();
        $this->info('💡 Use --sync flag to test synchronous event processing');
    }
}
