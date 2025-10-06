<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentDeleted
{
    use Dispatchable, SerializesModels;

    public $studentId;

    public function __construct($studentId)
    {
        $this->studentId = $studentId;
    }
}
