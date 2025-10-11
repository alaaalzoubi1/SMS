<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Hospital;
use App\Models\Account;
use App\Models\HospitalWorkSchedule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

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
    // Hospital Model

    public function getHospitalsWithServices(Request $request): JsonResponse
    {
        // Validate input filters
        $validated = $request->validate([
            'full_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
        ]);

        // Build the query
        $query = Hospital::with('services.service');

        // Apply filters if provided
        if (isset($validated['full_name'])) {
            $query->where('full_name', 'like', '%' . $validated['full_name'] . '%');
        }

        if (isset($validated['address'])) {
            $query->where('address', 'like', '%' . $validated['address'] . '%');
        }

        // Paginate the results
        $hospitals = $query->select('hospitals.id', 'hospitals.full_name', 'hospitals.address')
            ->paginate(10);

        // Format the response to map it into a clean structure
        $formattedHospitals = $hospitals->map(function ($hospital) {
            return [
                'id' => $hospital->id,
                'full_name' => $hospital->full_name,
                'address' => $hospital->address,
                'services' => $hospital->services->map(function ($service) {
                    return [
                        'id' => $service->id ,
                        'name' => $service->service->service_name ?? null,
                        'price' => $service->price,
                    ];
                }),
            ];
        });

        // Return the paginated response
        return response()->json($formattedHospitals);
    }

    public function getHospitalServices(Request $request, $hospitalId): \Illuminate\Http\JsonResponse
    {
        // Find the hospital by ID
        $hospital = Hospital::find($hospitalId);

        if (!$hospital) {
            return response()->json(['message' => 'Hospital not found'], 404);
        }

        // Retrieve services for the hospital
        $services = $hospital->services_2()->get();  // Get all related services

        // Format the response to include service name, price, and capacity
        $formattedServices = $services->map(function ($service) {
            return [
                'service_id' => $service->id,
                'service_name' => $service->service_name,
                'price' => $service->pivot->price, // Access price from pivot table
                'capacity' => $service->pivot->capacity, // Access capacity from pivot table
            ];
        });

        // Return the services in a formatted response
        return response()->json([
            'hospital_id' => $hospital->id,
            'hospital_name' => $hospital->full_name,
            'services' => $formattedServices,
        ]);
    }


}
