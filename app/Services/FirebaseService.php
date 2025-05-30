<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

class FirebaseService
{
    protected \Kreait\Firebase\Contract\Messaging $messaging;

    public function __construct()
    {
        $factory = (new Factory)->withServiceAccount(base_path('firebase_credentials.json'));
        $this->messaging = $factory->createMessaging();
    }

    public function sendNotification(string $deviceToken, string $title, string $body): void
    {
        $message = CloudMessage::withTarget('token', $deviceToken)
            ->withNotification([
                'title' => $title,
                'body' => $body,
            ]);

        $this->messaging->send($message);
    }
}
