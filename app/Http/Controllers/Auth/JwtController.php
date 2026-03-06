<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

class JwtController extends Controller
{
    public function refresh()
    {
        try {

            $newToken = auth()->refresh();

            return response()->json([
                'access_token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => auth()->factory()->getTTL() * 60
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Token cannot be refreshed'
            ], 401);
        }
    }
}
