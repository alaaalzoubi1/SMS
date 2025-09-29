<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseService
{
    protected \Kreait\Firebase\Contract\Messaging $messaging;

    public function __construct()
    {
        $factory = (new Factory)->withServiceAccount(storage_path('firebase/firebase_credentials.json'));
        $this->messaging = $factory->createMessaging();
    }

    public function sendNotification(string $deviceToken, string $title, string $body): void
    {
        $message = CloudMessage::withTarget('token', $deviceToken)
            ->withNotification(Notification::create($title, $body));

        $this->messaging->send($message);
    }
}

