<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DuplicateEmailDetected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $email;
    public $source;
    public $existingSubmissionId;
    public $attemptedData;
    public $csvRow;

    /**
     * Create a new event instance.
     */
    public function __construct(
        string $email, 
        string $source, 
        ?string $existingSubmissionId = null,
        ?array $attemptedData = null,
        ?int $csvRow = null
    ) {
        $this->email = $email;
        $this->source = $source;
        $this->existingSubmissionId = $existingSubmissionId;
        $this->attemptedData = $attemptedData;
        $this->csvRow = $csvRow;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('duplicate-emails'),
        ];
    }
}