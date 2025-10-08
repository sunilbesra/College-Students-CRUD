<?php

namespace App\Events;

use App\Models\FormSubmission;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FormSubmissionProcessed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $formSubmission;
    public $status;
    public $errorMessage;

    /**
     * Create a new event instance.
     */
    public function __construct(FormSubmission $formSubmission, string $status, ?string $errorMessage = null)
    {
        $this->formSubmission = $formSubmission;
        $this->status = $status;
        $this->errorMessage = $errorMessage;
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