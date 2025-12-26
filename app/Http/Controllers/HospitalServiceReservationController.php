<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\SendFirebaseNotificationJob;
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
                      'user_name' => $reservation->user->full_name  ?? 'N/A',
                      'service_name' => $reservation->hospitalService->service->service_name ?? 'N/A',
                      'price' => (float) $reservation->hospitalService->price,
                      'status' => $reservation->status,
                      'start_date' => Carbon::parse($reservation->start_date)->format('Y-m-d'),
                      'end_date' => Carbon::parse($reservation->end_date)->format('Y-m-d'),
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
            'start_date' => Carbon::parse($reservation->start_date)->format('Y-m-d'),
            'end_date' => Carbon::parse($reservation->end_date)->format('Y-m-d'),
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
            'reason' => 'required_if:status,cancelled|string'
        ]);

        $newStatus = $request->status;
        $oldStatus = $reservation->status;

        DB::beginTransaction();
        try {
            /* ───────────────────────────────
               RULE 1 — CANCELLED
               ─────────────────────────────── */
            if ($newStatus === 'cancelled') {

                if (!in_array($oldStatus, ['pending', 'accepted'])) {
                    return response()->json(['message' => 'Cannot cancel unless status is pending or accepted'], 422);
                }
                if (!$request->reason) {
                    return response()->json(['message' => 'Cancellation reason is required'], 422);
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
                    return response()->json(['message' => 'Reservation must be pending to be accepted'], 422);
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
                    return response()->json(['message' => 'Reservation must be accepted before confirming'], 422);
                }

                $reservation->start_date = now();

                $service = $reservation->hospitalService;
                if ($service->capacity <= 0) {
                    return response()->json(['message' => 'No capacity remaining for this service'], 422);
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
                    return response()->json(['message' => 'Reservation must be confirmed before finishing'], 422);
                }

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
                'message' => 'Reservation status updated successfully',
                'reservation' => $reservation
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
            ->map(function($reservation) {
                return [
                    'id' => $reservation->id,
                    'user_name' => $reservation->user->name ?? $reservation->user->email ?? 'N/A',
                    'service_name' => $reservation->hospitalService->service->service_name ?? 'N/A',
                    'price' => (float) $reservation->hospitalService->price,
                    'status' => $reservation->status,
                    'start_date' => Carbon::parse($reservation->start_date)->format('Y-m-d'),
                    'end_date' => Carbon::parse($reservation->end_date)->format('Y-m-d'),
                ];
            });

        Log::info('Hospital Trashed Reservations fetched:', ['hospital_id' => $hospital->id, 'reservations_count' => $reservations->count()]);

        return response()->json($reservations);
    }


    public function makeReservation(Request $request)
    {
        $validated = $request->validate([
            'hospital_service_id' => 'required|exists:hospital_services,id',
        ]);

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

        HospitalServiceReservation::create($validated);


        return response()->json([
            'message' => "تم إنشاء الحجز بنجاح،بعد موافقة المشفى عليه يجب تثبيت الحجز خلال مدة أقصاها {$hospitalService->hospital->reservation_confirmation_deadline} ساعة"
        ]);
    }

}
