<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CsvBatchQueued
{
    use Dispatchable, SerializesModels;

    /**
     * The file name that produced the batch (optional)
     *
     * @var string|null
     */
    public $fileName;

    /**
     * Array of job IDs (Mongo _id values) for rows that were queued.
     *
     * @var array
     */
    public $jobIds;

    /**
     * Create a new event instance.
     *
     * @param string|null $fileName
     * @param array $jobIds
     */
    public function __construct(?string $fileName, array $jobIds)
    {
        $this->fileName = $fileName;
        $this->jobIds = $jobIds;
    }
}
