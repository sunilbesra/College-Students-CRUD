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

class FormSubmissionCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $formSubmission;
    public $submissionData;
    public $source;

    /**
     * Create a new event instance.
     */
    public function __construct(FormSubmission $formSubmission, array $submissionData, string $source = 'form')
    {
        $this->formSubmission = $formSubmission;
        $this->submissionData = $submissionData;
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