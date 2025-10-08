<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FormSubmissionDeleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $submissionId;
    public $submissionData;
    public $operation;
    public $studentId;
    public $source;

    /**
     * Create a new event instance.
     */
    public function __construct(
        string $submissionId,
        array $submissionData,
        string $operation,
        ?string $studentId = null,
        string $source = 'form'
    ) {
        $this->submissionId = $submissionId;
        $this->submissionData = $submissionData;
        $this->operation = $operation;
        $this->studentId = $studentId;
        $this->source = $source;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('form-submissions'),
        ];
    }
}
