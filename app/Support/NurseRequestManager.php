<?php

namespace App\Support;

use App\Jobs\ExpireNurseRequestJob;
use App\Jobs\SendFirebaseNotificationJob;
use App\Models\Nurse;
use App\Models\NurseReservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * "One request at a time" for nurses.
 *
 * nurses.current_reservation_id holds the request the nurse is busy with:
 *   - set when a request is created (status pending),
 *   - kept while an accepted *direct* (instant) request is in progress,
 *   - cleared when the request is rejected / cancelled / completed /
 *     expired, or accepted as a *manual* (scheduled for later) booking.
 *
 * Every check is a single indexed column (or a primary-key lookup), never a
 * scan of the nurse's reservation history.
 *
 * A pending request the nurse does not answer within TIMEOUT_MINUTES expires
 * (status 'expired'): a delayed job does it on time, and the same rule is
 * applied lazily wherever the nurse's busy state is read, so a late queue
 * worker can never leave a nurse blocked.
 */
class NurseRequestManager
{
    public const TIMEOUT_MINUTES = 8;

    public const NOTIFICATION_TITLE = 'معافى يتمنى لكم الشفاء';

    /** FCM data key the apps use to show the long / ringing alert. */
    public const RING_TYPE = 'nurse_new_request';

    public static function deadlineFrom(?Carbon $from = null): Carbon
    {
        return ($from ?? Carbon::now())->copy()->addMinutes(self::TIMEOUT_MINUTES);
    }

    /**
     * Mark the nurse busy with this reservation. Atomic: only succeeds when
     * the nurse is free, so two patients booking the same nurse at the same
     * moment cannot both get through.
     */
    public static function claim(int $nurseId, int $reservationId): bool
    {
        self::freeIfStale($nurseId);

        return Nurse::withoutGlobalScopes()
            ->whereKey($nurseId)
            ->whereNull('current_reservation_id')
            ->update(['current_reservation_id' => $reservationId]) === 1;
    }

    /** Free the nurse, but only if they are still busy with this reservation. */
    public static function release(NurseReservation $reservation): void
    {
        Nurse::withoutGlobalScopes()
            ->whereKey($reservation->nurse_id)
            ->where('current_reservation_id', $reservation->id)
            ->update(['current_reservation_id' => null]);
    }

    /** Keep the busy flag in step with a status change made by the nurse/patient/admin. */
    public static function syncAfterStatusChange(NurseReservation $reservation): void
    {
        $stillBusy = $reservation->status === 'pending'
            || ($reservation->status === 'accepted' && $reservation->reservation_type === 'direct');

        if (!$stillBusy) {
            self::release($reservation);
        }
    }

    /** Is the nurse busy right now? (expires an overdue request on the way) */
    public static function isBusy(int $nurseId): bool
    {
        self::freeIfStale($nurseId);

        return Nurse::withoutGlobalScopes()
            ->whereKey($nurseId)
            ->whereNotNull('current_reservation_id')
            ->exists();
    }

    /**
     * Listing filter: free nurses, plus nurses whose pending request is
     * already past its deadline (the expiry job just has not run yet).
     */
    public static function onlyAvailable(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->where(function (Builder $q) use ($table) {
            $q->whereNull("{$table}.current_reservation_id")
                ->orWhereExists(function ($sub) use ($table) {
                    $sub->selectRaw('1')
                        ->from('nurse_reservations')
                        ->whereColumn('nurse_reservations.id', "{$table}.current_reservation_id")
                        ->where('nurse_reservations.status', 'pending')
                        ->where('nurse_reservations.response_deadline_at', '<', Carbon::now());
                });
        });
    }

    /**
     * Pending → expired, only if the nurse still has not answered. Returns
     * true when this call did the expiry (so notifications go out once).
     */
    public static function expire(NurseReservation $reservation): bool
    {
        $changed = NurseReservation::whereKey($reservation->id)
            ->where('status', 'pending')
            ->update(['status' => 'expired', 'updated_at' => Carbon::now()]);

        if ($changed !== 1) {
            return false;
        }

        $reservation->status = 'expired';
        self::release($reservation);

        try {
            PatientNotifier::notify(
                $reservation,
                self::NOTIFICATION_TITLE,
                'هذا الممرض غير متاح حالياً، يرجى طلب ممرض آخر أو التواصل مع فريق الدعم.'
            );

            $reservation->loadMissing('nurse.account');
            $nurseToken = $reservation->nurse?->account?->fcm_token;
            if ($nurseToken) {
                SendFirebaseNotificationJob::dispatch(
                    $nurseToken,
                    'انتهت مهلة الطلب',
                    'انتهت مهلة الرد على طلب الحجز رقم ' . $reservation->id . ' ولم يعد متاحاً.',
                    ['type' => 'nurse_request_expired', 'reservation_id' => (string) $reservation->id]
                );
            }
        } catch (\Throwable $e) {
            Log::error('Nurse request expiry notification failed: ' . $e->getMessage());
        }

        return true;
    }

    /** Ringing push to the nurse for a new request + schedule its expiry. */
    public static function announce(NurseReservation $reservation, string $patientName): void
    {
        ExpireNurseRequestJob::dispatch($reservation->id)
            ->delay($reservation->response_deadline_at)
            ->afterCommit();

        $reservation->loadMissing(['nurse.account', 'services.service']);
        $token = $reservation->nurse?->account?->fcm_token;

        if (!$token) {
            return;
        }

        SendFirebaseNotificationJob::dispatch(
            $token,
            'طلب حجز جديد',
            sprintf(
                'المستخدم %s طلب خدمة: %s. يرجى الرد خلال %d دقائق.',
                $patientName,
                $reservation->servicesLabel(),
                self::TIMEOUT_MINUTES
            ),
            [
                'type'                 => self::RING_TYPE,
                'ring'                 => '1',
                'reservation_id'       => (string) $reservation->id,
                'reservation_type'     => (string) $reservation->reservation_type,
                'response_deadline_at' => $reservation->response_deadline_at?->toIso8601String() ?? '',
                'timeout_seconds'      => (string) (self::TIMEOUT_MINUTES * 60),
            ]
        )->afterCommit();
    }

    /** If the nurse's current request is pending and overdue, expire it now. */
    private static function freeIfStale(int $nurseId): void
    {
        $currentId = Nurse::withoutGlobalScopes()->whereKey($nurseId)->value('current_reservation_id');

        if (!$currentId) {
            return;
        }

        $current = NurseReservation::withTrashed()->find($currentId);

        if (!$current || $current->trashed()) {
            Nurse::withoutGlobalScopes()->whereKey($nurseId)
                ->where('current_reservation_id', $currentId)
                ->update(['current_reservation_id' => null]);

            return;
        }

        if ($current->status === 'pending' && self::deadlineOf($current)->isPast()) {
            self::expire($current);

            return;
        }

        // Defensive: the flag points at a request that is no longer open.
        self::syncAfterStatusChange($current);
    }

    private static function deadlineOf(NurseReservation $reservation): Carbon
    {
        return $reservation->response_deadline_at
            ?? self::deadlineFrom(Carbon::parse($reservation->created_at));
    }
}
