<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Get notifications for the dropdown
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 10);
            $showRead = $request->boolean('show_read', false);
            
            $query = Notification::active()
                ->orderBy('is_important', 'desc')
                ->orderBy('created_at', 'desc');
            
            if (!$showRead) {
                $query->unread();
            }
            
            $notifications = $query->limit($limit)->get();
            $unreadCount = Notification::getUnreadCount();
            
            return response()->json([
                'success' => true,
                'notifications' => $notifications->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'type' => $notification->type,
                        'title' => $notification->title,
                        'message' => $notification->message,
                        'icon' => $notification->notification_icon,
                        'color' => $notification->notification_color,
                        'is_read' => $notification->is_read,
                        'is_important' => $notification->is_important,
                        'action_url' => $notification->action_url,
                        'action_text' => $notification->action_text,
                        'time_ago' => $notification->time_ago,
                        'created_at' => $notification->created_at->toISOString()
                    ];
                }),
                'unread_count' => $unreadCount,
                'has_more' => $notifications->count() === $limit
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch notifications', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications'
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        try {
            $notification->markAsRead();
            
            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
                'unread_count' => Notification::getUnreadCount()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark notification as read', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read'
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            $updated = Notification::unread()->active()->update(['is_read' => true]);
            
            return response()->json([
                'success' => true,
                'message' => "Marked {$updated} notifications as read",
                'unread_count' => 0
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark all notifications as read', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read'
            ], 500);
        }
    }

    /**
     * Delete a notification
     */
    public function destroy(Notification $notification): JsonResponse
    {
        try {
            $notification->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Notification deleted',
                'unread_count' => Notification::getUnreadCount()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification'
            ], 500);
        }
    }

    /**
     * Clear all read notifications
     */
    public function clearRead(): JsonResponse
    {
        try {
            $deleted = Notification::read()->delete();
            
            return response()->json([
                'success' => true,
                'message' => "Cleared {$deleted} read notifications",
                'unread_count' => Notification::getUnreadCount()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear read notifications', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear read notifications'
            ], 500);
        }
    }

    /**
     * Get notification statistics
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total' => Notification::count(),
                'unread' => Notification::unread()->count(),
                'important' => Notification::important()->count(),
                'by_type' => [
                    'success' => Notification::ofType(Notification::TYPE_SUCCESS)->count(),
                    'error' => Notification::ofType(Notification::TYPE_ERROR)->count(),
                    'warning' => Notification::ofType(Notification::TYPE_WARNING)->count(),
                    'info' => Notification::ofType(Notification::TYPE_INFO)->count(),
                    'form_submission' => Notification::ofType(Notification::TYPE_FORM_SUBMISSION)->count(),
                    'csv_upload' => Notification::ofType(Notification::TYPE_CSV_UPLOAD)->count(),
                    'duplicate_email' => Notification::ofType(Notification::TYPE_DUPLICATE_EMAIL)->count(),
                ]
            ];
            
            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get notification stats', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get notification statistics'
            ], 500);
        }
    }

    /**
     * Create a test notification
     */
    public function createTest(Request $request): JsonResponse
    {
        try {
            $type = $request->get('type', 'info');
            $title = $request->get('title', 'Test Notification');
            $message = $request->get('message', 'This is a test notification created at ' . now()->format('Y-m-d H:i:s'));
            
            $notification = match($type) {
                'success' => Notification::createSuccess($title, $message),
                'error' => Notification::createError($title, $message),
                'warning' => Notification::createWarning($title, $message),
                default => Notification::createInfo($title, $message)
            };
            
            return response()->json([
                'success' => true,
                'message' => 'Test notification created',
                'notification' => [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'color' => $notification->notification_color,
                    'icon' => $notification->notification_icon
                ],
                'unread_count' => Notification::getUnreadCount()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create test notification', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create test notification'
            ], 500);
        }
    }
}
