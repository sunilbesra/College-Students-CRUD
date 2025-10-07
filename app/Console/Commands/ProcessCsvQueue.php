<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ProcessCsvQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'csv:process-queue {--timeout=0 : How many seconds the worker should run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process CSV jobs from the csv_jobs queue using Beanstalkd';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeout = $this->option('timeout');
        
        $this->info('Starting CSV queue worker for beanstalkd queue: csv_jobs');
        $this->info('Press Ctrl+C to stop the worker');
        
        if ($timeout > 0) {
            $this->info("Worker will stop after {$timeout} seconds");
        }
        
        // Run the queue worker specifically for the csv_jobs queue
        $exitCode = Artisan::call('queue:work', [
            'connection' => 'beanstalkd',
            '--queue' => 'csv_jobs',
            '--timeout' => $timeout ?: 60,
            '--sleep' => 3,
            '--tries' => 3,
            '--max-jobs' => 1000,
            '--max-time' => $timeout ?: 3600,
        ]);
        
        if ($exitCode === 0) {
            $this->info('Queue worker finished successfully');
        } else {
            $this->error('Queue worker exited with errors');
        }
        
        return $exitCode;
    }
}
