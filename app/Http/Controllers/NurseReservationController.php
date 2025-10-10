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
use TarfinLabs\LaravelSpatial\Types\Point;

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
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $nurse = Nurse::where('account_id', auth()->id())->firstOrFail();

        $query = NurseReservation::where('nurse_id', $nurse->id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->from, fn($q) => $q->whereDate('start_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('start_at', '<=', $request->to))
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
            'status' => 'required|in:pending,approved,rejected,completed',
        ]);


        $reservation = NurseReservation::where('id', $id)
            ->first();
        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found or unauthorized.'], 404);
        }
        $this->authorize('manageNurseReservations',$reservation);
        $reservation->status = $request->status;
        $reservation->save();

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
            $reservation = new NurseReservation();
            $reservation->user_id = auth()->user()->user->id;
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
                $reservation->location = new Point(lat: $request->lat,lng: $request->lng,srid: 4326);
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
                    auth()->user()->name ?? "Unknown",
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

            return response()->json([
                'message' => 'Failed to create reservation.',
                'error' => $e->getMessage(),
            ], 500);
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
