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

            // Otherwise try right after it.
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
     * True when the doctor actually works on the given date. Used to reject
     * reservation requests up front instead of failing later at approval.
     */
    public function worksOn(int $doctorId, string $date): bool
    {
        return DoctorWorkSchedule::findForDate($doctorId, $date) !== null;
    }

    /**
     * Build a slot when the caller explicitly overrides the schedule
     * (force_confirm). The result is still anchored to the doctor's window:
     * it continues from the last approved booking, or from opening time when
     * there is none, and it is refused outright if the resulting slot would
     * fall outside working hours.
     *
     * @throws Exception when no schedule exists for that date
     */
    public function forceSlotForApproval(int $doctorId, string $date, int $durationMinutes): array
    {
        $schedule = DoctorWorkSchedule::findForDate($doctorId, $date);

        if (!$schedule) {
            throw new Exception('الطبيب لا يملك دوام عمل في هذا اليوم.');
        }

        $window = $schedule->windowFor($date);
        [$workStart, $workEnd] = $window;

        $lastApproved = DoctorReservation::where('doctor_id', $doctorId)
            ->whereDate('date', $date)
            ->where('status', 'approved')
            ->whereNotNull('end_time')
            ->orderByDesc('end_time')
            ->first();

        $start = $lastApproved
            ? $this->timeOnDate($date, $lastApproved->end_time)
            : $workStart->copy();

        if ($start === null) {
            $start = $workStart->copy();
        }

        $end = $start->copy()->addMinutes($durationMinutes);

        if ($end->gt($workEnd)) {
            throw new Exception('لا يمكن تثبيت الحجز خارج أوقات دوام الطبيب في هذا اليوم.');
        }

        return [
            'start_time' => $start,
            'end_time'   => $end,
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
