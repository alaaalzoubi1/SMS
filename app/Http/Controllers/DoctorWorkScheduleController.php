<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\DoctorWorkSchedule;
use App\Http\Requests\StoreDoctorWorkScheduleRequest;
use App\Http\Requests\UpdateDoctorWorkScheduleRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
class DoctorWorkScheduleController extends Controller
{
    use AuthorizesRequests;
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDoctorWorkScheduleRequest $request): JsonResponse
    {
        $doctor = Doctor::where('account_id', auth()->id())->first();

        if (!$doctor) {
            return response()->json(['message' => 'Doctor not found.'], 404);
        }
        $validateData = $request->validated();
        $schedule = DoctorWorkSchedule::create([
            'doctor_id' => $doctor->id,
            'day_of_week' => $validateData['day_of_week'],
            'start_time' => $validateData['start_time'],
            'end_time' => $validateData['end_time']
        ]);

        return response()->json([
            'message' => 'Work schedule created successfully.',
            'data'    => $schedule
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     * @throws AuthorizationException
     */
    public function update(UpdateDoctorWorkScheduleRequest $request, $id): JsonResponse
    {
        $schedule = DoctorWorkSchedule::findOrFail($id);
        $this->authorize('all', $schedule);
//        $schedule->update($request->validated());
        $schedule->fill($request->validated());
        if ($schedule->isDirty())
            return response()->json([
                'message' => 'Work schedule updated successfully.',
                'data' => $schedule
            ]);
        return response()->json(['message' => 'No changes detected.']);

    }

    /**
     * Remove the specified resource from storage.
     * @throws AuthorizationException
     */
    public function destroy($id): JsonResponse
    {
        $schedule = DoctorWorkSchedule::findOrFail($id);
        $this->authorize('all', $schedule);
        $schedule->delete();

        return response()->json([
            'message' => 'Work schedule deleted successfully.'
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    public function restore($id): JsonResponse
    {
        $schedule = DoctorWorkSchedule::withTrashed()->findOrFail($id);

        // Get the authenticated doctor
        $doctor = Doctor::where('account_id', auth()->id())->firstOrFail();

        // Authorization check (assuming policy is implemented)
        $this->authorize('all', $schedule);

        // Check for duplicates: same doctor_id and day_of_week, not soft deleted
        $dayAlreadyTaken = DoctorWorkSchedule::where('doctor_id', $doctor->id)
            ->where('day_of_week', $schedule->day_of_week)
            ->whereNull('deleted_at')
            ->exists();

        if ($dayAlreadyTaken) {
            return response()->json([
                'message' => 'This day already exists for your schedule.'
            ], 409);
        }

        // Restore if it's soft deleted
        if ($schedule->trashed()) {
            $schedule->restore();

            return response()->json([
                'message' => 'Schedule restored successfully.',
                'data' => $schedule
            ]);
        }

        return response()->json([
            'message' => 'Schedule is not deleted.'
        ], 400);
    }


    public function trashed(): JsonResponse
    {
        $doctor = Doctor::where('account_id', auth()->id())->first();

        if (!$doctor) {
            return response()->json(['message' => 'Doctor not found.'], 404);
        }
        $trashedSchedules = DoctorWorkSchedule::onlyTrashed()->where('doctor_id',$doctor->id)->get();
        return response()->json([
            'message' => 'Soft deleted schedules retrieved successfully.',
            'data' => $trashedSchedules
        ]);
    }

    public function mySchedules(): JsonResponse
    {
        $doctor = Doctor::where('account_id', auth()->id())->first();

        if (!$doctor) {
            return response()->json(['message' => 'Doctor not found.'], 404);
        }

        $schedules = DoctorWorkSchedule::where('doctor_id', $doctor->id)
            ->get();

        return response()->json([
            'message' => 'Doctor schedules retrieved successfully.',
            'data' => $schedules
        ]);
    }


    public function getAvailableDates(Request $request, $doctorId): JsonResponse
    {
        $doctor = Doctor::find($doctorId);
        if (!$doctor) {
            return response()->json(['message' => 'Doctor not found'], 404);
        }

        $month = (int) $request->query('month', now()->month);
        $year  = (int) $request->query('year', now()->year);

        $now = now()->startOfDay();

        // بداية الشهر المطلوب
        if (!checkdate($month, 1, $year)) {
            return response()->json([
                'message' => 'Invalid month or year.'
            ], 422);
        }

        $requestedMonthStart = Carbon::createFromDate($year, $month, 1)->startOfDay();        $requestedMonthEnd   = $requestedMonthStart->copy()->endOfMonth();

        if ($requestedMonthEnd->lt($now)) {
            return response()->json([
                'message' => 'Cannot fetch dates for past months.'
            ], 422);
        }

        $startDate = $requestedMonthStart->isSameMonth($now)
            ? $now->copy()
            : $requestedMonthStart->copy();

        $workDays = $doctor->doctorWorkSchedule()
            ->pluck('day_of_week')
            ->toArray();

        $dayNameToNum = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];

        $workDayNums = array_map(fn ($d) => $dayNameToNum[$d] ?? null, $workDays);
        $workDayNums = array_filter($workDayNums, fn ($d) => $d !== null);

        $dates = [];

        for ($date = $startDate->copy(); $date->lte($requestedMonthEnd); $date->addDay()) {
            if (in_array($date->dayOfWeek, $workDayNums)) {
                $dates[] = $date->toDateString();
            }
        }

        return response()->json([
            'doctor_id' => $doctor->id,
            'month' => $month,
            'year' => $year,
            'available_dates' => $dates,
        ]);
    }
}
