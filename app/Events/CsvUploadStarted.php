<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CsvUploadStarted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $fileName;
    public $operation;
    public $totalRows;
    public $ipAddress;
    public $userAgent;

    /**
     * Create a new event instance.
     */
    public function __construct(string $fileName, string $operation, int $totalRows, ?string $ipAddress = null, ?string $userAgent = null)
    {
        $this->fileName = $fileName;
        $this->operation = $operation;
        $this->totalRows = $totalRows;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
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