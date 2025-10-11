<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseService
{
    protected \Kreait\Firebase\Contract\Messaging $messaging;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        if (!file_exists(storage_path('firebase/sahtee-9cd53-firebase-adminsdk-fbsvc-b7608db178.json'))) {
            throw new Exception('Firebase credentials file not found!');
        }

        $factory = (new Factory)->withServiceAccount(
            storage_path('firebase/sahtee-9cd53-firebase-adminsdk-fbsvc-b7608db178.json')
        );

        $this->messaging = $factory->createMessaging();
    }

    public function sendNotification(string $deviceToken, string $title, string $body): void
    {
        try {
            $message = CloudMessage::withTarget('token', $deviceToken)
                ->withNotification(Notification::create($title, $body));

            $this->messaging->send($message);
        } catch (\Throwable $e) {
            Log::error('FCM send failed: ' . $e->getMessage(), [
                'token' => $deviceToken,
                'title' => $title,
                'body' => $body,
            ]);
        }
    }
}
