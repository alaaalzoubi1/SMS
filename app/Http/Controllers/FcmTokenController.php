<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lets any logged-in account (admin dashboard included) register or refresh
 * the device / browser FCM token without logging in again. Firebase rotates
 * tokens, and the admin dashboard had no way to register one at all.
 */
class FcmTokenController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string|max:1024',
        ]);

        $account = auth()->user();
        $account->fcm_token = trim($validated['fcm_token']);
        $account->save();

        return response()->json([
            'message' => 'تم تحديث رمز الإشعارات بنجاح.',
        ]);
    }

    public function destroy(): JsonResponse
    {
        $account = auth()->user();
        $account->fcm_token = null;
        $account->save();

        return response()->json([
            'message' => 'تم إيقاف الإشعارات على هذا الجهاز.',
        ]);
    }
}
