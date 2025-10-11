<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FirebaseService;

class FirebaseTestController extends Controller
{
    public function sendTestNotification(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
            'title' => 'required|string',
            'body' => 'required|string',
        ]);

        try {
            $firebase = new FirebaseService();
            $response = $firebase->sendNotification(
                $request->fcm_token,
                $request->title,
                $request->body
            );

            // check response type
            if (is_array($response) && isset($response['error'])) {
                return response()->json([
                    'status' => 'failed',
                    'message' => $response['error'],
                ], 400);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Notification sent successfully!',
                'firebase_response' => $response,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
