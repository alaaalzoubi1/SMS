<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\SendFirebaseNotificationJob;
use App\Jobs\SendFirebaseNotificationToAdminsJob;
use App\Models\HospitalCancellation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Hospital;
use App\Models\HospitalServiceReservation;
use App\Models\HospitalService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HospitalServiceReservationController extends Controller
{
    private function getAuthenticatedHospital()
    {
        $hospital = Hospital::where('account_id', Auth::id())->first();
        if (!$hospital) {
            abort(404, 'Hospital not found for the authenticated user.');
        }
        return $hospital;
    }

    /**
     * Age in whole years. User already keeps an `age` column in sync with
     * birthdate, so prefer it and derive it only as a fallback.
     */
    private function patientAge($user): ?int
    {
        if (!$user) {
            return null;
        }

        if (!empty($user->age)) {
            return (int) $user->age;
        }

        if (empty($user->birthdate)) {
            return null;
        }

        return (int) Carbon::parse($user->birthdate)->startOfDay()->diffInYears(now()->startOfDay());
    }

    private function formatDate($date): ?string
    {
        return $date ? Carbon::parse($date)->format('Y-m-d') : null;
    }

    /**
     * Single shape used by every read endpoint in this controller, including
     * the status-update response. The hospital app needs the patient's gender
     * and age on the confirmation screen, and the booking source, which the
     * raw reservation row never carried.
     */
    private function present(HospitalServiceReservation $reservation): array
    {
        $reservation->loadMissing('user');

        $user = $reservation->user;

        return [
            'id' => $reservation->id,
            'user_id' => $reservation->user_id,
            'user_name' => $user->full_name ?? 'N/A',
            'user_phone' => $user?->phone_number,
            'user_gender' => $user?->gender,
            'user_age' => $this->patientAge($user),
            'user_birthdate' => $this->formatDate($user?->birthdate),
            // The app renders this verbatim, so it must never be null.
            'source' => 'من التطبيق',
            'service_id' => $reservation->hospital_service_id,
            'service_name' => $reservation->hospitalService->service->service_name ?? 'N/A',
            'unit_price' => (float) $reservation->unit_price,
            'days' => $reservation->days,
            'final_price' => $reservation->final_price,
            'status' => $reservation->status,
            'reserved_by_admin' => $reservation->reserved_by_admin,
            'start_date' => $this->formatDate($reservation->start_date),
            'end_date' => $this->formatDate($reservation->end_date),
            'cancellation' => $reservation->cancellation,
        ];
    }

    public function index(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:pending,confirmed,accepted,cancelled,finished',
        ]);

        $hospital = $this->getAuthenticatedHospital();

        $reservations = HospitalServiceReservation::where('hospital_id', $hospital->id)
              ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
              ->with(['user', 'hospitalService.service','cancellation'])
              ->orderBy('start_date', 'desc')
              ->paginate()
              ->map(fn (HospitalServiceReservation $reservation) => $this->present($reservation));

        return response()->json($reservations);
    }

    public function show(string $id)
    {
        $hospital = $this->getAuthenticatedHospital();

        $reservation = HospitalServiceReservation::where('hospital_id', $hospital->id)
              ->where('id', $id)
              ->with(['user', 'hospitalService.service'])
              ->first();

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found or does not belong to this hospital'], 404);
        }


        return response()->json($this->present($reservation));
    }

    /**
     * Calendar view: reservations scheduled for a given day (by the
     * `start_date` column), like the doctor calendar. Optional `status` filter.
     */
    public function calendar(Request $request)
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'status' => 'nullable|in:pending,confirmed,accepted,cancelled,finished',
        ]);

        $hospital = $this->getAuthenticatedHospital();

        $reservations = HospitalServiceReservation::where('hospital_id', $hospital->id)
              ->whereDate('start_date', $request->date)
              ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
              ->with(['user', 'hospitalService.service', 'cancellation'])
              ->orderBy('start_date')
              ->get()
              ->map(fn (HospitalServiceReservation $reservation) => $this->present($reservation));

        return response()->json([
            'date' => $request->date,
            'reservations' => $reservations,
        ]);
    }



    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $hospital = $this->getAuthenticatedHospital();

        $reservation = HospitalServiceReservation::where('hospital_id', $hospital->id)
            ->where('id', $id)
            ->with(['user.account', 'hospitalService.service'])
            ->first();

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found or does not belong to this hospital'], 404);
        }

        $request->validate([
            'status' => 'required|string|in:pending,confirmed,accepted,cancelled,finished',
            'reason' => 'required_if:status,cancelled|nullable|string|max:1000'
        ], [
            'status.in'           => 'الحالة المدخلة غير صحيحة.',
            'reason.required_if'  => 'سبب الإلغاء مطلوب.',
        ]);

        $newStatus = $request->status;
        $oldStatus = $reservation->status;

        DB::beginTransaction();
        try {
            /* ───────────────────────────────
               RULE 1 — CANCELLED
               ─────────────────────────────── */
            if ($newStatus === 'cancelled') {
                if (!in_array($oldStatus, ['pending', 'accepted','confirmed'])) {
                    DB::rollBack();
                    return response()->json(['message' => 'لا يمكن الإلغاء إلا إذا كانت الحالة قيد الانتظار أو مقبولة أو مؤكدة'], 422);
                }
                if (!$request->reason) {
                    DB::rollBack();
                    return response()->json(['message' => 'سبب الإلغاء مطلوب'], 422);
                }
                HospitalCancellation::create([
                    'reservation_id' => $reservation->id,
                    'reason' => $request->reason
                ]);

                $this->notifyUser($reservation,
                    "إلغاء حجز",
                    "تم إلغاء حجزك من المستشفى {$hospital->name}. السبب: {$request->reason}"
                );
            }

            /* ───────────────────────────────
               RULE 2 — ACCEPTED
               ─────────────────────────────── */
            if ($newStatus === 'accepted') {

                if ($oldStatus !== 'pending') {
                    DB::rollBack();
                    return response()->json(['message' => 'يجب أن تكون الحالة قيد الانتظار ليتم القبول'], 422);
                }

                $deadlineHours = $hospital->reservation_confirmation_deadline;
                $body = "تم قبول طلب الحجز، يجب تثبيت الحجز خلال {$deadlineHours} ساعة وإلا سيتم إلغاؤه تلقائياً.";

                $this->notifyUser($reservation, "قبول الحجز", $body);
            }

            /* ───────────────────────────────
               RULE 3 — CONFIRMED
               ─────────────────────────────── */
            if ($newStatus === 'confirmed') {

                if ($oldStatus !== 'accepted') {
                    DB::rollBack();
                    return response()->json(['message' => 'يجب قبول الحجز قبل تأكيده'], 422);
                }

                // Confirming pins the start of the stay. The end date is left
                // alone; while it is null the reservation is still running
                // and `days` counts up to today.
                $reservation->start_date = Carbon::now()->toDateString();

                $service = $reservation->hospitalService;
                if ($service->capacity <= 0) {
                    DB::rollBack();
                    return response()->json(['message' => 'لا توجد سعة متبقية لهذه الخدمة'], 422);
                }
                $service->capacity -= 1;
                $service->save();

                $this->notifyUser(
                    $reservation,
                    "تأكيد الحجز",
                    "تم تثبيت حجزك من المستشفى {$hospital->name}."
                );
            }

            /* ───────────────────────────────
               RULE 4 — FINISHED
               ─────────────────────────────── */
            if ($newStatus === 'finished') {

                if ($oldStatus !== 'confirmed') {
                    DB::rollBack();
                    return response()->json(['message' => 'يجب تأكيد الحجز قبل إنهائه'], 422);
                }
                $reservation->end_date = Carbon::now()->toDateString();
                $service = $reservation->hospitalService;
                $service->capacity += 1;
                $service->save();

                $this->notifyUser(
                    $reservation,
                    "انتهاء الحجز",
                    "تم إنهاء الحجز الخاص بك في المستشفى {$hospital->name}."
                );
            }

            $reservation->status = $newStatus;
            $reservation->save();

            DB::commit();

            return response()->json([
                'message' => 'تم تحديث حالة الحجز بنجاح',
                'reservation' => $this->present($reservation->fresh())
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error updating reservation: " . $e->getMessage());

            return response()->json(['message' => 'Failed to update status'], 500);
        }
    }

    private function notifyUser($reservation, string $title, string $body)
    {
        try {
            $token = $reservation->user->account->fcm_token ?? null;
            if ($token) {
                SendFirebaseNotificationJob::dispatch($token, $title, $body);
            }
        } catch (\Throwable $e) {
            Log::error("Failed to send notification: " . $e->getMessage());
        }
    }


    public function destroy(string $id)
    {
        $hospital = $this->getAuthenticatedHospital();

        $reservation = HospitalServiceReservation::where('hospital_id', $hospital->id)
                                                ->where('id', $id)
                                                ->first();

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found or does not belong to this hospital'], 404);
        }

        DB::beginTransaction();
        try {
            $reservation->delete(); // Soft delete
            DB::commit();

            Log::info('Hospital Service Reservation soft-deleted:', ['reservation_id' => $id]);

            return response()->json(['message' => 'Reservation deleted successfully (soft-deleted)'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error soft-deleting reservation: " . $e->getMessage());
            return response()->json(['message' => 'Failed to delete reservation', 'error' => $e->getMessage()], 500);
        }
    }

    public function restore(string $id)
    {
        $hospital = $this->getAuthenticatedHospital();

        $reservation = HospitalServiceReservation::onlyTrashed()
                                                ->where('hospital_id', $hospital->id)
                                                ->where('id', $id)
                                                ->first();

        if (!$reservation) {
            return response()->json(['message' => 'Trashed reservation not found or does not belong to this hospital'], 404);
        }

        DB::beginTransaction();
        try {
            $reservation->restore();
            DB::commit();

            Log::info('Hospital Service Reservation restored:', ['reservation_id' => $id]);

            return response()->json(['message' => 'Reservation restored successfully'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error restoring reservation: " . $e->getMessage());
            return response()->json(['message' => 'Failed to restore reservation', 'error' => $e->getMessage()], 500);
        }
    }

    public function trashed()
    {
        $hospital = $this->getAuthenticatedHospital();

        $reservations = HospitalServiceReservation::onlyTrashed()
            ->where('hospital_id', $hospital->id)
->with(['user', 'hospitalService.service'])
              ->orderBy('start_date', 'desc')
              ->get()
              ->map(fn (HospitalServiceReservation $reservation) => $this->present($reservation));

        Log::info('Hospital Trashed Reservations fetched:', ['hospital_id' => $hospital->id, 'reservations_count' => $reservations->count()]);

        return response()->json($reservations);
    }


    public function makeReservation(Request $request)
    {
        $validated = $request->validate([
            'hospital_service_id' => 'required|exists:hospital_services,id',
            'confirm' => 'sometimes|boolean',
        ]);
        $userId = auth()->user()->user->id;
        if (!$request->confirm){
            $previousReservation = HospitalServiceReservation::where('user_id' , $userId)
                ->where('status','pending')
                ->exists();
            if ($previousReservation)
            {
                return response()->json([
                    'message' => 'لديك بالفعل طلب بحالة قيد الانتظار هل تريد المتابعة فعلاً!'
                ]);
            }
        }

        $hospitalService = HospitalService::with('hospital')
            ->where('id', $validated['hospital_service_id'])
            ->first();

        if ($hospitalService->capacity <= 0) {
            return response()->json([
                'message' => 'لا يوجد سعة كافية في المشفى حالياً'
            ]);
        }

        $validated['user_id'] = auth()->user()->user->id;
        $validated['hospital_id'] = $hospitalService->hospital_id;
        $validated['unit_price'] = $hospitalService->price;

        $reservation = HospitalServiceReservation::create($validated);

        $this->notifyAdminsOfNewReservation($reservation);

        return response()->json([
            'message' => "تم إنشاء الطلب سيتم التواصل معكم خلال دقائق يرجى الانتظار",
            'reservation_id' => $reservation->id,
        ]);
    }

    /**
     * Admin push notification for every patient-submitted hospital booking.
     * Previously nothing was dispatched here, which is why the admin panel
     * never saw incoming hospital reservation requests.
     */
    private function notifyAdminsOfNewReservation(HospitalServiceReservation $reservation): void
    {
        try {
            $reservation->loadMissing(['user', 'hospital', 'hospitalService.service']);

            $serviceName = $reservation->hospitalService?->service?->service_name ?? 'خدمة';
            $patientName = $reservation->user?->full_name ?? 'مستخدم';

            SendFirebaseNotificationToAdminsJob::dispatch(
                'طلب حجز خدمة مشفى جديد',
                sprintf(
                    'طلب %s حجز خدمة "%s" في مشفى %s.',
                    $patientName,
                    $serviceName,
                    $reservation->hospital?->name ?? 'غير محدد'
                )
            );
        } catch (\Throwable $e) {
            Log::error('Failed to queue admin notification for hospital reservation: ' . $e->getMessage());
        }
    }
    public function storeManual(Request $request): JsonResponse
    {
        $request->validate([
            'full_name' => 'required|string|max:255',
            'birthdate' => 'required|date|before_or_equal:today',
            'gender' => 'required|in:male,female',
            'phone' => 'nullable|string|max:25',
            'hospital_service_id' => 'required|exists:hospital_services,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'affect_capacity' => 'sometimes|boolean',
        ]);

        DB::beginTransaction();

        try {

            $hospital_id = auth()->user()->hospital->id;

            $service = HospitalService::where('id', $request->hospital_service_id)
                ->where('hospital_id', $hospital_id)
                ->lockForUpdate()
                ->first();

            if (!$service) {
                return response()->json([
                    'message' => 'The selected service does not belong to this hospital.'
                ], 400);
            }

            $affectCapacity = $request->boolean('affect_capacity', true);

            if ($affectCapacity) {
                if ($service->capacity <= 0) {
                    return response()->json([
                        'message' => 'No available capacity for this service.'
                    ], 400);
                }

                $service->decrement('capacity');
            }

            $user = User::create([
                'account_id' => null,
                'full_name' => $request->full_name,
                'birthdate' => $request->birthdate,
                'gender' => $request->gender,
                'phone' => $request->phone,
            ]);

            $reservation = HospitalServiceReservation::create([
                'user_id' => $user->id,
                'hospital_id' => $hospital_id,
                'hospital_service_id' => $service->id,
                'unit_price' => $service->price,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'status' => 'confirmed',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'تم إنشاء الحجز اليدوي بنجاح.',
                'data' => $this->present($reservation->fresh())
            ], 201);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create manual reservation.'
            ], 500);
        }
    }

}
