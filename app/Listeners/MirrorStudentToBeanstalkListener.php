<?php

namespace App\Listeners;

use Pheanstalk\Pheanstalk;
use Illuminate\Support\Facades\Log;
use App\Events\StudentCreated;
use App\Events\StudentUpdated;
use App\Events\StudentDeleted;

class MirrorStudentToBeanstalkListener
{
    public function handle($event)
    {
        try {
            $pheanstalkHost = env('BEANSTALKD_QUEUE_HOST', '127.0.0.1');
            $pheanstalkPort = env('BEANSTALKD_PORT', 11300) ?: env('BEANSTALKD_PORT', 11300);
            $pheanstalk = Pheanstalk::create($pheanstalkHost, $pheanstalkPort);
            $mirrorTube = env('BEANSTALKD_JSON_TUBE', 'csv_jobs_json');

            if ($event instanceof StudentCreated) {
                $payload = json_encode(['operation' => 'create', 'data' => $event->student], JSON_UNESCAPED_UNICODE);
            } elseif ($event instanceof StudentUpdated) {
                $payload = json_encode(['operation' => 'update', 'data' => $event->student], JSON_UNESCAPED_UNICODE);
            } elseif ($event instanceof StudentDeleted) {
                $payload = json_encode(['operation' => 'delete', 'id' => $event->studentId], JSON_UNESCAPED_UNICODE);
            } else {
                return;
            }

            $pheanstalk->useTube($mirrorTube)->put($payload);
        } catch (\Throwable $e) {
            Log::warning('Failed to mirror student event to beanstalk: ' . $e->getMessage());
        }
    }
}
