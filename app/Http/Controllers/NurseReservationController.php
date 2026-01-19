<?php

namespace App\Http\Controllers;

use App\Jobs\SendFirebaseNotificationJob;
use App\Models\Nurse;
use App\Models\NurseReservation;
use App\Http\Requests\StoreNurseReservationRequest;
use App\Http\Requests\UpdateNurseReservationRequest;
use App\Models\NurseSubserviceReservation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\NurseReservationRequest;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Illuminate\Support\Facades\Log;
class NurseReservationController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|in:pending,approved,rejected,completed',
            'reservation_type' => 'nullable|in:direct,manual',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $nurse = Nurse::where('account_id', auth()->id())->firstOrFail();

        $query = NurseReservation::where('nurse_id', $nurse->id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->from, fn($q) => $q->whereDate('start_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('start_at', '<=', $request->to))
            ->when($request->reservation_type,fn($q) => $q->where('reservation_type',$request->reservation_type))
            ->orderBy('start_at', 'desc')
            ->with(['user.account','nurseService', 'subserviceReservations']);

        $perPage = $request->input('per_page', 10);

        return response()->json(
            $query->paginate($perPage)
        );
    }


    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,accepted,cancelled,rejected,completed',
        ]);

        $reservation = NurseReservation::with(['user.account', 'nurse.account'])->find($id);

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found or unauthorized.'], 404);
        }

        $this->authorize('manageNurseReservations', $reservation);

        $currentStatus = $reservation->status;
        $newStatus = $request->status;

        $allowedTransitions = [
            'pending'   => ['accepted', 'rejected', 'cancelled'],
            'accepted'  => ['completed', 'cancelled'],
            'rejected'  => [],
            'cancelled' => [],
            'completed'  => [],
        ];

        if (!in_array($newStatus, $allowedTransitions[$currentStatus])) {
            return response()->json(['message' => "Cannot change status from $currentStatus to $newStatus."], 400);
        }

        $reservation->status = $newStatus;
        $reservation->save();

        try {
            $userAccount = $reservation->user->account ?? null;
            $nurseName = $reservation->nurse->full_name ?? '';

            if ($userAccount && $userAccount->fcm_token) {
                $title = "تحديث حالة الحجز";
                $body = match ($newStatus) {
                    'accepted'  => "تمت الموافقة على حجزك من الممرض $nurseName. يرجى تثبيت الحجز خلال المهلة المحددة.",
                    'rejected'  => "تم رفض حجزك من الممرض $nurseName.",
                    'cancelled' => "تم إلغاء حجزك من الممرض $nurseName.",
                    'completed'  => "تم اكتمال حجزك مع الممرض $nurseName بنجاح.",
                    default     => "تم تحديث حالة حجزك إلى $newStatus.",
                };

                SendFirebaseNotificationJob::dispatch($userAccount->fcm_token, $title, $body);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send notification: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Reservation status updated successfully.',
            'data' => $reservation
        ]);
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
            // Create the main reservation
            $userId = auth()->user()->user->id;
            if (!$request->comfirm){
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

            $reservation = new NurseReservation();
            $reservation->user_id = $userId;
            $reservation->nurse_id = $request->nurse_id;
            $reservation->nurse_service_id = $request->nurse_service_id;
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

            // Attach subservices if provided
            if ($request->filled('subservices')) {
                $subserviceData = collect($request->subservices)->map(function ($subId) use ($reservation) {
                    return [
                        'nurse_reservation_id' => $reservation->id,
                        'subservice_id' => $subId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->toArray();

                DB::table('nurse_subservices_reservations')->insert($subserviceData);
            }

            DB::commit();
            $nurse = $reservation->nurse()->with('account')->first();
            if ($nurse && $nurse->account && $nurse->account->fcm_token) {
                $body = sprintf(
                    "User %s requested %s service.",
                    auth()->user()->user->full_name ?? "Unknown",
                    $reservation->nurseService->name ?? "a service",
                );

                SendFirebaseNotificationJob::dispatch(
                    $nurse->account->fcm_token,
                    "New Reservation Request",
                    $body
                );
            }
            return response()->json([
                'message' => 'Reservation created successfully.',
                'data' => $reservation->load('nurseService', 'subserviceReservations','nurse.account'),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Reservation creation failed: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create reservation.'], 500);
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
