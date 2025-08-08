<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Hospital;
use App\Models\HospitalWorkSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HospitalWorkScheduleController extends Controller
{
    private function getAuthenticatedHospital()
    {
        $hospital = Hospital::where('account_id', Auth::id())->first();
        if (!$hospital) {
            abort(404, 'Hospital not found for the authenticated user.');
        }
        return $hospital;
    }

    public function index()
    {
        $hospital = $this->getAuthenticatedHospital();

        $schedules = HospitalWorkSchedule::where('hospital_id', $hospital->id)->get()->map(function($schedule) {
            return [
                'id' => $schedule->id,
                'day_of_week' => $schedule->day_of_week,
            ];
        });

        Log::info('Hospital Work Schedules fetched:', ['hospital_id' => $hospital->id, 'schedules' => $schedules->toArray()]);

        return response()->json($schedules);
    }

    public function store(Request $request)
    {
        $hospital = $this->getAuthenticatedHospital();

        $request->validate([
            'day_of_week' => 'required|string|in:Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday|unique:hospital_work_schedules,day_of_week,NULL,id,hospital_id,' . $hospital->id,
        ]);

        DB::beginTransaction();
        try {
            $schedule = HospitalWorkSchedule::create([
                'day_of_week' => $request->day_of_week,
                'hospital_id' => $hospital->id
            ]);
            DB::commit();

            Log::info('Hospital Work Schedule added:', ['schedule' => $schedule->toArray()]);

            return response()->json($schedule, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error adding hospital work schedule: " . $e->getMessage());
            return response()->json(['message' => 'Failed to add work schedule', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(string $id)
    {
        $hospital = $this->getAuthenticatedHospital();

        $schedule = HospitalWorkSchedule::where('hospital_id', $hospital->id)->find($id);

        if (!$schedule) {
            return response()->json(['message' => 'Work schedule not found'], 404);
        }

        Log::info('Hospital Work Schedule details:', ['schedule_id' => $id, 'details' => $schedule->toArray()]);

        return response()->json([
            'id' => $schedule->id,
            'day_of_week' => $schedule->day_of_week,
        ]);
    }



    public function destroy(string $id)
    {
        $hospital = $this->getAuthenticatedHospital();

        $schedule = HospitalWorkSchedule::where('hospital_id', $hospital->id)->find($id);

        if (!$schedule) {
            return response()->json(['message' => 'Work schedule not found'], 404);
        }

        DB::beginTransaction();
        try {
            $schedule->delete();
            DB::commit();

            return response()->json(['message' => 'Work schedule deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error deleting hospital work schedule: " . $e->getMessage());
            return response()->json(['message' => 'Failed to delete work schedule', 'error' => $e->getMessage()], 500);
        }
    }
    public function getAvailableDates(Request $request, $hospitalId): JsonResponse
{
    // Find the hospital by ID
    $hospital = Hospital::find($hospitalId);
    if (!$hospital) {
        return response()->json(['message' => 'Hospital not found'], 404);
    }

    // Get month and year from the request or use current month and year as default
    $month = $request->query('month', now()->month); // Default to current month
    $year = $request->query('year', now()->year);   // Default to current year

    // Get working days from the hospital's work schedule
    $workDays = $hospital->workSchedule()->pluck('day_of_week')->toArray(); // ['monday', 'wednesday', ...]

    // Map day names to numbers (Carbon: 0=Sunday, 1=Monday, ...)
    $dayNameToNum = [
        'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
        'thursday' => 4, 'friday' => 5, 'saturday' => 6
    ];
    $workDayNums = array_map(fn($d) => $dayNameToNum[$d], $workDays);

    // Generate all dates in the given month that match work days
    $startOfMonth = Carbon::create($year, $month, 1); // Start from the 1st of the given month
    $endOfMonth = $startOfMonth->copy()->endOfMonth(); // End of the given month
    $dates = [];

    // Loop through the days of the month and check if they match the hospital's work schedule
    for ($date = $startOfMonth->copy(); $date->lte($endOfMonth); $date->addDay()) {
        if (in_array($date->dayOfWeek, $workDayNums)) {
            $dates[] = $date->toDateString(); // Add date in YYYY-MM-DD format
        }
    }

    // Return the available dates in the response
    return response()->json([
        'hospital_id' => $hospital->id,
        'hospital_name' => $hospital->full_name,
        'month' => $month,
        'year' => $year,
        'available_dates' => $dates,
    ]);
}
}
