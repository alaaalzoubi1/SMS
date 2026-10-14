<?php

namespace App\Http\Controllers;

use App\Jobs\SendFirebaseNotificationJob;
use App\Models\Doctor;
use App\Models\DoctorReservation;
use App\Models\DoctorService;
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

        // Reject up front rather than letting the request sit pending only to
        // fail (or get force-confirmed) when the doctor tries to approve it.
        if (!$this->doctorReservationService->worksOn(
            (int) $request->doctor_id,
            $request->date
        )) {
            return response()->json([
                'message' => 'الطبيب لا يملك دوام عمل في هذا اليوم، اختر تاريخاً آخر.'
            ], 422);
        }
        // The "you already have a pending request" warning only applies to the
        // SAME doctor. It used to look at any pending doctor request, so a
        // patient could not request a second, different doctor until the
        // first one accepted.
        if (!$request->boolean('confirm')){
            $previousReservation = DoctorReservation::where('user_id' , $userId)
                ->where('doctor_id', $request->doctor_id)
                ->where('status','pending')
                ->exists();
            if ($previousReservation)
            {
                return response()->json([
                    'message' => 'لديك بالفعل طلب بحالة قيد الانتظار لدى هذا الطبيب، هل تريد المتابعة فعلاً؟',
                    'requires_confirmation' => true,
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
            'message' => 'تم إرسال طلب الحجز بنجاح.',
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
                'message' => 'تم إنشاء الحجز بنجاح.',
                'data'    => $reservation->load('user'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ أثناء إنشاء الحجز.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:approved,rejected,cancelled,completed',
            'reason' => 'required_if:status,cancelled,rejected|string|max:1000',
            'force_confirm' => 'nullable|boolean'
        ], [
            'reason.required_if' => 'سبب الإلغاء أو الرفض مطلوب.',
        ]);

        DB::beginTransaction();

        try {

            $reservation = DoctorReservation::with([
                'user.account',
                'doctor.account',
                'doctorService'
            ])->lockForUpdate()->findOrFail($id);

            $this->authorize('manageReservations', $reservation);

            // pending  → approved | rejected | cancelled
            // approved → completed | cancelled   (emergency: the doctor can
            //            now cancel an already-approved booking, which was
            //            impossible before)
            if ($request->status === 'completed') {
                if ($reservation->status !== 'approved') {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'الحجز يجب أن يكون بحالة مقبول قبل تغييره إلى مكتمل.'
                    ], 403);
                }
            } elseif ($request->status === 'cancelled') {
                if (!in_array($reservation->status, ['pending', 'approved'], true)) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'لا يمكن إلغاء الحجز إلا إذا كان قيد الانتظار أو مقبولاً.'
                    ], 403);
                }
            } else {
                if ($reservation->status !== 'pending') {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'لا يمكن تغيير الحالة إلا إذا كان الحجز قيد الانتظار.'
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
                            'message' => 'لا يوجد وقت متاح ضمن دوام الطبيب في هذا اليوم. هل تريد المتابعة رغم ذلك؟',
                            'requires_confirmation' => true
                        ], 409);
                    }

                    // The doctor saw the warning and confirmed: accept the
                    // request even though it is after official working hours
                    // (it used to be refused here, so confirming did nothing).
                    $slot = $this->doctorReservationService
                        ->forceSlotForApproval($doctorId, $date, $duration);
                }

                $reservation->update([
                    'status'     => 'approved',
                    'start_time' => $slot['start_time']->format('H:i:s'),
                    'end_time'   => $slot['end_time']->format('H:i:s'),
                ]);

                $outsideWorkingHours = (bool) ($slot['outside_working_hours'] ?? false);
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
            | REJECT
            |--------------------------------------------------------------------------
            */
            elseif ($request->status === 'rejected') {

                $reservation->update([
                    'status' => 'rejected'
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
                'message' => 'تم تحديث حالة الحجز بنجاح.',
                'data'    => $reservation->fresh(['cancellation', 'doctorService']),
                'outside_working_hours' => $outsideWorkingHours ?? false,
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'حدث خطأ أثناء تحديث حالة الحجز.',
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
                'rejected' => sprintf(
                    'تم رفض حجزك لدى %s. السبب: %s',
                    $doctorName,
                    $reason
                ),
                'completed' => sprintf(
                    'تم اكتمال حجزك مع %s بنجاح.',
                    $doctorName
                ),
                default     => sprintf(
                    'تم تحديث حالة حجزك لدى %s.',
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

    /**
     * PATCH api/doctor/reservations/cancel-remaining
     *
     * Cancel every remaining reservation of a given day (doctor emergency /
     * day off) and notify each patient.
     *
     * Previously only `pending` rows were touched, so patients whose booking
     * was already approved for that day were neither cancelled nor notified —
     * which is the "nothing is sent when cancelling a specific day / bulk
     * cancellation" report. Approved bookings are now included (optionally
     * restrict with `statuses[]`), the date match uses whereDate, and the
     * whole operation is atomic; notifications go out after the commit.
     */
    public function cancelRemaining(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
            'reason' => 'required|string|max:500',
            'statuses' => 'sometimes|array|min:1',
            'statuses.*' => 'in:pending,approved',
        ], [
            'reason.required' => 'سبب الإلغاء مطلوب.',
        ]);

        $doctor = Doctor::where('account_id', auth()->id())
            ->firstOrFail();

        $reason = $request->reason;
        $statuses = $request->input('statuses', ['pending', 'approved']);

        $reservations = DB::transaction(function () use ($doctor, $request, $statuses, $reason) {
            $rows = DoctorReservation::with(['user.account', 'doctor'])
                ->where('doctor_id', $doctor->id)
                ->whereDate('date', $request->date)
                ->whereIn('status', $statuses)
                ->lockForUpdate()
                ->get();

            foreach ($rows as $reservation) {
                $reservation->update(['status' => 'cancelled']);

                $reservation->cancellation()->create([
                    'reason' => $reason,
                ]);
            }

            return $rows;
        });

        foreach ($reservations as $reservation) {
            $this->notifyUser($reservation, $reason);
        }

        if ($reservations->isEmpty()) {
            return response()->json([
                'message' => 'لا توجد حجوزات قابلة للإلغاء في هذا اليوم.',
                'cancelled_count' => 0,
            ]);
        }

        return response()->json([
            'message' => 'تم إلغاء جميع الحجوزات المتبقية بنجاح وإشعار المرضى.',
            'cancelled_count' => $reservations->count(),
        ]);
    }


}
