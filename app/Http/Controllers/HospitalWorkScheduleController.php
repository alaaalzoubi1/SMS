<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Hospital;
use App\Models\HospitalWorkSchedule;
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
}
