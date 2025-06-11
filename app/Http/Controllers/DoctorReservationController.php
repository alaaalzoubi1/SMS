<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Doctor;
use App\Models\DoctorReservation;
use App\Http\Requests\StoreDoctorReservationRequest;
use App\Http\Requests\UpdateDoctorReservationRequest;
use App\Models\DoctorService;
use App\Models\User;
use App\Services\DoctorReservationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DoctorReservationController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|in:pending,approved,rejected,cancelled,completed',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $doctor = Doctor::where('account_id', auth()->id())->firstOrFail();

        $query = DoctorReservation::where('doctor_id', $doctor->id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->from, fn($q) => $q->whereDate('start_time', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('start_time', '<=', $request->to))
            ->orderBy('start_time', 'desc')
            ->with(['user.account', 'doctorService']);

        $perPage = $request->input('per_page', 10);

        return response()->json(
            $query->paginate($perPage)
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function createStaticUser(array $data): User
    {
        $uniqueId = Str::uuid()->toString();
        $email = "static_user_{$uniqueId}@sahtee.local";
        $account = Account::create([
            'full_name'    => $data['full_name'],
            'email'        => $email,
            'password'     => bcrypt(Str::random(10)), // Secure random password
            'phone_number' => $data['phone_number'] ?? null,
            'fcm_token'    => null,
        ]);
        return User::create([
            'account_id' => $account->id,
            'age' => $data['age'],
            'gender' => $data['gender']
        ]);
    }

    public function createStaticReservation(Request $request, DoctorReservationService $reservationService): JsonResponse
    {
        $request->validate([
            'full_name'     => 'required|string|max:255',
            'phone_number'  => 'nullable|string|max:20',
            'age' => 'required|integer|min:0|max:99',
            'gender'         => 'required|in:male,female',
            'doctor_service_id' => 'required|exists:doctor_services,id',
            'date'          => 'required|date|after_or_equal:today',
        ]);

        DB::beginTransaction();
        try {
            $service = DoctorService::with('doctor')->findOrFail($request->doctor_service_id);
            $this->authorize('manage',$service);
            $user = $this->createStaticUser([
                'full_name' => $request->full_name,
                'phone_number' => $request->phone_number,
                'age' => $request->age,
                'gender' => $request->gender
            ]);


            $slot = $reservationService->getNextAvailableSlot($service->doctor_id, $request->date, $service->duration_minutes);

            if (!$slot) {
                return response()->json(['message' => 'No available time slots on this date.'], 409);
            }

            $reservation = DoctorReservation::create([
                'user_id'       => $user->id,
                'doctor_id'     => $service->doctor_id,
                'doctor_service_id' => $service->id,
                'date'          => $request->date,
                'start_time'    => $slot['start_time'],
                'end_time'      => $slot['end_time'],
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Reservation created successfully.',
                'data'    => $reservation,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error creating reservation.', 'error' => $e->getMessage()], 500);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDoctorReservationRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(DoctorReservation $doctorReservation)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DoctorReservation $doctorReservation)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDoctorReservationRequest $request, DoctorReservation $doctorReservation)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DoctorReservation $doctorReservation)
    {
        //
    }
}
