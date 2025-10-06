<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentUpdated
{
    use Dispatchable, SerializesModels;

    public $student;

    public function __construct($student)
    {
        $this->student = $student;
    }
}
