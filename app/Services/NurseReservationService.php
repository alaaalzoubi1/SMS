<?php
namespace App\Services;

use App\Jobs\SendFirebaseNotificationJob;
use App\Models\NurseReservation;
use App\Models\NurseService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use MatanYadaev\EloquentSpatial\Objects\Point;

class NurseReservationService
{

    /**
     * @throws Exception
     */
    public function create($userId, $data, $isAdmin = false)
    {
        try {

            $serviceIds = $data['nurse_service_ids'] ?? ($data['nurse_service_id'] ?? null);
            if (empty($serviceIds)) {
                throw new ModelNotFoundException('Doctor or service not available.');
            }
            $serviceIds = array_values((array) $serviceIds);
            $serviceIds = array_values(array_unique(array_map('intval', $serviceIds)));

            $services = NurseService::with('nurse.account')
                ->whereIn('id', $serviceIds)
                ->where('nurse_id', $data['nurse_id'])
                ->whereHas('nurse.account', function ($q) {
                    $q->active();
                })
                ->get();

            if ($services->count() !== count($serviceIds)) {
                throw new ModelNotFoundException('Doctor or service not available.');
            }

            $reservation = new NurseReservation();

            $reservation->user_id = $userId;
            $reservation->nurse_id = $data['nurse_id'];

            // Legacy column: the DB still requires it (NOT NULL FK), so it
            // keeps pointing at the first service. `price` is the real total
            // and every client should read `services` / `services_total_price`
            // instead — see NurseReservation::$appends.
            $reservation->nurse_service_id = $services->first()->id;
            $reservation->price = $services->sum('price');
            $reservation->reservation_type = $data['reservation_type'];
            $reservation->note = $data['note'] ?? null;
            $reservation->status = "pending";
            $reservation->reserved_by_admin = $isAdmin;

            if (isset($data['start_at']))
                $reservation->start_at = $data['start_at'];

            if (isset($data['end_at']))
                $reservation->end_at = $data['end_at'];

            if (isset($data['lat']) && isset($data['lng']))
                $reservation->location = new Point($data['lat'], $data['lng']);

            $reservation->save();

            $reservation->services()->attach(
                $services->mapWithKeys(fn (NurseService $s) => [$s->id => ['price' => $s->price]])
            );

            $reservation->loadMissing(['services.service', 'user:id,full_name']);

            // Notify the nurse. servicesLabel() covers every selected service,
            // whereas the singular nurseService only ever named the first.
            $nurseAccount = $reservation->nurse?->account;

            if ($nurseAccount?->fcm_token) {
                SendFirebaseNotificationJob::dispatch(
                    $nurseAccount->fcm_token,
                    'طلب حجز جديد',
                    sprintf(
                        'المستخدم %s طلب خدمة: %s.',
                        $reservation->user?->full_name ?? 'غير معروف',
                        $reservation->servicesLabel()
                    )
                );
            }

            return $reservation;
        }catch (ModelNotFoundException $e){
            throw new ModelNotFoundException('Doctor or service not available.');
        }

    }

}
