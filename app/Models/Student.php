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

   
    public function scopeSearchText($query, ?string $term)
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        
            return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('college', 'like', "%{$term}%")
              ->orWhere('contact', 'like', "%{$term}%")
              ->orWhere('address', 'like', "%{$term}%");
        });
    }
}
