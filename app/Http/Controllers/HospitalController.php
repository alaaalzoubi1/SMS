<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Hospital;
use App\Models\Account;
use App\Models\HospitalWorkSchedule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class HospitalController extends Controller
{
    private function getAuthenticatedHospital()
    {
        $hospital = Hospital::where('account_id', Auth::id())->first();
        if (!$hospital) {
            abort(404, 'Hospital not found for the authenticated user.');
        }
        return $hospital;
    }

    public function getProfile()
    {
        $hospital = $this->getAuthenticatedHospital();

        $account = $hospital->account;
        $workSchedules = $hospital->workSchedules;

        Log::info('Hospital Profile Data:', [
            'hospital_id' => $hospital->id,
            'account_data_exists' => (bool) $account,
            'phone_number' => $account ? $account->phone_number : 'N/A',
            'work_schedules_count' => $workSchedules ? $workSchedules->count() : 0,
            'raw_work_schedules' => $workSchedules ? $workSchedules->toArray() : [],
        ]);

        return response()->json([
            'id' => $hospital->id,
            'account_id' => $hospital->account_id,
            'address' => $hospital->address,
            'contact_number' => $account ? $account->phone_number : null,
            'work_schedules' => $workSchedules ? $workSchedules->map(function($schedule) {
                return [
                    'id' => $schedule->id,
                    'day_of_week' => $schedule->day_of_week,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                ];
            }) : [],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $hospital = $this->getAuthenticatedHospital();

        $request->validate([
            'address' => 'sometimes|string|max:255',
            'contact_number' => 'sometimes|string|max:20',
        ]);

        DB::beginTransaction();

        try {
            if ($request->has('address')) {
                $hospital->address = $request->input('address');
                $hospital->save();
            }

            $account = $hospital->account;
            if ($account && $request->has('contact_number')) {
                $account->phone_number = $request->input('contact_number');
                $account->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Hospital profile updated successfully',
                'hospital' => [
                    'id' => $hospital->id,
                    'account_id' => $hospital->account_id,
                    'address' => $hospital->address,
                    'contact_number' => $account ? $account->phone_number : null,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error updating hospital profile: " . $e->getMessage());
            return response()->json(['message' => 'Failed to update hospital profile', 'error' => $e->getMessage()], 500);
        }
    }

    public function changePassword(Request $request)
    {
        $hospital = $this->getAuthenticatedHospital();
        $account = Account::find($hospital->account_id);

        if (!$account) {
            return response()->json(['message' => 'Associated account not found'], 404);
        }

        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $account->password)) {
            return response()->json(['message' => 'Current password does not match'], 400);
        }

        $account->password = Hash::make($request->new_password);
        $account->save();

        return response()->json(['message' => 'Password updated successfully']);
    }

    public function updateWorkSchedules(Request $request)
    {
        $hospital = $this->getAuthenticatedHospital();

        $request->validate([
            'schedules' => 'required|array',
            'schedules.*.day_of_week' => 'required|string|in:Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
            'schedules.*.start_time' => 'required|date_format:H:i:s',
            'schedules.*.end_time' => 'required|date_format:H:i:s|after:schedules.*.start_time',
            'schedules.*.id' => 'sometimes|nullable|exists:hospital_work_schedules,id',
        ]);

        DB::beginTransaction();

        try {
            $existingScheduleIds = $hospital->workSchedules->pluck('id')->toArray();
            $updatedOrCreatedIds = [];

            foreach ($request->schedules as $scheduleData) {
                if (isset($scheduleData['id']) && !is_null($scheduleData['id'])) {
                    $schedule = HospitalWorkSchedule::where('hospital_id', $hospital->id)
                                                    ->where('id', $scheduleData['id'])
                                                    ->firstOrFail();
                    $schedule->update($scheduleData);
                    $updatedOrCreatedIds[] = $schedule->id;
                } else {
                    $schedule = $hospital->workSchedules()->create($scheduleData);
                    $updatedOrCreatedIds[] = $schedule->id;
                }
            }

            HospitalWorkSchedule::where('hospital_id', $hospital->id)
                                ->whereNotIn('id', $updatedOrCreatedIds)
                                ->delete();

            DB::commit();

            return response()->json([
                'message' => 'Hospital work schedules updated successfully',
                'work_schedules' => $hospital->fresh()->workSchedules
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error updating hospital work schedules: " . $e->getMessage());
            return response()->json(['message' => 'Failed to update work schedules', 'error' => $e->getMessage()], 500);
        }
    }
}