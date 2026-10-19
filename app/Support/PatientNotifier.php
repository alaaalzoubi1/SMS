<?php

namespace App\Support;

use App\Jobs\SendFirebaseNotificationJob;
use App\Models\AdminNotification;
use App\Models\DoctorReservation;
use App\Models\HospitalServiceReservation;
use App\Models\NurseReservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Sends a reservation-status notification to the patient — or to the admins
 * when there is nobody to send it to.
 *
 * A reservation the admin creates for someone who has no account
 * (`user_data` on the admin reserve endpoints) belongs to a "static" user
 * with account_id = null, so the patient can never receive a push. Those
 * updates (approved, rejected, cancelled, completed, …) now go to the admins
 * instead — push + dashboard inbox — together with the patient's name and
 * phone, so the admin can call the patient and pass the news on.
 */
class PatientNotifier
{
    public static function notify(Model $reservation, string $title, string $body): void
    {
        try {
            $reservation->loadMissing('user.account');

            $user    = $reservation->user;
            $account = $user?->account;

            // Normal case: the patient has an account → push to the patient.
            if ($account) {
                if ($account->fcm_token) {
                    SendFirebaseNotificationJob::dispatch($account->fcm_token, $title, $body);
                }

                return;
            }

            // No account: only admin-made reservations are forwarded (the
            // admin is the one in contact with that patient).
            if (!$reservation->reserved_by_admin) {
                return;
            }

            [$kind, $kindLabel, $provider] = self::describe($reservation);

            $patientName  = $user?->full_name ?? 'مريض';
            $patientPhone = $user?->phone_number;

            AdminNotifier::notify(
                AdminNotification::TYPE_ADMIN_RESERVATION_UPDATE,
                sprintf('%s — حجز من الإدارة #%d (%s)', $title, $reservation->id, $kindLabel),
                implode("\n", array_filter([
                    $body,
                    sprintf('المريض: %s%s', $patientName, $patientPhone ? " ({$patientPhone})" : ''),
                    'المريض لا يملك حساباً في التطبيق، يرجى إبلاغه بهذا التحديث.',
                ])),
                [
                    'reservation_type' => $kind,
                    'reservation_id'   => $reservation->id,
                    'status'           => $reservation->status,
                    'provider_name'    => $provider,
                    'patient_user_id'  => $reservation->user_id,
                    'patient_name'     => $patientName,
                    'patient_phone'    => $patientPhone,
                    'original_title'   => $title,
                    'original_body'    => $body,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send reservation notification: ' . $e->getMessage());
        }
    }

    /**
     * @return array{0:string,1:string,2:?string} [type, Arabic label, provider name]
     */
    private static function describe(Model $reservation): array
    {
        return match (true) {
            $reservation instanceof DoctorReservation => ['doctor', 'طبيب',
                $reservation->loadMissing('doctor')->doctor?->full_name],
            $reservation instanceof NurseReservation => ['nurse', 'ممرض',
                $reservation->loadMissing('nurse')->nurse?->full_name],
            $reservation instanceof HospitalServiceReservation => ['hospital', 'مشفى',
                $reservation->loadMissing('hospital')->hospital?->full_name],
            default => ['other', 'حجز', null],
        };
    }
}
