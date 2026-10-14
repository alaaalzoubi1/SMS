<?php

namespace App\Support;

use App\Models\Account;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Shared login helpers:
 *  - "remember me": issue a long-lived token so the apps do not force a new
 *    login every JWT_TTL (15) minutes.
 *  - persist the device FCM token sent with the login, otherwise push
 *    notifications go to a stale device (or nowhere at all).
 */
trait IssuesJwtTokens
{
    protected function issueToken(Account $account, Request $request): string
    {
        if ($request->boolean('remember_me')) {
            JWTAuth::factory()->setTTL((int) config('jwt_remember.ttl', 43200));
        }

        return JWTAuth::fromUser($account);
    }

    protected function tokenTtlMinutes(Request $request): int
    {
        return $request->boolean('remember_me')
            ? (int) config('jwt_remember.ttl', 43200)
            : (int) config('jwt.ttl', 15);
    }

    protected function storeFcmToken(Account $account, Request $request): void
    {
        $token = $request->input('fcm_token');

        if (is_string($token) && trim($token) !== '' && $account->fcm_token !== $token) {
            $account->fcm_token = trim($token);
            $account->save();
        }
    }
}
