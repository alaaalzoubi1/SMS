<?php

namespace App\Support;

use App\Jobs\SendFirebaseNotificationToAdminsJob;
use App\Models\AdminNotification;
use Illuminate\Support\Facades\Log;

/**
 * One entry point for "tell the admins": stores the alert for the dashboard
 * (GET api/admin/notifications) and pushes it to every admin device/browser
 * that registered an FCM token.
 */
class AdminNotifier
{
    public static function notify(string $type, string $title, string $body, array $data = []): void
    {
        try {
            AdminNotification::create([
                'type'  => $type,
                'title' => $title,
                'body'  => $body,
                'data'  => $data,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to store admin notification: ' . $e->getMessage());
        }

        try {
            SendFirebaseNotificationToAdminsJob::dispatch($title, $body);
        } catch (\Throwable $e) {
            Log::error('Failed to queue admin push notification: ' . $e->getMessage());
        }
    }
}
