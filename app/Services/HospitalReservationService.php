<?php

namespace App\Services;

use App\Jobs\SendFirebaseNotificationJob;
use App\Models\AdminNotification;
use App\Models\Hospital;
use App\Models\HospitalService;
use App\Models\HospitalServiceReservation;
use App\Models\User;
use App\Support\AdminNotifier;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class HospitalReservationService
{
    /**
     * Thrown when the requested hospital service has no free beds left.
     * Carries the alternatives so the caller can show them to the patient.
     */
    public const NO_VACANCY_MESSAGE = 'لا يوجد شاغر حالياً في هذا المشفى لهذه الخدمة.';

    /**
     * Admin-side reservation.
     *
     * @throws ModelNotFoundException when the hospital/service is not available
     * @throws \DomainException       when there is no free bed (message is Arabic)
     */
    public function create($userId, $hospitalServiceId, $isAdmin = false)
    {
        $hospitalService = HospitalService::with(['hospital.account', 'service'])
            ->where('id', $hospitalServiceId)
            ->whereHas('hospital.account', function ($q) {
                $q->active();
            })
            ->first();

        if (!$hospitalService) {
            throw new ModelNotFoundException('المشفى أو الخدمة غير متاحة حالياً.');
        }

        if ($hospitalService->capacity <= 0) {
            throw new \DomainException(self::NO_VACANCY_MESSAGE);
        }

        $reservation = HospitalServiceReservation::create([
            'user_id' => $userId,
            'hospital_service_id' => $hospitalServiceId,
            'hospital_id' => $hospitalService->hospital_id,
            'unit_price' => $hospitalService->price,
            'reserved_by_admin' => $isAdmin
        ]);

        $this->notifyHospitalOfNewReservation($reservation);

        return $reservation;
    }

    /**
     * Other hospitals that currently have at least one free bed for the same
     * service, nearest province first (same province, then the rest).
     *
     * @return Collection<int, array>
     */
    public function alternativesFor(HospitalService $requested, int $limit = 5): Collection
    {
        $provinceId = $requested->hospital?->province_id;

        return HospitalService::query()
            ->with(['hospital:id,account_id,full_name,address,location,province_id', 'hospital.account:id,phone_number', 'hospital.province:id,name_ar,name_en'])
            ->where('service_id', $requested->service_id)
            ->where('id', '!=', $requested->id)
            ->where('capacity', '>', 0)
            ->whereNotNull('price')
            ->whereHas('hospital') // respects suspended / deleted hospitals
            ->get()
            ->sortBy(fn (HospitalService $s) => $s->hospital?->province_id == $provinceId ? 0 : 1)
            ->take($limit)
            ->values()
            ->map(fn (HospitalService $s) => [
                'hospital_service_id' => $s->id,
                'hospital_id'   => $s->hospital_id,
                'hospital_name' => $s->hospital?->full_name,
                'address'       => $s->hospital?->address,
                'province'      => $s->hospital?->province,
                'price'         => (float) $s->price,
                'available_beds'=> (int) $s->capacity,
                'phone_number'  => $s->hospital?->account?->phone_number,
            ]);
    }

    /**
     * The hospital itself was never told a new request arrived.
     */
    public function notifyHospitalOfNewReservation(HospitalServiceReservation $reservation): void
    {
        try {
            $reservation->loadMissing(['hospital.account', 'user', 'hospitalService.service']);

            $token = $reservation->hospital?->account?->fcm_token;
            if (!$token) {
                return;
            }

            SendFirebaseNotificationJob::dispatch(
                $token,
                'طلب حجز جديد',
                sprintf(
                    'وصل طلب جديد من %s لخدمة "%s".',
                    $reservation->user?->full_name ?? 'مستخدم',
                    $reservation->hospitalService?->service?->service_name ?? 'خدمة'
                )
            );
        } catch (\Throwable $e) {
            Log::error('Failed to notify hospital of new reservation: ' . $e->getMessage());
        }
    }

    /**
     * A patient tried to book but the hospital has no free bed. The admins
     * get an alert with the patient's contact details and the hospitals that
     * DO have a free bed, so they can call the patient and redirect them.
     */
    public function notifyAdminsOfNoVacancy(HospitalService $requested, ?User $patient, Collection $alternatives): void
    {
        $serviceName  = $requested->service?->service_name ?? 'خدمة';
        $hospitalName = $requested->hospital?->full_name ?? 'غير محدد';
        $patientName  = $patient?->full_name ?? 'مستخدم';
        $patientPhone = $patient?->phone_number;

        $body = sprintf(
            'حاول %s%s حجز خدمة "%s" في مشفى %s ولا يوجد شاغر. %s',
            $patientName,
            $patientPhone ? " ({$patientPhone})" : '',
            $serviceName,
            $hospitalName,
            $alternatives->isNotEmpty()
                ? 'يوجد شاغر في: ' . $alternatives->pluck('hospital_name')->filter()->implode('، ') . '. يرجى التواصل مع المريض.'
                : 'لا يوجد شاغر في أي مشفى آخر لهذه الخدمة حالياً.'
        );

        AdminNotifier::notify(
            AdminNotification::TYPE_HOSPITAL_NO_VACANCY,
            'طلب بدون شاغر',
            $body,
            [
                'patient_user_id'     => $patient?->id,
                'patient_name'        => $patientName,
                'patient_phone'       => $patientPhone,
                'hospital_id'         => $requested->hospital_id,
                'hospital_name'       => $hospitalName,
                'hospital_service_id' => $requested->id,
                'service_name'        => $serviceName,
                'alternatives'        => $alternatives->all(),
            ]
        );
    }
}
