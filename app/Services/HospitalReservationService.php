<?php

namespace App\Services;

use App\Models\HospitalService;
use App\Models\HospitalServiceReservation;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Kreait\Firebase\Exception\Messaging\NotFound;

class HospitalReservationService
{

    /**
     * @throws \Exception
     */
    public function create($userId, $hospitalServiceId, $isAdmin=false)
    {


        $hospitalService = HospitalService::with('hospital.account')
            ->where('id', $hospitalServiceId)
            ->whereHas('hospital.account', function ($q) {
                $q->active();
            })
            ->first();
        if (!$hospitalService) {
            throw new ModelNotFoundException('Hospital or service not available.');
        }

        if ($hospitalService->capacity <= 0)
            throw new Exception("No capacity available");

        return HospitalServiceReservation::create([
            'user_id' => $userId,
            'hospital_service_id' => $hospitalServiceId,
            'hospital_id' => $hospitalService->hospital_id,
            'reserved_by_admin' => $isAdmin
        ]);

    }

}
