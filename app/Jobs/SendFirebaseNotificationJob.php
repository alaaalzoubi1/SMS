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

    protected string $deviceToken;
    protected string $title;
    protected string $body;

    /**
     * Create a new job instance.
     */
    public function __construct(string $deviceToken, string $title, string $body)
    {
        $this->deviceToken = $deviceToken;
        $this->title = $title;
        $this->body = $body;
    }

    /**
     * Execute the job.
     */
    public function handle(FirebaseService $firebase): void
    {
        $firebase->sendNotification(
            $this->deviceToken,
            $this->title,
            $this->body
        );
    }
}
