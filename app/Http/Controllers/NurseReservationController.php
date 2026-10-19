<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersReservedByAdmin;
use App\Jobs\SendFirebaseNotificationJob;
use App\Support\PatientNotifier;
use App\Models\Nurse;
use App\Models\NurseCancellation;
use App\Models\NurseReservation;
use App\Http\Requests\StoreNurseReservationRequest;
use App\Http\Requests\UpdateNurseReservationRequest;
use App\Models\NurseService;
use App\Models\NurseSubserviceReservation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\NurseReservationRequest;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
class NurseReservationController extends Controller
{
    use AuthorizesRequests, FiltersReservedByAdmin;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|in:pending,accepted,rejected,completed,cancelled',
            'reservation_type' => 'nullable|in:direct,manual',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:100',
            'reserved_by_admin' => 'nullable',
        ]);
        $byAdmin = $this->reservedByAdmin($request);

        $nurse = Nurse::where('account_id', auth()->id())->firstOrFail();

        $query = NurseReservation::where('nurse_id', $nurse->id)
            ->when($byAdmin !== null, fn ($q) => $q->where('reserved_by_admin', $byAdmin))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->from, fn($q) => $q->whereDate('start_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('start_at', '<=', $request->to))
            ->when($request->reservation_type,fn($q) => $q->where('reservation_type',$request->reservation_type))
            ->orderBy('created_at', 'desc')
            ->with(['user.account','nurseService.service','services.service','cancellation']);

        $perPage = $request->input('per_page', 10);

        return response()->json(
            $query->paginate($perPage)
        );
    }


    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:accepted,cancelled,rejected,completed',
            'reason' => 'required_if:status,cancelled,rejected|string|max:1000'
        ]);

        DB::beginTransaction();

        try {

            $reservation = NurseReservation::with(['user.account', 'nurse.account'])
                ->find($id);

            if (!$reservation) {
                DB::rollBack();
                return response()->json(['message' => 'الحجز غير موجود.'], 404);
            }

            $this->authorize('manageNurseReservations', $reservation);

            $currentStatus = $reservation->status;
            $newStatus = $request->status;

            $allowedTransitions = [
                'pending'   => ['accepted', 'rejected', 'cancelled'],
                'accepted'  => ['completed', 'cancelled'],
                'rejected'  => [],
                'cancelled' => [],
                'completed' => [],
            ];

            if (!in_array($newStatus, $allowedTransitions[$currentStatus] ?? [])) {
                DB::rollBack();
                return response()->json([
                    'message' => 'لا يمكن تغيير حالة الحجز من "' . $this->statusLabel($currentStatus) . '" إلى "' . $this->statusLabel($newStatus) . '".'
                ], 400);
            }

            // Same rule as the doctor: a reservation requested for a later
            // date cannot be marked completed before that date arrives.
            if ($newStatus === 'completed' && $reservation->start_at
                && Carbon::parse($reservation->start_at)->startOfDay()->isFuture()) {
                DB::rollBack();
                return response()->json([
                    'message' => 'لا يمكن إكمال الحجز قبل حلول موعد الحجز.'
                ], 422);
            }

            // A rejection writes its own marker on the cancellation record so
            // the reason is reported back as a rejection, not as a cancellation.
            if ($newStatus === 'rejected') {
                $reservation->reject($request->reason);
            } else {
                $reservation->update([
                    'status' => $newStatus
                ]);

                if ($newStatus === 'cancelled') {
                    $reservation->cancellation()->updateOrCreate(
                        ['reservation_id' => $reservation->id],
                        ['reason' => $request->reason, 'status' => NurseCancellation::CANCELLED]
                    );
                }
            }

            DB::commit();

            try {
                $nurseName = $reservation->nurse->full_name ?? '';

                $title = "تحديث حالة الحجز";

                $body = match ($newStatus) {
                    'accepted'  => "تمت الموافقة على حجزك من الممرض $nurseName.",
                    'rejected'  => "تم رفض حجزك من الممرض $nurseName. السبب: {$request->reason}",
                    'cancelled' => "تم إلغاء حجزك من الممرض $nurseName. السبب: {$request->reason}",
                    'completed' => "تم اكتمال حجزك مع الممرض $nurseName بنجاح.",
                    default     => "تم تحديث حالة حجزك إلى $newStatus.",
                };

                // Patient with an account → push to the patient. Admin-made
                // reservation for someone without an account → the admins.
                PatientNotifier::notify($reservation, $title, $body);

            } catch (\Throwable $e) {
                Log::error('Failed to send notification: ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'تم تحديث حالة الحجز بنجاح.',
                'data' => $reservation->load(['cancellation', 'nurseService.service', 'services.service', 'user'])
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();
            Log::error($e->getMessage());

            return response()->json([
                'message' => 'فشل تحديث حالة الحجز.'
            ], 500);
        }
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending'   => 'قيد الانتظار',
            'accepted'  => 'مقبول',
            'rejected'  => 'مرفوض',
            'cancelled' => 'ملغي',
            'completed' => 'مكتمل',
            default     => $status,
        };
    }



    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */


    public function store(NurseReservationRequest $request)
    {
        DB::beginTransaction();

        try {
            $userId = auth()->user()->user->id;
            if (!$request->confirm){
                $previousReservation = NurseReservation::where('user_id' , $userId)
                    ->where('status','pending')
                    ->exists();
                if ($previousReservation)
                {
                    return response()->json([
                        'message' => 'لديك بالفعل طلب بحالة قيد الانتظار هل تريد المتابعة فعلاً!'
                    ]);
                }
            }
            $serviceIds = array_values(array_unique($request->nurse_service_ids));

            $services = NurseService::query()
                ->where('nurse_id', $request->nurse_id)
                ->whereIn('id', $serviceIds)
                ->get();

            if ($services->count() !== count($serviceIds)) {
                return response()->json(['message' => 'أحد الخدمات المحددة غير متاحة.'], 422);
            }

            $reservation = new NurseReservation();
            $reservation->user_id = $userId;
            $reservation->nurse_id = $request->nurse_id;

            // Legacy column (NOT NULL FK) — points at the first service only.
            // `price` is the real total; clients should render `services` /
            // `services_total_price` instead of the singular `nurseService`.
            $reservation->nurse_service_id = $services->first()->id;
            $reservation->price = $services->sum('price');
            $reservation->reservation_type = $request->reservation_type;
            $reservation->note = $request->note;

            if ($request->filled('start_at')) {
                $reservation->start_at = $request->start_at;
            }

            if ($request->filled('end_at')) {
                $reservation->end_at = $request->end_at;
            }

            if ($request->filled('lat') && $request->filled('lng')) {
                $reservation->location = new Point($request->lat,$request->lng);
            }
            $reservation->status = "pending";
            $reservation->save();

            $reservation->services()->attach(
                $services->mapWithKeys(fn (NurseService $s) => [$s->id => ['price' => $s->price]])
            );

            DB::commit();
            $reservation->loadMissing('services.service');

            $nurse = $reservation->nurse()->with('account')->first();
            if ($nurse && $nurse->account && $nurse->account->fcm_token) {
                // servicesLabel() lists every selected service. Using the
                // singular `nurseService` here only ever named the first one,
                // so a multi-service booking looked like a single-service one.
                $body = sprintf(
                    "المستخدم %s طلب خدمة: %s.",
                    auth()->user()->user->full_name ?? "غير معروف",
                    $reservation->servicesLabel(),
                );

                SendFirebaseNotificationJob::dispatch(
                    $nurse->account->fcm_token,
                    "طلب حجز جديد",
                    $body
                );
            }
            return response()->json([
                'message' => 'تم إنشاء الحجز بنجاح.',
                'data' => $reservation->load('nurseService', 'services.service', 'nurse.account:id'),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Reservation creation failed: ' . $e->getMessage());
            return response()->json(['message' => 'فشل تسجيل الحجز يرجى المحاولة مرة أخرى لاحقاً'], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(NurseReservation $nurseReservation)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(NurseReservation $nurseReservation)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateNurseReservationRequest $request, NurseReservation $nurseReservation)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(NurseReservation $nurseReservation)
    {
        //
    }
}
