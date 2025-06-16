<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
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
}