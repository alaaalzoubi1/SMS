<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Doctor;
use App\Models\DoctorReservation;
use App\Models\Nurse;
use App\Models\NurseCancellation;
use App\Models\NurseReservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Admin-side deletion of user / doctor / nurse accounts (hospital deletion
 * already existed in ManageHospitalsAccountsController).
 *
 * Deletion is a soft delete: reservations, ratings and cancellations keep
 * pointing at these rows through foreign keys, so a hard delete would break
 * the history (and fail on the FKs). The login is cut immediately because:
 *  - the account row is soft-deleted, so the JWT user provider no longer
 *    resolves it (every request with an old token gets 401), and
 *  - it is also flagged `is_suspended` (belt and braces + login message).
 *
 * Pending requests that the deleted provider would never answer are
 * cancelled with a reason so patients are not left waiting.
 */
class AccountDeletionService
{
    public const REASON = 'تم حذف الحساب من قبل الإدارة.';

    public function deleteAccount(Account $account): void
    {
        DB::transaction(function () use ($account) {
            if ($doctor = Doctor::withTrashed()->withSuspended()->where('account_id', $account->id)->first()) {
                $this->cancelPendingDoctorReservations($doctor);
                $doctor->delete();
            }

            if ($nurse = Nurse::withTrashed()->withSuspended()->where('account_id', $account->id)->first()) {
                $this->cancelPendingNurseReservations($nurse);
                $nurse->delete();
            }

            User::where('account_id', $account->id)->get()->each->delete();

            $account->forceFill([
                'is_suspended' => true,
                'fcm_token'    => null,
            ])->save();

            $account->delete();
        });
    }

    /**
     * Patients created manually (no account) can still be deleted.
     */
    public function deleteUser(User $user): void
    {
        DB::transaction(function () use ($user) {
            if ($user->account_id && ($account = Account::find($user->account_id))) {
                // A patient account that is ALSO a doctor / nurse is deleted
                // completely: the admin asked to delete the account.
                $this->deleteAccount($account);

                return;
            }

            $user->delete();
        });
    }

    public function restoreAccount(Account $account): void
    {
        DB::transaction(function () use ($account) {
            $account->restore();
            $account->forceFill(['is_suspended' => false])->save();

            Doctor::onlyTrashed()->where('account_id', $account->id)->get()->each->restore();
            Nurse::onlyTrashed()->where('account_id', $account->id)->get()->each->restore();
            User::onlyTrashed()->where('account_id', $account->id)->get()->each->restore();
        });
    }

    private function cancelPendingDoctorReservations(Doctor $doctor): void
    {
        DoctorReservation::where('doctor_id', $doctor->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('date', '>=', now()->toDateString())
            ->get()
            ->each(function (DoctorReservation $reservation) {
                $reservation->update(['status' => 'cancelled']);
                $reservation->cancellation()->create(['reason' => self::REASON]);
            });
    }

    private function cancelPendingNurseReservations(Nurse $nurse): void
    {
        NurseReservation::where('nurse_id', $nurse->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->get()
            ->each(function (NurseReservation $reservation) {
                $reservation->update(['status' => 'cancelled']);
                $reservation->cancellation()->updateOrCreate(
                    ['reservation_id' => $reservation->id],
                    ['reason' => self::REASON, 'status' => NurseCancellation::CANCELLED]
                );
            });
    }
}
