<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dashboard inbox for admin alerts (see App\Support\AdminNotifier).
 */
class AdminNotificationController extends Controller
{
    /** GET api/admin/notifications?unread=1&type=hospital_no_vacancy&per_page=20 */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'unread'   => 'nullable|in:0,1,true,false',
            'type'     => 'nullable|string|max:64',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $notifications = AdminNotification::query()
            ->when(filter_var($request->input('unread'), FILTER_VALIDATE_BOOLEAN), fn ($q) => $q->whereNull('read_at'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => AdminNotification::whereNull('read_at')->count(),
        ]);
    }

    /** GET api/admin/notifications/unread-count */
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'unread_count' => AdminNotification::whereNull('read_at')->count(),
        ]);
    }

    /** PATCH api/admin/notifications/{id}/read */
    public function markRead(int $id): JsonResponse
    {
        $notification = AdminNotification::findOrFail($id);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return response()->json([
            'message' => 'تم تعليم الإشعار كمقروء.',
            'notification' => $notification,
        ]);
    }

    /** PATCH api/admin/notifications/read-all */
    public function markAllRead(): JsonResponse
    {
        $count = AdminNotification::whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'message' => 'تم تعليم جميع الإشعارات كمقروءة.',
            'updated' => $count,
        ]);
    }
}
