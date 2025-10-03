<?php

namespace App\Listeners;

use App\Events\StudentCreated;
use App\Jobs\SendStudentNotification;

class SendStudentNotificationListener
{
    public function handle(StudentCreated $event)
    {
        // Dispatch job to the queue
        SendStudentNotification::dispatch($event->student);
    }
}
