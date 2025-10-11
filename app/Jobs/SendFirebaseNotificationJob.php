<?php

namespace App\Jobs;

use App\Services\FirebaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendFirebaseNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $token;
    protected string $title;
    protected string $body;

    public function __construct(string $token, string $title, string $body)
    {
        $this->token = $token;
        $this->title = $title;
        $this->body = $body;
    }

    public function handle(FirebaseService $firebaseService): void
    {
        $firebaseService->sendNotification($this->token, $this->title, $this->body);
    }
}
