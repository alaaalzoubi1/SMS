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
use Illuminate\Auth\Access\AuthorizationException;
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

        return User::create([
            'account_id' => 3,
            'full_name'    => $data['full_name'],
            'age' => $data['age'],
            'gender' => $data['gender']
        ]);
    }

    public function createStaticReservation(Request $request, DoctorReservationService $reservationService): JsonResponse
    {
        $request->validate([
            'full_name'     => 'required|string|max:255',
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
                'account_id' => 3,
                'full_name' => $request->full_name,
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
     * @throws AuthorizationException
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected,cancelled,completed',
        ]);


        $reservation = DoctorReservation::where('id', $id)
            ->first();
        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found or unauthorized.'], 404);
        }
        $this->authorize('manageReservations',$reservation);


        $reservation->status = $request->status;
        $reservation->save();

        return response()->json([
            'message' => 'Reservation status updated successfully.',
            'data' => $reservation
        ]);
    }
}
