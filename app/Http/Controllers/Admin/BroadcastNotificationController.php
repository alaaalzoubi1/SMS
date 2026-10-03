<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendFirebaseNotificationJob;
use App\Models\BroadcastLog;
use App\Models\Doctor;
use App\Models\Hospital;
use App\Models\Nurse;
use App\Models\User;
use Illuminate\Bus\Batch;
use Illuminate\Http\Request;
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
        $groups = $request->unique('groups');

        /*
         | Jobs must be collected *before* the batch is dispatched. The
         | previous order dispatched an empty batch first, so the `finally`
         | hook could fire (logging tokens_count = 0) before any job was
         | ever added.
         */
        $jobs = [];

        foreach ($groups as $group) {
            $query = match ($group) {
                'users'     => User::with('account:id,fcm_token'),
                'nurses'    => Nurse::with('account:id,fcm_token'),
                'doctors'   => Doctor::with('account:id,fcm_token'),
                'hospitals' => Hospital::with('account:id,fcm_token'),
            };

            $query->chunkById(500, function ($items) use (&$jobs, $title, $body) {
                foreach ($items as $item) {
                    $token = $item->account?->fcm_token;

                    if (empty($token)) {
                        continue;
                    }

                    $jobs[] = new SendFirebaseNotificationJob($token, $title, $body);
                }
            });
        }

        if ($jobs === []) {
            return response()->json([
                'message'       => 'لا توجد حسابات تحتوي على رمز إشعار في المجموعات المحددة.',
                'tokens_queued' => 0,
            ], 422);
        }

        $batch = Bus::batch($jobs)
            ->name("broadcast:{$title}")
            ->finally(function (Batch $batch) use ($title, $body, $groups) {
                BroadcastLog::create([
                    'title'        => $title,
                    'body'         => $body,
                    'groups'       => $groups,
                    'tokens_count' => $batch->totalJobs,
                ]);
            })
            ->allowFailures()
            ->dispatch();

        return response()->json([
            'message'       => 'تم بدء إرسال الإشعار.',
            'batch_id'      => $batch->id,
            'tokens_queued' => count($jobs),
        ], 202);
    }

    public function broadcastLogs()
    {
        return response()->json([
            'data' => BroadcastLog::orderByDesc('created_at')->paginate(10)
        ]);
    }
}