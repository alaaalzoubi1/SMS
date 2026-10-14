<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Account;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Support\IssuesJwtTokens;

class AdminAuthController extends Controller
{
    use IssuesJwtTokens;

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'       => 'required|email',
            'password'    => 'required|string',
            'fcm_token'   => 'nullable|string|max:1024',
            'remember_me' => 'sometimes|boolean',
        ]);

        $credentials = $request->only('email', 'password');

        if ($request->boolean('remember_me')) {
            auth()->factory()->setTTL((int) config('jwt_remember.ttl', 43200));
        }

        if (!$token = auth()->attempt($credentials)) {
            return response()->json([
                'error'   => 'Invalid Credintials',
                'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
            ], 401);
        }
        if (!auth()->user()->hasRole('admin'))
            return response()->json(['message' => 'غير مصرح لك بالدخول.'], 403);

        // The admin account never stored an FCM token, so every
        // "notify the admins" push (new hospital booking, …) had no target.
        $this->storeFcmToken(auth()->user(), $request);

        return $this->respondWithToken($token);
    }

    public function logout(): JsonResponse
    {
        auth()->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    public function me(): JsonResponse
    {
        return response()->json(auth()->user());
    }

    protected function respondWithToken($token): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth()->factory()->getTTL() * 60
        ]);
    }
}



