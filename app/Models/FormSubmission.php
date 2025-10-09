<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Services\FormSubmissionValidator;
use App\Events\DuplicateEmailDetected;
use Illuminate\Support\Facades\Log;

class FormSubmission extends Model
{
    protected $connection = 'mongodb'; // Use MongoDB
    protected $collection = 'form_submissions'; // Collection name

    protected $fillable = [
        'operation', // 'create', 'update', 'delete'
        'student_id', // Used as reference ID for update/delete operations
        'data',
        'status', // 'queued', 'processing', 'completed', 'failed'
        'error_message',
        'source', // 'form', 'api', 'csv'
        'user_id', // For future user authentication
        'ip_address',
        'user_agent',
        'processed_at', // When processing completed
        'duplicate_of', // Reference to existing submission if duplicate
        'updated_submission_id', // Reference to submission that was updated
        'deleted_submission_id', // Reference to submission that was deleted
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Set the data attribute, ensuring it's properly stored
     */
    public function setDataAttribute($value)
    {
        if (is_string($value)) {
            // If it's a JSON string, decode it
            $decoded = json_decode($value, true);
            $this->attributes['data'] = $decoded !== null ? $decoded : [];
        } elseif (is_array($value)) {
            // If it's already an array, use it directly
            $this->attributes['data'] = $value;
        } else {
            // Default to empty array for other types
            $this->attributes['data'] = [];
        }
    }

    /**
     * Get the data attribute, ensuring it's always an array
     */
    public function getDataAttribute($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return $decoded !== null ? $decoded : [];
        }
        
        return is_array($value) ? $value : [];
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOperation($query, $operation)
    {
        return $query->where('operation', $operation);
    }


}
