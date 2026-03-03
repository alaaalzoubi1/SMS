<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsNotSuspended
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

        if ($user && $user->is_suspended) {

            auth()->logout();

            return response()->json([
                'message' => 'لقد تم إيقاف حسابك من قبل الإدارة الرجاء التواصل مع الدعم الفني للتطبيق.'
            ], 403);
        }

        return $next($request);
    }
}
