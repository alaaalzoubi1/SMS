<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendFirebaseNotificationJob;
use App\Models\Account;
use App\Models\BroadcastLog;
use Illuminate\Bus\Batch;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;

class BroadcastNotificationController extends Controller
{
    public function broadcast(Request $request)
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

        $totalTokens = 0;

        $batch = Bus::batch([])
            ->finally(function (Batch $batch) use ($title, $body, $groups) {
                BroadcastLog::create([
                    'title' => $title,
                    'body' => $body,
                    'groups' => $groups,
                    'tokens_count' => $batch->totalJobs,
                ]);
            })
            ->dispatch();

        foreach ($groups as $group) {

            $query = match ($group) {
                'users' => \App\Models\User::query(),
                'nurses' => \App\Models\Nurse::query(),
                'doctors' => \App\Models\Doctor::query(),
                'hospitals' => \App\Models\Hospital::query(),
            };

            $query->with('account:id,fcm_token')
                ->chunk(500, function ($items) use ($batch, $title, $body, &$totalTokens) {

                    $jobs = [];

                    foreach ($items as $item) {
                        if (!empty($item->account?->fcm_token)) {
                            $jobs[] = new SendFirebaseNotificationJob(
                                $item->account->fcm_token,
                                $title,
                                $body
                            );

                            $totalTokens++;
                        }
                    }

                    if (!empty($jobs)) {
                        $batch->add($jobs);
                    }
                });
        }

        return response()->json([
            'message' => 'Broadcast batch started.',
            'tokens_queued' => $totalTokens,
        ]);
    }
    public function broadcastLogs()
    {
        return response()->json([
            'data' =>BroadcastLog::orderByDesc()->paginate(10)
        ]);
    }
}
