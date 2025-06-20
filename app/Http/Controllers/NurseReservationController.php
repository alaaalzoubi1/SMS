<?php

namespace App\Http\Controllers;

use App\Models\Nurse;
use App\Models\NurseReservation;
use App\Http\Requests\StoreNurseReservationRequest;
use App\Http\Requests\UpdateNurseReservationRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            ->with(['user.account','nurseService', 'subservices']);

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
    public function store(StoreNurseReservationRequest $request)
    {
        //
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
