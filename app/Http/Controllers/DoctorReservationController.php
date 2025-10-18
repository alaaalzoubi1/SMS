<?php

namespace App\Http\Controllers;

use App\Jobs\SendFirebaseNotificationJob;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DoctorReservationController extends Controller
{
    use AuthorizesRequests;


    protected DoctorReservationService $doctorReservationService;

    public function __construct(DoctorReservationService $doctorReservationService)
    {
        $this->doctorReservationService = $doctorReservationService;
    }

    /**
     * Reserve an available slot for a user with a doctor service.
     */
    public function reserve(Request $request): JsonResponse
    {
        // Validate the request data
        $request->validate([
            'doctor_service_id' => 'required|exists:doctor_services,id',  // doctor_service_id instead of doctor_id
            'doctor_id' => 'required|exists:doctors,id', // Ensure doctor_id is provided
            'date' => 'required|date_format:Y-m-d',
        ]);

        // Get the reservation date and duration from the request
        $doctorServiceId = $request->doctor_service_id;
        $doctorId = $request->doctor_id;
        $date = $request->date;

        $service = DoctorService::where('id',$doctorServiceId)
            ->where('doctor_id',$doctorId)
            ->first();

        $duration = $service->duration_minutes;

        // Use the service to find the next available slot for the doctor service
        $availableSlot = $this->doctorReservationService->getNextAvailableSlot($doctorId, $date, $duration);

        if ($availableSlot) {
            // If available, create the reservation
            $reservation = DoctorReservation::create([
                'doctor_service_id' => $doctorServiceId,  // link to the doctor service
                'doctor_id' => $doctorId,  // link to the doctor
                'user_id' => auth()->user()->user->id,  // assuming you're using Auth for the user
                'date' => $date,
                'start_time' => $availableSlot['start_time'],
                'end_time' => $availableSlot['end_time'],
                'status' => 'pending',
            ]);

            return response()->json([
                'message' => 'Reservation successful.',
                'reservation' => $reservation,
            ], 201);
        }

        return response()->json([
            'message' => 'No available slot for the selected date and duration.',
        ], 400);
    }

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
            ->with(['user.account', 'doctor.account']) // حتى نقدر نرسل إشعار للمستخدم
            ->first();

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found or unauthorized.'], 404);
        }

        $this->authorize('manageReservations', $reservation);

        $reservation->status = $request->status;
        $reservation->save();

        // ✅ إرسال إشعار للمستخدم لما الطبيب يحدّث الحالة
        try {
            $userAccount = $reservation->user->account ?? null;
            $doctorName = $reservation->doctor->full_name ?? 'الطبيب';

            if ($userAccount && $userAccount->fcm_token) {
                $title = "تحديث حالة الحجز من الطبيب";
                $body = match ($reservation->status) {
                    'approved' => sprintf("تمت الموافقة على حجزك من %s.", $doctorName),
                    'rejected' => sprintf("تم رفض حجزك من %s.", $doctorName),
                    'cancelled' => sprintf("تم إلغاء حجزك من %s.", $doctorName),
                    'completed' => sprintf("تم اكتمال حجزك مع %s بنجاح.", $doctorName),
                    default => sprintf("تم تحديث حالة حجزك إلى %s من %s.", $reservation->status, $doctorName),
                };

                SendFirebaseNotificationJob::dispatch(
                    $userAccount->fcm_token,
                    $title,
                    $body
                );
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send user notification (doctor): ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Reservation status updated successfully.',
            'data' => $reservation,
        ]);
    }

}
