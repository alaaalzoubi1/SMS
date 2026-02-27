<?php

namespace App\Http\Controllers;

use App\Jobs\SendFirebaseNotificationJob;
use App\Models\DoctorReservation;
use App\Models\HospitalServiceReservation;
use App\Models\NurseReservation;
use App\Models\User;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{


    public function cancelNurseReservation(Request $request)
    {
        $request->validate([
            'reservation_id' => 'required|exists:nurse_reservations,id',
            'reason' => 'required|string|max:500'
        ]);

        $reservation = NurseReservation::with(['nurse.account', 'user:id,full_name'])
            ->findOrFail($request->reservation_id);

        if ($reservation->user_id !== auth()->user()->user->id) {
            return response()->json([
                'message' => 'This reservation does not belong to this user.'
            ], 403);
        }

        try {

            DB::transaction(function () use ($reservation, $request) {
                $reservation->cancel($request->reason);
            });

        } catch (\DomainException $e) {

            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
        $nurseToken = $reservation->nurse?->account?->fcm_token;

        if ($nurseToken) {

            $userName = $reservation->user->full_name ?? 'أحد المستخدمين';

            SendFirebaseNotificationJob::dispatch(
                $nurseToken,
                'تم إلغاء الحجز',
                "قام المستخدم {$userName} بإلغاء الحجز."
            );
        }
        return response()->json([
            'message' => 'تم إلغاء الحجز بنجاح.',
            'reservation' => $reservation->fresh()
        ]);
    }
    public function cancelDoctorReservation(Request $request)
    {
        $request->validate([
            'reservation_id' => 'required|exists:nurse_reservations,id',
            'reason' => 'required|string|max:500'
        ]);

        $reservation = DoctorReservation::with(['doctor.account', 'user:id,full_name'])
            ->findOrFail($request->reservation_id);

        if ($reservation->user_id !== auth()->user()->user->id) {
            return response()->json([
                'message' => 'This reservation does not belong to this user.'
            ], 403);
        }

        try {

            DB::transaction(function () use ($reservation, $request) {
                $reservation->cancel($request->reason);
            });

        } catch (\DomainException $e) {

            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
        $doctorToken = $reservation->doctor?->account?->fcm_token;

        if ($doctorToken) {

            $userName = $reservation->user->full_name ?? 'أحد المستخدمين';

            SendFirebaseNotificationJob::dispatch(
                $doctorToken,
                'تم إلغاء الحجز',
                "قام المستخدم {$userName} بإلغاء الحجز."
            );
        }
        return response()->json([
            'message' => 'تم إلغاء الحجز بنجاح.',
            'reservation' => $reservation->fresh()
        ]);
    }
    public function cancelHospitalReservation(Request $request)
    {
        $request->validate([
            'reservation_id' => 'required|exists:nurse_reservations,id',
            'reason' => 'required|string|max:500'
        ]);

        $reservation = HospitalServiceReservation::with(['hospital.account', 'user:id,full_name'])
            ->findOrFail($request->reservation_id);

        if ($reservation->user_id !== auth()->user()->user->id) {
            return response()->json([
                'message' => 'This reservation does not belong to this user.'
            ], 403);
        }

        try {

            DB::transaction(function () use ($reservation, $request) {
                $reservation->cancel($request->reason);
            });

        } catch (\DomainException $e) {

            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
        $hospitalToken = $reservation->hospital?->account?->fcm_token;

        if ($hospitalToken) {

            $userName = $reservation->user->full_name ?? 'أحد المستخدمين';

            SendFirebaseNotificationJob::dispatch(
                $hospitalToken,
                'تم إلغاء الحجز',
                "قام المستخدم {$userName} بإلغاء الحجز."
            );
        }
        return response()->json([
            'message' => 'تم إلغاء الحجز بنجاح.',
            'reservation' => $reservation->fresh()
        ]);
    }
}
