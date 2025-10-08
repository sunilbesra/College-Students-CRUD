<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Create notification for form submission events
     */
    public static function createFormSubmissionNotification(string $type, array $data): ?Notification
    {
        try {
            $notification = match($type) {
                'created' => self::createFormSubmissionCreated($data),
                'processed' => self::createFormSubmissionProcessed($data),
                'failed' => self::createFormSubmissionFailed($data),
                'updated' => self::createFormSubmissionUpdated($data),
                'deleted' => self::createFormSubmissionDeleted($data),
                'duplicate' => self::createDuplicateEmailDetected($data),
                default => null
            };

            if ($notification) {
                Log::info('Notification created', [
                    'type' => $type,
                    'notification_id' => $notification->id,
                    'title' => $notification->title
                ]);
            }

            return $notification;
        } catch (\Exception $e) {
            Log::error('Failed to create form submission notification', [
                'type' => $type,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Create notification for CSV upload events
     */
    public static function createCsvUploadNotification(string $type, array $data): ?Notification
    {
        try {
            $notification = match($type) {
                'started' => self::createCsvUploadStarted($data),
                'completed' => self::createCsvUploadCompleted($data),
                'failed' => self::createCsvUploadFailed($data),
                default => null
            };

            if ($notification) {
                Log::info('CSV upload notification created', [
                    'type' => $type,
                    'notification_id' => $notification->id,
                    'title' => $notification->title
                ]);
            }

            return $notification;
        } catch (\Exception $e) {
            Log::error('Failed to create CSV upload notification', [
                'type' => $type,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Form submission created notification
     */
    private static function createFormSubmissionCreated(array $data): Notification
    {
        $email = $data['email'] ?? 'Unknown';
        $source = ucfirst($data['source'] ?? 'form');
        
        return Notification::create([
            'type' => Notification::TYPE_FORM_SUBMISSION,
            'title' => 'New Form Submission',
            'message' => "New {$source} submission received for {$email}",
            'icon' => 'fas fa-file-plus',
            'color' => 'blue',
            'action_url' => route('form_submissions.index'),
            'action_text' => 'View Submissions',
            'related_type' => 'FormSubmission',
            'related_id' => $data['submission_id'] ?? null,
            'data' => $data,
            'expires_at' => now()->addDays(7)
        ]);
    }

    /**
     * Form submission processed notification
     */
    private static function createFormSubmissionProcessed(array $data): Notification
    {
        $email = $data['email'] ?? 'Unknown';
        $status = $data['status'] ?? 'completed';
        
        return Notification::createSuccess(
            'Form Submission Processed',
            "Submission for {$email} has been {$status} successfully",
            [
                'action_url' => route('form_submissions.index'),
                'action_text' => 'View Details',
                'related_type' => 'FormSubmission',
                'related_id' => $data['submission_id'] ?? null,
                'data' => $data,
                'expires_at' => now()->addDays(3)
            ]
        );
    }

    /**
     * Form submission failed notification
     */
    private static function createFormSubmissionFailed(array $data): Notification
    {
        $email = $data['email'] ?? 'Unknown';
        $error = $data['error'] ?? 'Unknown error';
        
        return Notification::createError(
            'Form Submission Failed',
            "Submission for {$email} failed: {$error}",
            [
                'action_url' => route('form_submissions.index', ['status' => 'failed']),
                'action_text' => 'View Failed Submissions',
                'related_type' => 'FormSubmission',
                'related_id' => $data['submission_id'] ?? null,
                'data' => $data,
                'is_important' => true,
                'expires_at' => now()->addDays(30) // Keep failed notifications longer
            ]
        );
    }

    /**
     * Form submission updated notification
     */
    private static function createFormSubmissionUpdated(array $data): Notification
    {
        $email = $data['email'] ?? 'Unknown';
        $changesCount = count($data['changes'] ?? []);
        $changesText = $changesCount === 1 ? '1 field' : "{$changesCount} fields";
        
        return Notification::createInfo(
            'Form Submission Updated',
            "Submission for {$email} updated ({$changesText} changed)",
            [
                'icon' => 'fas fa-edit',
                'color' => 'blue',
                'action_url' => route('form_submissions.show', ['formSubmission' => $data['submission_id']]),
                'action_text' => 'View Changes',
                'related_type' => 'FormSubmission',
                'related_id' => $data['submission_id'] ?? null,
                'data' => $data,
                'expires_at' => now()->addDays(7)
            ]
        );
    }

    /**
     * Form submission deleted notification
     */
    private static function createFormSubmissionDeleted(array $data): Notification
    {
        $email = $data['email'] ?? 'Unknown';
        $operation = ucfirst($data['operation'] ?? 'delete');
        
        return Notification::createWarning(
            'Form Submission Deleted',
            "{$operation} submission for {$email} has been deleted",
            [
                'icon' => 'fas fa-trash',
                'color' => 'red',
                'action_url' => route('form_submissions.index'),
                'action_text' => 'View Submissions',
                'related_type' => 'FormSubmission',
                'related_id' => $data['submission_id'] ?? null,
                'data' => $data,
                'expires_at' => now()->addDays(30) // Keep deletion notifications longer
            ]
        );
    }

    /**
     * Duplicate email detected notification
     */
    private static function createDuplicateEmailDetected(array $data): Notification
    {
        $email = $data['email'] ?? 'Unknown';
        $source = ucfirst($data['source'] ?? 'form');
        $row = isset($data['row']) ? " (Row {$data['row']})" : '';
        
        return Notification::createWarning(
            'Duplicate Email Detected',
            "Duplicate email {$email} found in {$source} submission{$row}",
            [
                'icon' => 'fas fa-copy',
                'color' => 'orange',
                'action_url' => route('form_submissions.index', ['q' => $email]),
                'action_text' => 'View Duplicates',
                'related_type' => 'FormSubmission',
                'related_id' => $data['submission_id'] ?? null,
                'data' => $data,
                'expires_at' => now()->addDays(14)
            ]
        );
    }

    /**
     * CSV upload started notification
     */
    private static function createCsvUploadStarted(array $data): Notification
    {
        $fileName = $data['file_name'] ?? 'Unknown file';
        $totalRows = $data['total_rows'] ?? 0;
        
        return Notification::create([
            'type' => Notification::TYPE_CSV_UPLOAD,
            'title' => 'CSV Upload Started',
            'message' => "Processing {$fileName} with {$totalRows} rows",
            'icon' => 'fas fa-upload',
            'color' => 'indigo',
            'action_url' => route('form_submissions.index', ['source' => 'csv']),
            'action_text' => 'View CSV Submissions',
            'data' => $data,
            'expires_at' => now()->addHours(2) // Short-lived notification
        ]);
    }

    /**
     * CSV upload completed notification
     */
    private static function createCsvUploadCompleted(array $data): Notification
    {
        $fileName = $data['file_name'] ?? 'Unknown file';
        $summary = $data['summary'] ?? [];
        $validRows = $summary['valid_rows'] ?? 0;
        $errorRows = $summary['invalid_rows'] ?? 0;
        $duplicateRows = $summary['duplicate_rows'] ?? 0;
        
        $type = ($errorRows > 0 || $duplicateRows > 0) ? 'warning' : 'success';
        $title = 'CSV Upload Completed';
        $message = "Processed {$fileName}: {$validRows} valid";
        
        if ($errorRows > 0) {
            $message .= ", {$errorRows} errors";
        }
        if ($duplicateRows > 0) {
            $message .= ", {$duplicateRows} duplicates";
        }
        
        $notification = $type === 'success' 
            ? Notification::createSuccess($title, $message)
            : Notification::createWarning($title, $message);
            
        $notification->update([
            'action_url' => route('form_submissions.index', ['source' => 'csv']),
            'action_text' => 'View Results',
            'data' => $data,
            'expires_at' => now()->addDays(7)
        ]);
        
        return $notification;
    }

    /**
     * CSV upload failed notification
     */
    private static function createCsvUploadFailed(array $data): Notification
    {
        $fileName = $data['file_name'] ?? 'Unknown file';
        $error = $data['error'] ?? 'Unknown error';
        
        return Notification::createError(
            'CSV Upload Failed',
            "Failed to process {$fileName}: {$error}",
            [
                'action_url' => route('form_submissions.upload_csv'),
                'action_text' => 'Try Again',
                'data' => $data,
                'is_important' => true,
                'expires_at' => now()->addDays(30)
            ]
        );
    }

    /**
     * Clean up old notifications
     */
    public static function cleanup(): int
    {
        $expired = Notification::cleanupExpired();
        
        // Also clean up old read notifications (older than 30 days)
        $oldRead = Notification::read()
            ->where('created_at', '<', now()->subDays(30))
            ->delete();
            
        Log::info('Notification cleanup completed', [
            'expired_deleted' => $expired,
            'old_read_deleted' => $oldRead,
            'total_deleted' => $expired + $oldRead
        ]);
        
        return $expired + $oldRead;
    }

    /**
     * Create a system notification
     */
    public static function createSystemNotification(string $title, string $message, string $type = 'info', array $options = []): Notification
    {
        return match($type) {
            'success' => Notification::createSuccess($title, $message, $options),
            'error' => Notification::createError($title, $message, $options),
            'warning' => Notification::createWarning($title, $message, $options),
            default => Notification::createInfo($title, $message, $options)
        };
    }
}