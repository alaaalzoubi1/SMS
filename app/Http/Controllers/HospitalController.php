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
        $account = auth()->user();
        $hospital = $account->hospital;
        $workSchedules = $hospital->workSchedules;

        return response()->json([
            'id' => $hospital->id,
            'email' => $account->email,
            'account_id' => $hospital->account_id,
            'address' => $hospital->address,
            'contact_number' => $account?->phone_number,
            'work_schedules' => $workSchedules ? $workSchedules->map(function($schedule) {
                return [
                    'id' => $schedule->id,
                    'day_of_week' => $schedule->day_of_week,
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
            if ($account && $request->has('phone_number')) {
                $account->phone_number = $request->input('phone_number');
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
            return response()->json(['message' => 'Failed to update hospital profile'], 500);
        }
    }
}
