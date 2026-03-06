<?php


namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReservationUserService;
use App\Services\DoctorReservationService;
use App\Services\NurseReservationService;
use App\Services\HospitalReservationService;
use Exception;
use Illuminate\Http\Request;

class AdminReservationController extends Controller
{

    public function __construct(
        protected ReservationUserService $userService,
        protected DoctorReservationService $doctorService,
        protected NurseReservationService $nurseService,
        protected HospitalReservationService $hospitalService
    ) {}


    /**
     * @throws Exception
     */
    public function doctorReservation(Request $request)
    {

        $request->validate([

            'doctor_id' => 'required|exists:doctors,id',
            'doctor_service_id' => 'required|exists:doctor_services,id',
            'date' => 'required|date|after_or_equal:today',

            'user_id' => 'sometimes|exists:users,id',
            'user_data' => 'sometimes|array',

            'user_data.full_name' => 'required_with:user_data|string|max:255',
            'user_data.age' => 'required_with:user_data|integer|min:0',
            'user_data.gender' => 'required_with:user_data|in:male,female',

        ]);
        if ($request->filled('user_id') && $request->filled('user_data')) {
            return response()->json([
                'message' => 'You cannot send both user_id and user_data.'
            ], 422);
        }

        if (!$request->filled('user_id') && !$request->filled('user_data')) {
            return response()->json([
                'message' => 'You must send either user_id or user_data.'
            ], 422);
        }

        $user = $this->userService->resolveUser(
            $request->user_id,
            $request->user_data
        );

        $reservation = $this->doctorService->create(
            $user->id,
            $request->doctor_id,
            $request->doctor_service_id,
            $request->date,
            true
        );

        return response()->json($reservation);
    }


    /**
     * @throws Exception
     */
    public function nurseReservation(Request $request)
    {
        $request->validate([

            'nurse_id' => 'required|exists:nurses,id',
            'nurse_service_id' => 'required|exists:nurse_services,id',

            'reservation_type' => 'required|in:direct,manual',
            'note' => 'nullable|string|max:1000',

            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after:start_at',

            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],

            'user_id' => 'sometimes|exists:users,id',
            'user_data' => 'sometimes|array',

            'user_data.full_name' => 'required_with:user_data|string|max:255',
            'user_data.age' => 'required_with:user_data|integer|min:0',
            'user_data.gender' => 'required_with:user_data|in:male,female',

        ]);
        if ($request->filled('user_id') && $request->filled('user_data')) {
            return response()->json([
                'message' => 'You cannot send both user_id and user_data.'
            ], 422);
        }

        if (!$request->filled('user_id') && !$request->filled('user_data')) {
            return response()->json([
                'message' => 'You must send either user_id or user_data.'
            ], 422);
        }
        $user = $this->userService->resolveUser(
            $request->user_id,
            $request->user_data
        );

        $reservation = $this->nurseService->create(
            $user->id,
            $request->all(),
            true
        );

        return response()->json($reservation);
    }


    /**
     * @throws Exception
     */
    public function hospitalReservation(Request $request)
    {

        $request->validate([

            'hospital_service_id' => 'required|exists:hospital_services,id',

            'user_id' => 'sometimes|exists:users,id',
            'user_data' => 'sometimes|array',

            'user_data.full_name' => 'required_with:user_data|string|max:255',
            'user_data.age' => 'required_with:user_data|integer|min:0',
            'user_data.gender' => 'required_with:user_data|in:male,female',

        ]);
        if ($request->filled('user_id') && $request->filled('user_data')) {
            return response()->json([
                'message' => 'You cannot send both user_id and user_data.'
            ], 422);
        }

        if (!$request->filled('user_id') && !$request->filled('user_data')) {
            return response()->json([
                'message' => 'You must send either user_id or user_data.'
            ], 422);
        }

        $user = $this->userService->resolveUser(
            $request->user_id,
            $request->user_data
        );

        $reservation = $this->hospitalService->create(
            $user->id,
            $request->hospital_service_id,
            true
        );

        return response()->json($reservation);
    }

}
