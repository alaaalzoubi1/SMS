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
        return $this->findSlotForApproval($doctorId, $date, $durationMinutes);
    }

    /**
     * Find the first free slot of $durationMinutes inside the doctor's
     * working window for $date.
     *
     * Returns null when the doctor has no schedule that day, or when no slot
     * of that length fits before closing time. Callers must not fall back to
     * a default hour in that case — a booking outside the published schedule
     * is exactly the bug this guards against.
     */
    public function findSlotForApproval(int $doctorId, string $date, int $durationMinutes): ?array
    {
        $schedule = DoctorWorkSchedule::findForDate($doctorId, $date);

        if (!$schedule) {
            return null;
        }

        $window = $schedule->windowFor($date);

        if ($window === null) {
            return null;
        }

        [$workStart, $workEnd] = $window;

        // Only approved bookings hold a time; pending ones have null times
        // and must not influence the search.
        $approvedReservations = DoctorReservation::where('doctor_id', $doctorId)
            ->whereDate('date', $date)
            ->where('status', 'approved')
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->orderBy('start_time')
            ->get();

        $candidateStart = $workStart->copy();

        // For today, a slot that already passed is not a real slot. Without
        // this a request accepted at 19:00 could be pinned at 14:00.
        if (Carbon::parse($date)->isToday() && Carbon::now()->gt($candidateStart)) {
            $candidateStart = Carbon::now()->second(0);
        }

        /** @var Collection $approvedReservations */
        foreach ($approvedReservations as $reservation) {
            $resStart = $this->timeOnDate($date, $reservation->start_time);
            $resEnd   = $this->timeOnDate($date, $reservation->end_time);

            if ($resStart === null || $resEnd === null) {
                continue;
            }

            // Does the candidate fit in the gap before this booking?
            if ($candidateStart->copy()->addMinutes($durationMinutes)->lte($resStart)) {
                break;
            }

            // Otherwise try right after it (never moving backwards in time).
            if ($resEnd->gt($candidateStart)) {
                $candidateStart = $resEnd->copy();
            }
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
     * True when the doctor actually works on the given date. Used to reject
     * reservation requests up front instead of failing later at approval.
     */
    public function worksOn(int $doctorId, string $date): bool
    {
        return DoctorWorkSchedule::findForDate($doctorId, $date) !== null;
    }

    /**
     * Build a slot when the doctor explicitly confirms the warning
     * (force_confirm = true).
     *
     * Client requirement: when the doctor accepts a request after official
     * working hours, the warning is shown — and if the doctor confirms it,
     * the request MUST be accepted. Previously this method threw when the
     * slot ran past closing time, so the doctor saw the warning, confirmed,
     * and the reservation was still not accepted.
     *
     * The slot is still placed sensibly: right after the last approved
     * booking of that day, or at opening time when there is none. Only when
     * the day has no schedule at all (e.g. a manual reservation on a day off)
     * is it placed after the last booking, or at the current time for today,
     * or at 09:00. `outside_working_hours` tells the caller whether the slot
     * ended up (partly) outside the published working window.
     *
     * @return array{start_time: Carbon, end_time: Carbon, outside_working_hours: bool}
     */
    public function forceSlotForApproval(int $doctorId, string $date, int $durationMinutes): array
    {
        $schedule = DoctorWorkSchedule::findForDate($doctorId, $date);
        $window = $schedule?->windowFor($date);

        $lastApproved = DoctorReservation::where('doctor_id', $doctorId)
            ->whereDate('date', $date)
            ->where('status', 'approved')
            ->whereNotNull('end_time')
            ->orderByDesc('end_time')
            ->first();

        $start = $lastApproved ? $this->timeOnDate($date, $lastApproved->end_time) : null;

        if ($start === null) {
            if ($window !== null) {
                $start = $window[0]->copy();
            } elseif (Carbon::parse($date)->isToday()) {
                $start = Carbon::now()->second(0);
            } else {
                $start = Carbon::parse($date)->startOfDay()->setTime(9, 0);
            }
        }

        // The doctor confirmed late in the day: never place the booking
        // before the current time on today's date.
        if (Carbon::parse($date)->isToday() && $start->lt(Carbon::now()) && $window !== null && Carbon::now()->gt($window[1])) {
            $start = Carbon::now()->second(0);
        }

        $end = $start->copy()->addMinutes($durationMinutes);

        // `time` columns cannot hold a value past midnight of the booking day.
        $dayEnd = Carbon::parse($date)->endOfDay()->second(0);
        if ($end->gt($dayEnd)) {
            $end = $dayEnd->copy();
            if ($start->gte($end)) {
                $start = $end->copy()->subMinutes(max(1, $durationMinutes));
            }
        }

        $outside = $window === null
            || $start->lt($window[0])
            || $end->gt($window[1]);

        return [
            'start_time' => $start,
            'end_time'   => $end,
            'outside_working_hours' => $outside,
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

    /**
     * Combine a date with a `time` column value. The column arrives as either
     * "H:i" or "H:i:s" depending on the driver, and Carbon::parse() on a bare
     * time string resolves against *today*, which silently shifts a booking
     * onto the wrong day.
     */
    private function timeOnDate(string $date, string|\DateTimeInterface|null $time): ?Carbon
    {
        if ($time === null || $time === '') {
            return null;
        }

        if ($time instanceof \DateTimeInterface) {
            return Carbon::parse($date)->startOfDay()->setTimeFromTimeString(
                Carbon::parse($time)->format('H:i:s')
            );
        }

        $parts = explode(':', $time);
        if (count($parts) < 2) {
            return null;
        }

        return Carbon::parse($date)->startOfDay()->setTime((int) $parts[0], (int) $parts[1], (int) ($parts[2] ?? 0));
    }
}
