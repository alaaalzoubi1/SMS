<?php

namespace App\Http\Controllers;

use App\Jobs\SendFirebaseNotificationJob;
use App\Models\Doctor;
use App\Models\DoctorReservation;
use App\Models\DoctorService;
use App\Models\DoctorWorkSchedule;
use App\Models\User;
use App\Services\DoctorReservationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $request->validate([
            'doctor_service_id' => 'required|exists:doctor_services,id',
            'doctor_id' => 'required|exists:doctors,id',
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'confirm' => 'sometimes|boolean',
        ]);
        $userId = auth()->user()->user->id;
        if (!$request->confirm){
            $previousReservation = DoctorReservation::where('user_id' , $userId)
                ->where('status','pending')
                ->exists();
            if ($previousReservation)
            {
                return response()->json([
                    'message' => 'لديك بالفعل طلب بحالة قيد الانتظار هل تريد المتابعة فعلاً!'
                ]);
            }
        }
        $service = DoctorService::where('id', $request->doctor_service_id)
            ->where('doctor_id', $request->doctor_id)
            ->firstOrFail();

        $reservation = DoctorReservation::create([
            'doctor_service_id' => $service->id,
            'doctor_id' => $service->doctor_id,
            'user_id' => auth()->user()->user->id,
            'date' => $request->date,
            'start_time' => null,
            'end_time' => null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Reservation request submitted successfully.',
            'reservation' => $reservation,
        ], 201);
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
            ->with(['user.account', 'doctorService','cancellation']);

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
            'birthdate' => $data['birthdate'],
            'gender' => $data['gender'],
            'phone' => $data['phone'] ?? null,
        ]);
    }

    public function createStaticReservation(Request $request, DoctorReservationService $reservationService): JsonResponse
    {
        $request->validate([
            'full_name'     => 'required|string|max:255',
            'birthdate' => 'required|date|before_or_equal:today',
            'gender'         => 'required|in:male,female',
            'phone'          => 'nullable|string|max:25',
            'doctor_service_id' => 'required|exists:doctor_services,id',
            'date'          => 'required|date|after_or_equal:today',
        ]);

        DB::beginTransaction();
        try {
            $service = DoctorService::with('doctor')->findOrFail($request->doctor_service_id);
            $this->authorize('manage',$service);
            $user = $this->createStaticUser([
                'full_name' => $request->full_name,
                'birthdate' => $request->birthdate,
                'gender' => $request->gender,
                'phone' => $request->phone,
            ]);




            $reservation = DoctorReservation::create([
                'user_id'       => $user->id,
                'doctor_id'     => $service->doctor_id,
                'doctor_service_id' => $service->id,
                'date'          => $request->date,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Reservation created successfully.',
                'data'    => $reservation->load('user'),
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
            'status' => 'required|in:approved,cancelled,completed',
            'reason' => 'required_if:status,cancelled|string|max:1000',
            'force_confirm' => 'nullable|boolean'
        ]);

        DB::beginTransaction();

        try {

            $reservation = DoctorReservation::with([
                'user.account',
                'doctor.account',
                'doctorService'
            ])->lockForUpdate()->findOrFail($id);

            $this->authorize('manageReservations', $reservation);

            if ($request->status === 'completed') {
                if ($reservation->status !== 'approved') {
                    return response()->json([
                        'message' => 'Reservation must be approved before completing.'
                    ], 403);
                }
            } else {
                if ($reservation->status !== 'pending') {
                    return response()->json([
                        'message' => 'Cannot change status unless it is pending.'
                    ], 403);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | APPROVE
            |--------------------------------------------------------------------------
            */
            if ($request->status === 'approved') {

                $doctorId = $reservation->doctor_id;
                $date     = $reservation->date;
                $duration = $reservation->doctorService->duration_minutes;

                $slot = $this->doctorReservationService
                    ->findSlotForApproval($doctorId, $date, $duration);

                if (!$slot) {

                    if (!$request->boolean('force_confirm')) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'لا يوجد وقت متاح ضمن الدوام. هل تريد تأكيد الحجز رغم تجاوز وقت العمل؟',
                            'requires_confirmation' => true
                        ], 409);
                    }


                    $lastApproved = DoctorReservation::where('doctor_id', $doctorId)
                        ->where('date', $date)
                        ->where('status', 'approved')
                        ->orderByDesc('end_time')
                        ->first();

                    if ($lastApproved) {
                        $start = Carbon::parse($lastApproved->end_time);
                    } else {
                        // لا يوجد حجوزات أصلاً — نبدأ من أول الدوام
                        $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));

                        $schedule = DoctorWorkSchedule::where('doctor_id', $doctorId)
                            ->where('day_of_week', $dayOfWeek)
                            ->firstOrFail();

                        $start = Carbon::parse($date . ' ' . $schedule->start_time);
                    }

                    $end = $start->copy()->addMinutes($duration);

                    $slot = [
                        'start_time' => $start,
                        'end_time'   => $end,
                    ];
                }

                $reservation->update([
                    'status'     => 'approved',
                    'start_time' => $slot['start_time'],
                    'end_time'   => $slot['end_time'],
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | CANCEL
            |--------------------------------------------------------------------------
            */
            elseif ($request->status === 'cancelled') {

                $reservation->update([
                    'status' => 'cancelled'
                ]);

                $reservation->cancellation()->create([
                    'reason' => $request->reason,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | COMPLETE
            |--------------------------------------------------------------------------
            */
            elseif ($request->status === 'completed') {

                if ($reservation->date && Carbon::parse($reservation->date)->isFuture()) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'لا يمكن إكمال الحجز قبل حلول موعد الحجز.'
                    ], 422);
                }

                $reservation->update([
                    'status' => 'completed'
                ]);
            }

            DB::commit();

            $this->notifyUser($reservation, $request->reason);

            return response()->json([
                'message' => 'Reservation status updated successfully.',
                'data'    => $reservation->fresh(),
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Error updating reservation.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calendar view: reservations scheduled for a given day (by the `date`
     * column), like a Google Calendar. Optional `status` filter.
     */
    public function calendar(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'status' => 'nullable|in:pending,approved,rejected,cancelled,completed',
        ]);

        $doctor = Doctor::where('account_id', auth()->id())->firstOrFail();

        $reservations = DoctorReservation::query()
            ->where('doctor_id', $doctor->id)
            ->whereDate('date', $request->date)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('start_time')
            ->with(['user.account:id,full_name,phone_number', 'doctorService', 'cancellation'])
            ->get();

        return response()->json([
            'date' => $request->date,
            'reservations' => $reservations,
        ]);
    }

    private function notifyUser(DoctorReservation $reservation, ?string $reason = null): void
    {
        try {
            $userAccount = $reservation->user->account ?? null;

            if (!$userAccount || !$userAccount->fcm_token) {
                return;
            }

            $doctorName = $reservation->doctor->full_name ?? 'الطبيب';

            $title = 'تحديث حالة الحجز من الطبيب';

            $body = match ($reservation->status) {
                'approved'  => sprintf(
                    'تمت الموافقة على حجزك من %s بتاريخ %s من الساعة %s حتى %s.',
                    $doctorName,
                    $reservation->date,
                    $reservation->start_time,
                    $reservation->end_time
                ),
                'cancelled' => sprintf(
                    'تم إلغاء حجزك من %s. السبب: %s',
                    $doctorName,
                    $reason
                ),
                'completed' => sprintf(
                    'تم اكتمال حجزك مع %s بنجاح.',
                    $doctorName
                ),
            };

            SendFirebaseNotificationJob::dispatch(
                $userAccount->fcm_token,
                $title,
                $body
            );

        } catch (\Throwable $e) {
            Log::error('Failed to send user notification (doctor): ' . $e->getMessage());
        }
    }

    public function cancelRemaining(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
            'reason' => 'required|string|max:500',
        ]);

        $doctor = Doctor::where('account_id', auth()->id())
            ->firstOrFail();

        $reason = $request->reason;

        $pendingReservations = DoctorReservation::where('doctor_id', $doctor->id)
            ->where('date', $request->date)
            ->where('status', 'pending')
            ->get();

        foreach ($pendingReservations as $reservation) {
            $reservation->update(['status' => 'cancelled']);

            $reservation->cancellation()->create([
                'reason' => $reason,
            ]);

            $this->notifyUser($reservation, $reason);
        }

        return response()->json([
            'message' => 'تم إلغاء جميع الحجوزات المتبقية بنجاح.'
        ]);
    }


}
