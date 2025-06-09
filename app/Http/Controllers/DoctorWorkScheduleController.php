<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\DoctorWorkSchedule;
use App\Http\Requests\StoreDoctorWorkScheduleRequest;
use App\Http\Requests\UpdateDoctorWorkScheduleRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

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
        $schedule->update($request->validated());

        return response()->json([
            'message' => 'Work schedule updated successfully.',
            'data' => $schedule
        ]);
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

    public function restore($id): JsonResponse
    {
        $schedule = DoctorWorkSchedule::withTrashed()->findOrFail($id);
        $this->authorize('all', $schedule);
        if ($schedule->trashed()) {
            $schedule->restore();

            return response()->json([
                'message' => 'Schedule restored successfully.',
                'data' => $schedule
            ]);
        }

        return response()->json([
            'message' => 'Schedule is not deleted.',
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
}
