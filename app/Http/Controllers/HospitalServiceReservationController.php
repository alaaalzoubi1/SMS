<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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

    public function index(Request $request)
    {
        $hospital = $this->getAuthenticatedHospital();

        $reservations = HospitalServiceReservation::where('hospital_id', $hospital->id)
              ->with(['user', 'hospitalService.service'])
              ->orderBy('start_date', 'desc')
              ->paginate()
              ->map(function($reservation) {
                  return [
                      'id' => $reservation->id,
                      'user_name' => $reservation->user->name ?? $reservation->user->email ?? 'N/A',
                      'service_name' => $reservation->hospitalService->service->service_name ?? 'N/A',
                      'price' => (float) $reservation->hospitalService->price,
                      'status' => $reservation->status,
                      'start_date' => $reservation->start_date->format('Y-m-d'),
                      'end_date' => $reservation->end_date->format('Y-m-d'),
                  ];
              });
        Log::info('Hospital Service Reservations fetched:', ['hospital_id' => $hospital->id, 'reservations_count' => $reservations->count()]);

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

        Log::info('Hospital Service Reservation details:', ['reservation_id' => $id, 'details' => $reservation->toArray()]);

        return response()->json([
            'id' => $reservation->id,
            'user_name' => $reservation->user->name ?? $reservation->user->email ?? 'N/A',
            'service_name' => $reservation->hospitalService->service->service_name ?? 'N/A',
            'price' => (float) $reservation->hospitalService->price,
            'status' => $reservation->status,
            'start_date' => $reservation->start_date->format('Y-m-d'),
            'end_date' => $reservation->end_date->format('Y-m-d'),
        ]);
    }

    public function updateStatus(Request $request, string $id)
    {
        $hospital = $this->getAuthenticatedHospital();

        $reservation = HospitalServiceReservation::where('hospital_id', $hospital->id)
              ->where('id', $id)
              ->first();

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found or does not belong to this hospital'], 404);
        }

        $request->validate([
            'status' => 'required|string|in:pending,confirmed,cancelled', // تحديث حالات الـ ENUM
        ]);

        DB::beginTransaction();
        try {
            $reservation->status = $request->status;
            $reservation->save();
            DB::commit();

            Log::info('Reservation status updated:', ['reservation_id' => $id, 'new_status' => $request->status]);

            return response()->json(['message' => 'Reservation status updated successfully', 'reservation' => $reservation]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error updating reservation status: " . $e->getMessage());
            return response()->json(['message' => 'Failed to update reservation status', 'error' => $e->getMessage()], 500);
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
            ->map(function($reservation) {
                return [
                    'id' => $reservation->id,
                    'user_name' => $reservation->user->name ?? $reservation->user->email ?? 'N/A',
                    'service_name' => $reservation->hospitalService->service->service_name ?? 'N/A',
                    'price' => (float) $reservation->hospitalService->price,
                    'status' => $reservation->status,
                    'start_date' => $reservation->start_date->format('Y-m-d'),
                    'end_date' => $reservation->end_date->format('Y-m-d'),
                ];
            });

        Log::info('Hospital Trashed Reservations fetched:', ['hospital_id' => $hospital->id, 'reservations_count' => $reservations->count()]);

        return response()->json($reservations);
    }

    public function makeReservation(Request $request)
    {
        // Validate the incoming data
        $validated = $request->validate([
            'hospital_service_id' => 'required|exists:hospital_services,id',
            'hospital_id' => 'required|exists:hospitals,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        // Fetch the hospital service and its capacity
        $hospitalService = HospitalService::find($validated['hospital_service_id']);

        // Check if the hospital service exists
        if (!$hospitalService) {
            return response()->json(['message' => 'Hospital service not found'], 404);
        }

        // Start database transaction
        DB::beginTransaction();

        try {
            // Fetch the number of overlapping reservations for this service and the given dates
            $existingReservationsCount = HospitalServiceReservation::where('hospital_service_id', $validated['hospital_service_id'])
                ->where('hospital_id', $validated['hospital_id'])
                ->where(function (Builder $query) use ($validated) {
                    $query->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                        ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']])
                        ->orWhere(function ($query) use ($validated) {
                            $query->where('start_date', '<=', $validated['start_date'])
                                ->where('end_date', '>=', $validated['end_date']);
                        });
                })
                ->count(); // Count reservations in a single query

            // If the existing reservations exceed the capacity, return an error message
            if ($existingReservationsCount >= $hospitalService->capacity) {
                DB::rollBack();
                return response()->json(['message' => 'The service is fully booked for the selected dates.'], 400);
            }

            // Proceed with making the reservation
            $reservation = HospitalServiceReservation::create([
                'user_id' => auth()->user()->user->id,
                'hospital_service_id' => $validated['hospital_service_id'],
                'hospital_id' => $validated['hospital_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'status' => 'pending',
            ]);

            // Commit the transaction
            DB::commit();

            return response()->json([
                'message' => 'Reservation successful.',
                'reservation' => $reservation,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'An error occurred while making the reservation.'], 500);
        }
    }
}
