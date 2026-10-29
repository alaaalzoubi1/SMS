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
            'user_data.birthdate' => 'required_with:user_data|date|before_or_equal:today',
            'user_data.gender' => 'required_with:user_data|in:male,female',
            'user_data.phone' => 'nullable|string|max:25',

        ]);
        if ($request->filled('user_id') && $request->filled('user_data')) {
            return response()->json([
                'message' => 'لا يمكن إرسال user_id و user_data معاً.'
            ], 422);
        }

        if (!$request->filled('user_id') && !$request->filled('user_data')) {
            return response()->json([
                'message' => 'يجب إرسال user_id أو user_data.'
            ], 422);
        }

        // Same rule as the patient app: no booking on a day off, and no
        // booking for today once the doctor's shift is over. Checked before
        // resolveUser so no patient record is created for a refused request.
        if ($error = $this->doctorService->bookingDateError(
            (int) $request->doctor_id,
            (string) $request->date
        )) {
            return response()->json($error, 422);
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
            'nurse_service_id' => 'required_without:nurse_service_ids|exists:nurse_services,id',
            'nurse_service_ids' => 'required_without:nurse_service_id|array|min:1',
            'nurse_service_ids.*' => 'integer|distinct:strict|exists:nurse_services,id',

            'reservation_type' => 'required|in:direct,manual',
            'note' => 'nullable|string|max:1000',

            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after:start_at',

            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],

            'user_id' => 'sometimes|exists:users,id',
            'user_data' => 'sometimes|array',

            'user_data.full_name' => 'required_with:user_data|string|max:255',
            'user_data.birthdate' => 'required_with:user_data|date|before_or_equal:today',
            'user_data.gender' => 'required_with:user_data|in:male,female',
            'user_data.phone' => 'nullable|string|max:25',

        ]);
        if ($request->filled('user_id') && $request->filled('user_data')) {
            return response()->json([
                'message' => 'لا يمكن إرسال user_id و user_data معاً.'
            ], 422);
        }

        if (!$request->filled('user_id') && !$request->filled('user_data')) {
            return response()->json([
                'message' => 'يجب إرسال user_id أو user_data.'
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

        $reservation->load(['user', 'nurse.account', 'services']);

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
            'user_data.birthdate' => 'required_with:user_data|date|before_or_equal:today',
            'user_data.gender' => 'required_with:user_data|in:male,female',
            'user_data.phone' => 'nullable|string|max:25',

        ]);
        if ($request->filled('user_id') && $request->filled('user_data')) {
            return response()->json([
                'message' => 'لا يمكن إرسال user_id و user_data معاً.'
            ], 422);
        }

        if (!$request->filled('user_id') && !$request->filled('user_data')) {
            return response()->json([
                'message' => 'يجب إرسال user_id أو user_data.'
            ], 422);
        }

        $user = $this->userService->resolveUser(
            $request->user_id,
            $request->user_data
        );

        try {
            $reservation = $this->hospitalService->create(
                $user->id,
                $request->hospital_service_id,
                true
            );
        } catch (\DomainException $e) {
            // No free bed: used to bubble up as a generic 500. Return the
            // hospitals that do have a free bed so the admin can redirect.
            $requested = \App\Models\HospitalService::with(['hospital', 'service'])->find($request->hospital_service_id);

            return response()->json([
                'message' => $e->getMessage(),
                'no_vacancy' => true,
                'alternatives' => $requested ? $this->hospitalService->alternativesFor($requested) : [],
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'المشفى أو الخدمة غير متاحة حالياً.'], 404);
        }

        return response()->json($reservation);
    }

}
