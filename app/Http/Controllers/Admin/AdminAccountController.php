<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Doctor;
use App\Models\Nurse;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Delete / restore user, doctor and nurse accounts from the admin panel.
 * (There was no delete option for these three account types; hospitals
 * already had one.)
 *
 *   DELETE api/admin/user/{id}            PATCH api/admin/user/{id}/restore
 *   DELETE api/admin/doctor/{id}          PATCH api/admin/doctor/{id}/restore
 *   DELETE api/admin/nurse/{id}           PATCH api/admin/nurse/{id}/restore
 *
 * {id} is the profile id (users.id / doctors.id / nurses.id) — the same id
 * the admin lists already return.
 */
class AdminAccountController extends Controller
{
    public function __construct(protected AccountDeletionService $deletion)
    {
    }

    public function destroyUser(int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'المستخدم غير موجود.'], 404);
        }

        if ($user->account_id && $this->isAdminAccount($user->account_id)) {
            return response()->json(['message' => 'لا يمكن حذف حساب مدير.'], 422);
        }

        return $this->run(fn () => $this->deletion->deleteUser($user), 'تم حذف حساب المستخدم.', $id);
    }

    public function destroyDoctor(int $id): JsonResponse
    {
        $doctor = Doctor::withSuspended()->with('account')->find($id);

        if (!$doctor || !$doctor->account) {
            return response()->json(['message' => 'الطبيب غير موجود.'], 404);
        }

        return $this->run(fn () => $this->deletion->deleteAccount($doctor->account), 'تم حذف حساب الطبيب.', $id);
    }

    public function destroyNurse(int $id): JsonResponse
    {
        $nurse = Nurse::withSuspended()->with('account')->find($id);

        if (!$nurse || !$nurse->account) {
            return response()->json(['message' => 'الممرض غير موجود.'], 404);
        }

        return $this->run(fn () => $this->deletion->deleteAccount($nurse->account), 'تم حذف حساب الممرض.', $id);
    }

    public function restoreUser(int $id): JsonResponse
    {
        $user = User::withTrashed()->find($id);

        if (!$user) {
            return response()->json(['message' => 'المستخدم غير موجود.'], 404);
        }
        if (!$user->trashed()) {
            return response()->json(['message' => 'الحساب ليس محذوفاً.'], 400);
        }

        $account = $user->account_id ? Account::withTrashed()->find($user->account_id) : null;

        return $this->run(function () use ($user, $account) {
            $account ? $this->deletion->restoreAccount($account) : $user->restore();
        }, 'تمت استعادة حساب المستخدم.', $id);
    }

    public function restoreDoctor(int $id): JsonResponse
    {
        $doctor = Doctor::withTrashed()->withSuspended()->find($id);

        if (!$doctor) {
            return response()->json(['message' => 'الطبيب غير موجود.'], 404);
        }
        if (!$doctor->trashed()) {
            return response()->json(['message' => 'الحساب ليس محذوفاً.'], 400);
        }

        $account = Account::withTrashed()->find($doctor->account_id);

        return $this->run(fn () => $account ? $this->deletion->restoreAccount($account) : $doctor->restore(),
            'تمت استعادة حساب الطبيب.', $id);
    }

    public function restoreNurse(int $id): JsonResponse
    {
        $nurse = Nurse::withTrashed()->withSuspended()->find($id);

        if (!$nurse) {
            return response()->json(['message' => 'الممرض غير موجود.'], 404);
        }
        if (!$nurse->trashed()) {
            return response()->json(['message' => 'الحساب ليس محذوفاً.'], 400);
        }

        $account = Account::withTrashed()->find($nurse->account_id);

        return $this->run(fn () => $account ? $this->deletion->restoreAccount($account) : $nurse->restore(),
            'تمت استعادة حساب الممرض.', $id);
    }

    private function isAdminAccount(int $accountId): bool
    {
        return (bool) Account::find($accountId)?->hasRole('admin');
    }

    private function run(callable $action, string $successMessage, int $id): JsonResponse
    {
        try {
            $action();
        } catch (\Throwable $e) {
            Log::error('Admin account delete/restore failed: ' . $e->getMessage());

            return response()->json(['message' => 'تعذر تنفيذ العملية، حاول مرة أخرى.'], 500);
        }

        return response()->json([
            'message' => $successMessage,
            'id' => $id,
        ]);
    }
}
