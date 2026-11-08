<?php

namespace App\Jobs;

use App\Services\FirebaseService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendFirebaseNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels,Batchable;

    protected string $token;
    protected string $title;
    protected string $body;

    /** Optional FCM data payload (string values). `ring` = '1' marks a long / ringing alert. */
    protected array $data = []; // default keeps jobs queued before this change working

    public function __construct(string $token, string $title, string $body, array $data = [])
    {
        $this->token = $token;
        $this->title = $title;
        $this->body = $body;
        $this->data = $data;
    }

    public function handle(FirebaseService $firebaseService): void
    {
        $firebaseService->sendNotification($this->token, $this->title, $this->body, $this->data);
    }
}
