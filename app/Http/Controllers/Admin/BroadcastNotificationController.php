<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendFirebaseNotificationJob;
use App\Models\Account;
use App\Models\BroadcastLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;

class BroadcastNotificationController extends Controller
{
    public function broadcast(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:1000',
            'groups' => 'required|array|min:1',
            'groups.*' => 'in:users,nurses,doctors,hospitals',
        ]);

        $title = $request->title;
        $body = $request->body;
        $groups = $request->groups;

        $tokens = collect();

        foreach ($groups as $group) {
            $query = match ($group) {
                'users' => \App\Models\User::query(),
                'nurses' => \App\Models\Nurse::query(),
                'doctors' => \App\Models\Doctor::query(),
                'hospitals' => \App\Models\Hospital::query(),
            };

            $query->with('account:id,fcm_token');

            $query->chunk(500, function ($items) use (&$tokens) {
                foreach ($items as $item) {
                    if (isset($item->account->fcm_token) && $item->account->fcm_token) {
                        $tokens->push($item->account->fcm_token);
                    }
                }
            });
        }

        $jobs = $tokens->map(fn($token) => new SendFirebaseNotificationJob($token, $title, $body));

        // Dispatch all jobs as a batch
        Bus::batch($jobs)
            ->then(function () use ($title, $body, $groups, $tokens) {
                BroadcastLog::create([
                    'title' => $title,
                    'body' => $body,
                    'groups' => $groups,
                    'tokens_count' => $tokens->count()
                ]);
            })
            ->dispatch();

        return response()->json([
            'message' => 'Notification broadcast batch dispatched successfully.',
            'tokens_count' => $tokens->count(),
        ]);
    }
    public function broadcastLogs()
    {
        return response()->json([
            'data' =>BroadcastLog::get()
        ]);
    }
}
