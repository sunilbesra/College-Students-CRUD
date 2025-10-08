<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Notification extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'notifications';

    protected $fillable = [
        'type',
        'title',
        'message',
        'data',
        'icon',
        'color',
        'is_read',
        'is_important',
        'action_url',
        'action_text',
        'related_type',
        'related_id',
        'expires_at'
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'is_important' => 'boolean',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $attributes = [
        'is_read' => false,
        'is_important' => false
    ];

    // Notification types
    const TYPE_SUCCESS = 'success';
    const TYPE_ERROR = 'error';
    const TYPE_WARNING = 'warning';
    const TYPE_INFO = 'info';
    const TYPE_FORM_SUBMISSION = 'form_submission';
    const TYPE_CSV_UPLOAD = 'csv_upload';
    const TYPE_DUPLICATE_EMAIL = 'duplicate_email';

    // Color mappings
    const COLORS = [
        self::TYPE_SUCCESS => 'green',
        self::TYPE_ERROR => 'red',
        self::TYPE_WARNING => 'yellow',
        self::TYPE_INFO => 'blue',
        self::TYPE_FORM_SUBMISSION => 'purple',
        self::TYPE_CSV_UPLOAD => 'indigo',
        self::TYPE_DUPLICATE_EMAIL => 'orange'
    ];

    // Icon mappings
    const ICONS = [
        self::TYPE_SUCCESS => 'fas fa-check-circle',
        self::TYPE_ERROR => 'fas fa-exclamation-circle',
        self::TYPE_WARNING => 'fas fa-exclamation-triangle',
        self::TYPE_INFO => 'fas fa-info-circle',
        self::TYPE_FORM_SUBMISSION => 'fas fa-file-alt',
        self::TYPE_CSV_UPLOAD => 'fas fa-file-csv',
        self::TYPE_DUPLICATE_EMAIL => 'fas fa-copy'
    ];

    /**
     * Scope for unread notifications
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for read notifications
     */
    public function scopeRead(Builder $query): Builder
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope for important notifications
     */
    public function scopeImportant(Builder $query): Builder
    {
        return $query->where('is_important', true);
    }

    /**
     * Scope for non-expired notifications
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope for specific type
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): bool
    {
        return $this->update(['is_read' => true]);
    }

    /**
     * Mark notification as unread
     */
    public function markAsUnread(): bool
    {
        return $this->update(['is_read' => false]);
    }

    /**
     * Get formatted time ago
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get notification color based on type
     */
    public function getNotificationColorAttribute(): string
    {
        return self::COLORS[$this->type] ?? 'gray';
    }

    /**
     * Get notification icon based on type
     */
    public function getNotificationIconAttribute(): string
    {
        return $this->icon ?? self::ICONS[$this->type] ?? 'fas fa-bell';
    }

    /**
     * Check if notification is expired
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Create a success notification
     */
    public static function createSuccess(string $title, string $message, array $options = []): self
    {
        return self::create(array_merge([
            'type' => self::TYPE_SUCCESS,
            'title' => $title,
            'message' => $message,
            'color' => 'green',
            'icon' => 'fas fa-check-circle'
        ], $options));
    }

    /**
     * Create an error notification
     */
    public static function createError(string $title, string $message, array $options = []): self
    {
        return self::create(array_merge([
            'type' => self::TYPE_ERROR,
            'title' => $title,
            'message' => $message,
            'color' => 'red',
            'icon' => 'fas fa-exclamation-circle',
            'is_important' => true
        ], $options));
    }

    /**
     * Create a warning notification
     */
    public static function createWarning(string $title, string $message, array $options = []): self
    {
        return self::create(array_merge([
            'type' => self::TYPE_WARNING,
            'title' => $title,
            'message' => $message,
            'color' => 'yellow',
            'icon' => 'fas fa-exclamation-triangle'
        ], $options));
    }

    /**
     * Create an info notification
     */
    public static function createInfo(string $title, string $message, array $options = []): self
    {
        return self::create(array_merge([
            'type' => self::TYPE_INFO,
            'title' => $title,
            'message' => $message,
            'color' => 'blue',
            'icon' => 'fas fa-info-circle'
        ], $options));
    }

    /**
     * Clean up expired notifications
     */
    public static function cleanupExpired(): int
    {
        return self::where('expires_at', '<', now())->delete();
    }

    /**
     * Get recent notifications with pagination
     */
    public static function getRecent(int $limit = 10): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return self::active()
            ->orderBy('is_important', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($limit);
    }

    /**
     * Get unread count
     */
    public static function getUnreadCount(): int
    {
        return self::unread()->active()->count();
    }
}
