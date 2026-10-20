<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

class JwtController extends Controller
{
    /**
     * GET|POST api/refresh  (also api/{role}/refresh)
     *
     * Send the current (even expired) token as `Authorization: Bearer …`, or
     * as `token` in the body / query when the header can't be set. Works
     * while the token is within JWT_REFRESH_TTL (14 days by default).
     *
     * The response carries the new token as both `token` (the key every
     * login returns) and `access_token` (the old key), so either works.
     */
    public function refresh(\Illuminate\Http\Request $request)
    {
        try {
            // Always take the token from THIS request (header first, then
            // body/query) instead of whatever the JWT service may still hold.
            $token = $request->bearerToken() ?: $request->input('token');
            if (!is_string($token) || $token === '') {
                return response()->json([
                    'message' => 'يجب إرسال التوكن الحالي لتجديد الجلسة.',
                    'error' => 'Token not provided',
                ], 401);
            }
            auth()->setToken($token);

            $newToken = auth()->refresh();

            return response()->json([
                'message' => 'تم تجديد الجلسة بنجاح.',
                'token' => $newToken,
                'access_token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => auth()->factory()->getTTL() * 60
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'انتهت صلاحية الجلسة، يرجى تسجيل الدخول من جديد.',
                'error' => 'Token cannot be refreshed',
            ], 401);
        }
    }
}
