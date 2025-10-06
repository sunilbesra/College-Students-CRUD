<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class CsvJob extends Model
{
    protected $connection = 'mongodb'; // Use MongoDB
    protected $collection = 'csv_jobs'; // Collection name

    protected $fillable = [
        'file_name',
        'row_identifier',
        'data',
        'status',
        'error_message',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}