<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CsvUploadCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $fileName;
    public $operation;
    public $validationSummary;
    public $processingTimeMs;
    public $batchJobId;

    /**
     * Create a new event instance.
     */
    public function __construct(
        string $fileName, 
        string $operation, 
        array $validationSummary, 
        int $processingTimeMs,
        ?string $batchJobId = null
    ) {
        $this->fileName = $fileName;
        $this->operation = $operation;
        $this->validationSummary = $validationSummary;
        $this->processingTimeMs = $processingTimeMs;
        $this->batchJobId = $batchJobId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('csv-uploads'),
        ];
    }
}