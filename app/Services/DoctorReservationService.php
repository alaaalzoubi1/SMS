<?php
namespace App\Services;

use App\Models\Doctor;
use App\Models\DoctorService;
use App\Models\DoctorWorkSchedule;
use App\Models\DoctorReservation;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class DoctorReservationService
{
    /**
     * Get the next available reservation time slot.
     */
    public function getNextAvailableSlot(int $doctorId, string $date, int $durationMinutes): ?array
    {
        $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));

        $schedule = DoctorWorkSchedule::where('doctor_id', $doctorId)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (!$schedule) {
            return null; // No work schedule for that day
        }

        $startOfDay = Carbon::parse($date . ' ' . $schedule->start_time);
        $endOfDay = Carbon::parse($date . ' ' . $schedule->end_time);

        $existingReservations = DoctorReservation::where('doctor_id', $doctorId)
            ->whereDate('date', $date)
            ->orderBy('start_time')
            ->get();

        $nextStart = $startOfDay;

        /** @var Collection $existingReservations */
        foreach ($existingReservations as $res) {
            $resStart = Carbon::parse($res->start_time);
            $resEnd = Carbon::parse($res->end_time);

            if ($nextStart->copy()->addMinutes($durationMinutes)->lte($resStart)) {
                break; // slot fits before the next reservation
            }

            $nextStart = $resEnd; // try after current reservation
        }

        $nextEnd = $nextStart->copy()->addMinutes($durationMinutes);
        if ($nextEnd->gt($endOfDay)) {
            return null; // No slot fits within working hours
        }

        return [
            'start_time' => $nextStart,
            'end_time' => $nextEnd,
        ];
    }
    public function findSlotForApproval(int $doctorId, string $date, int $durationMinutes): ?array
    {
        $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));

        $schedule = DoctorWorkSchedule::where('doctor_id', $doctorId)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (!$schedule) {
            return null;
        }

        $workStart = Carbon::parse($date . ' ' . $schedule->start_time);
        $workEnd   = Carbon::parse($date . ' ' . $schedule->end_time);

        // فقط الحجوزات الموافق عليها
        $approvedReservations = DoctorReservation::where('doctor_id', $doctorId)
            ->whereDate('date', $date)
            ->where('status', 'approved')
            ->orderBy('start_time')
            ->get();

        $candidateStart = $workStart->copy();

        foreach ($approvedReservations as $reservation) {

            $resStart = Carbon::parse($date . ' ' . $reservation->start_time);
            $resEnd   = Carbon::parse($date . ' ' . $reservation->end_time);

            // هل توجد فجوة تكفي قبل هذا الحجز؟
            if ($candidateStart->copy()->addMinutes($durationMinutes)->lte($resStart)) {
                break;
            }

            // جرّب بعد نهاية هذا الحجز
            $candidateStart = $resEnd->copy();
        }

        $candidateEnd = $candidateStart->copy()->addMinutes($durationMinutes);

        if ($candidateEnd->gt($workEnd)) {
            return null;
        }

        return [
            'start_time' => $candidateStart,
            'end_time'   => $candidateEnd,
        ];
    }

    /**
     * @throws Exception
     */
    public function create($userId, $doctorId, $doctorServiceId, $date, $isAdmin = false)
    {
        try {
            $service = DoctorService::with(['doctor.account'])
                ->where('id', $doctorServiceId)
                ->where('doctor_id', $doctorId)
                ->whereHas('doctor.account', function ($q) {
                    $q->active();
                })
                ->firstOrFail();

            return DoctorReservation::create([
                'doctor_service_id' => $service->id,
                'doctor_id' => $service->doctor_id,
                'user_id' => $userId,
                'date' => $date,
                'start_time' => null,
                'end_time' => null,
                'status' => 'pending',
                'reserved_by_admin' => $isAdmin
            ]);
        }catch (ModelNotFoundException $e){
            throw new ModelNotFoundException('Doctor or service not available.');
        }
    }
}
