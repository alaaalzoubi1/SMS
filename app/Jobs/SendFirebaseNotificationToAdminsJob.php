<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fans a single message out to every admin account that has an FCM token.
 *
 * Jobs are collected before dispatching (see BroadcastNotificationController)
 * because a queued batch cannot be extended after it has been dispatched.
 */
class SendFirebaseNotificationToAdminsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $title,
        protected string $body,
    ) {
    }

    public function handle(): void
    {
        $tokens = \App\Models\Account::role('admin')
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->pluck('fcm_token')
            ->unique()
            ->values();

        foreach ($tokens as $token) {
            SendFirebaseNotificationJob::dispatch($token, $this->title, $this->body);
        }
    }
}