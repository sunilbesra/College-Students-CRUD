<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ProcessStudentQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'students:process-queue 
                          {--queue=both : Which queue to process (csv_jobs, student_jobs, or both)}
                          {--timeout=0 : How many seconds the worker should run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process student data from both CSV uploads and form submissions using unified Beanstalkd queues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $queueOption = $this->option('queue');
        $timeout = $this->option('timeout');
        
        $this->info('🎓 Starting Student Data Processing Workers');
        $this->info('=====================================');
        
        if ($timeout > 0) {
            $this->info("Workers will stop after {$timeout} seconds");
        }
        
        // Define queue configurations
        $queues = [];
        
        if ($queueOption === 'both' || $queueOption === 'csv_jobs') {
            $queues[] = [
                'name' => 'csv_jobs',
                'description' => 'CSV Upload Processing',
                'env_var' => 'BEANSTALKD_QUEUE'
            ];
        }
        
        if ($queueOption === 'both' || $queueOption === 'student_jobs') {
            $queues[] = [
                'name' => 'student_jobs', 
                'description' => 'Form Submission Processing',
                'env_var' => 'BEANSTALKD_STUDENT_QUEUE'
            ];
        }
        
        if (empty($queues)) {
            $this->error('Invalid queue option. Use: csv_jobs, student_jobs, or both');
            return 1;
        }
        
        foreach ($queues as $queue) {
            $queueName = env($queue['env_var'], $queue['name']);
            $this->info("📋 {$queue['description']}: {$queueName}");
        }
        
        $this->info('');
        $this->info('Press Ctrl+C to stop all workers');
        $this->info('');
        
        // If processing both queues, we need to run them with comma-separated queue names
        if (count($queues) > 1) {
            $queueNames = implode(',', array_map(function($q) {
                return env($q['env_var'], $q['name']);
            }, $queues));
            
            $this->info("🚀 Processing multiple queues: {$queueNames}");
            
            $exitCode = Artisan::call('queue:work', [
                'connection' => 'beanstalkd',
                '--queue' => $queueNames,
                '--timeout' => $timeout ?: 60,
                '--sleep' => 3,
                '--tries' => 3,
                '--max-jobs' => 1000,
                '--max-time' => $timeout ?: 3600,
            ]);
        } else {
            // Single queue processing
            $queue = $queues[0];
            $queueName = env($queue['env_var'], $queue['name']);
            
            $this->info("🚀 Processing single queue: {$queueName}");
            
            $exitCode = Artisan::call('queue:work', [
                'connection' => 'beanstalkd',
                '--queue' => $queueName,
                '--timeout' => $timeout ?: 60,
                '--sleep' => 3,
                '--tries' => 3,
                '--max-jobs' => 1000,
                '--max-time' => $timeout ?: 3600,
            ]);
        }
        
        if ($exitCode === 0) {
            $this->info('✅ Queue workers finished successfully');
        } else {
            $this->error('❌ Queue workers exited with errors');
        }
        
        return $exitCode;
    }
}
