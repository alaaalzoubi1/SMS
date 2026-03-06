<?php
namespace App\Services;

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


            $service = NurseService::with('nurse.account')
                ->where('id', $data['nurse_service_id'])
                ->where('nurse_id', $data['nurse_id'])
                ->whereHas('nurse.account', function ($q) {
                    $q->active();
                })
                ->firstOrFail();

            $reservation = new NurseReservation();

            $reservation->user_id = $userId;
            $reservation->nurse_id = $data['nurse_id'];
            $reservation->nurse_service_id = $data['nurse_service_id'];
            $reservation->price = $service->price;
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

            return $reservation;
        }catch (ModelNotFoundException $e){
            throw new ModelNotFoundException('Doctor or service not available.');
        }

    }

}
