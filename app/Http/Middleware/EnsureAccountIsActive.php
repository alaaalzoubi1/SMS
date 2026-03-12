<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return JsonResponse|mixed|Response
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($user->is_suspended) {
            auth()->logout();
            return response()->json([
                'message' => 'لقد تم إيقاف حسابك من قبل الإدارة. الرجاء التواصل مع الدعم الفني للتطبيق.'
            ], 403);
        }

        if (!$user->hasRole('admin')) {

            if (!is_null($user->subscription_expires_at) &&
                Carbon::parse($user->subscription_expires_at)->isPast()) {

                return response()->json([
                    'message' => 'انتهت صلاحية اشتراكك. الرجاء التواصل مع الإدارة لتجديد الاشتراك.'
                ], 403);
            }
        }

        return $next($request);
    }
}
