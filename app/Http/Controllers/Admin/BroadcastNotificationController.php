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
use Illuminate\Support\Facades\Log;

class BroadcastNotificationController extends Controller
{
    public function broadcast(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:1000',
            'groups' => 'required|array|min:1',
            'groups.*' => 'in:users,nurses,doctors,hospitals',
        ], [
            'title.required'  => 'عنوان الإشعار مطلوب.',
            'body.required'   => 'نص الإشعار مطلوب.',
            'groups.required' => 'يجب اختيار فئة واحدة على الأقل.',
            'groups.*.in'     => 'الفئة المختارة غير صحيحة.',
        ]);

        $title = $request->title;
        $body = $request->body;

        // This was `$request->unique('groups')` — Illuminate\Http\Request has
        // no `unique()` method, so EVERY broadcast threw
        // BadMethodCallException (HTTP 500): "the notification failed to send".
        $groups = collect($request->input('groups', []))->unique()->values()->all();

        /*
         | Jobs must be collected *before* the batch is dispatched. The
         | previous order dispatched an empty batch first, so the `finally`
         | hook could fire (logging tokens_count = 0) before any job was
         | ever added.
         */
        $jobs = [];
        // One device can belong to several groups (a doctor also has a user
        // profile), so tokens are de-duplicated: nobody gets the push twice.
        $seenTokens = [];

        foreach ($groups as $group) {
            $query = match ($group) {
                'users'     => User::with('account:id,fcm_token'),
                'nurses'    => Nurse::with('account:id,fcm_token'),
                'doctors'   => Doctor::with('account:id,fcm_token'),
                'hospitals' => Hospital::with('account:id,fcm_token'),
            };

            $query->chunkById(500, function ($items) use (&$jobs, &$seenTokens, $title, $body) {
                foreach ($items as $item) {
                    $token = $item->account?->fcm_token;

                    if (empty($token) || isset($seenTokens[$token])) {
                        continue;
                    }

                    $seenTokens[$token] = true;
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

        try {
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
        } catch (\Throwable $e) {
            // e.g. the job_batches table is missing (run `php artisan migrate`)
            Log::error('Broadcast dispatch failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'تعذر بدء إرسال الإشعار. تأكد من تشغيل الترحيلات (migrate) وعامل الطابور (queue:work).',
                'error'   => $e->getMessage(),
            ], 500);
        }

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