<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        \App\Events\StudentCreated::class => [
            \App\Listeners\SendStudentNotificationListener::class,
            \App\Listeners\MirrorStudentToBeanstalkListener::class,
        ],
        \App\Events\StudentUpdated::class => [
            \App\Listeners\MirrorStudentToBeanstalkListener::class,
        ],
        \App\Events\StudentDeleted::class => [
            \App\Listeners\MirrorStudentToBeanstalkListener::class,
        ],
        \App\Events\CsvBatchQueued::class => [
            \App\Listeners\DispatchCsvJobsListener::class,
        ],
        
        // Form Submission Events
        \App\Events\FormSubmissionCreated::class => [
            \App\Listeners\LogFormSubmissionCreated::class,
        ],
        \App\Events\FormSubmissionProcessed::class => [
            \App\Listeners\UpdateFormSubmissionStats::class,
        ],
        
        // CSV Upload Events
        \App\Events\CsvUploadStarted::class => [
            \App\Listeners\LogCsvUploadStarted::class,
        ],
        \App\Events\CsvUploadCompleted::class => [
            \App\Listeners\LogCsvUploadCompleted::class,
        ],
        
        // Duplicate Email Events
        \App\Events\DuplicateEmailDetected::class => [
            \App\Listeners\HandleDuplicateEmail::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
