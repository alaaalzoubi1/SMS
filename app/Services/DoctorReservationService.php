<?php
namespace App\Services;

use App\Models\DoctorService;
use App\Models\DoctorWorkSchedule;
use App\Models\DoctorReservation;
use Carbon\Carbon;
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
}
