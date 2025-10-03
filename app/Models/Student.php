<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
class Student extends Model
{
    protected $connection = 'mongodb';  // tell Laravel to use MongoDB
    protected $collection = 'students'; // optional, defaults to plural of class name

    protected $fillable = [
        'name',
        'email',
        'contact',
        'profile_image',
        'address',
        'college',
    ];
}
