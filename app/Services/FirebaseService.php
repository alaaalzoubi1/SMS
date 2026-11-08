<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use App\Support\NurseRequestManager;

class FirebaseService
{
    protected ?\Kreait\Firebase\Contract\Messaging $messaging = null;

    /**
     * Resolved lazily. The constructor used to throw whenever the credentials
     * file was missing, which turned every queued notification job into a
     * hard failure (and, on a sync queue, a failed API response).
     */
    public function __construct()
    {
        $this->messaging = $this->resolveMessaging();
    }

    /**
     * @param array<string, scalar> $data Optional data payload. Values are
     *        sent as strings. With data['ring'] === '1' the message is sent
     *        with high priority so the app can show its long / ringing alert.
     */
    public function sendNotification(string $deviceToken, string $title, string $body, array $data = []): void
    {
        if (empty($deviceToken)) {
            Log::warning('FCM skipped: empty device token', ['title' => $title]);

            return;
        }

        $messaging = $this->messaging ??= $this->resolveMessaging();

        if ($messaging === null) {
            Log::error('FCM skipped: Firebase credentials unavailable', [
                'title' => $title,
                'body'  => $body,
            ]);

            return;
        }

        try {
            $message = CloudMessage::withTarget('token', $deviceToken)
                ->withNotification(Notification::create($title, $body));

            if ($data) {
                $data = array_map(fn ($v) => (string) $v, $data);
                $message = $message->withData($data);

                if (($data['ring'] ?? null) === '1') {
                    $message = $message
                        ->withAndroidConfig(AndroidConfig::fromArray([
                            'priority' => 'high',
                            'ttl' => (NurseRequestManager::TIMEOUT_MINUTES * 60) . 's',
                            'notification' => ['channel_id' => 'nurse_requests', 'sound' => 'default'],
                        ]))
                        ->withApnsConfig(ApnsConfig::fromArray([
                            'headers' => ['apns-priority' => '10'],
                            'payload' => ['aps' => ['sound' => 'default', 'interruption-level' => 'time-sensitive']],
                        ]));
                }
            }

            $messaging->send($message);
        } catch (\Throwable $e) {
            Log::error('FCM send failed: ' . $e->getMessage(), [
                'token' => $deviceToken,
                'title' => $title,
                'body'  => $body,
            ]);
        }
    }

    /**
     * Candidate credential paths, in priority order:
     *   1. FIREBASE_CREDENTIALS from the environment (absolute or
     *      storage-relative), which is what .env actually sets.
     *   2. the legacy hardcoded filename, for deployments that already have it.
     *   3. any single *.json sitting in storage/firebase.
     */
    private function resolveMessaging(): ?\Kreait\Firebase\Contract\Messaging
    {
        foreach ($this->credentialPaths() as $path) {
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }

            try {
                $factory = (new Factory)->withServiceAccount($path);

                return $factory->createMessaging();
            } catch (\Throwable $e) {
                Log::error('Firebase credentials rejected at ' . $path . ': ' . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function credentialPaths(): array
    {
        $paths = [];

        $configured = env('FIREBASE_CREDENTIALS') ?: config('services.firebase.credentials');

        if (is_string($configured) && $configured !== '') {
            $paths[] = $this->isAbsolutePath($configured)
                ? $configured
                : base_path($configured);
            $paths[] = storage_path($configured);
        }

        $paths[] = storage_path('firebase/sahtee-9cd53-firebase-adminsdk-fbsvc-b7608db178.json');

        $fallback = glob(storage_path('firebase/*.json')) ?: [];

        return array_values(array_unique(array_merge($paths, $fallback)));
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }
}