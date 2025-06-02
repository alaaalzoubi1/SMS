<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Account;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (!$token = auth()->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        if (!auth()->user()->hasRole('admin'))
            return response()->json(['message' => 'Access denied.'], 403);

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



