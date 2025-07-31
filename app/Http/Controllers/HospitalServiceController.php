<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HospitalService;
use App\Models\Hospital;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Jobs\NotifyAdminNewService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class HospitalServiceController extends Controller
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

        $hospitalServices = HospitalService::where('hospital_id', $hospital->id)
                                            ->with('service')
                                            ->get()
                                            ->map(function ($service) {
                                                return [
                                                    'id' => $service->id,
                                                    'hospital_id' => $service->hospital_id,
                                                    'service_id' => $service->service_id,
                                                    'service_name' => $service->service->service_name ,
                                                    'price' => (float) $service->price,
                                                    'capacity' => (int) $service->capacity,
                                                ];
                                            });

        Log::info('Hospital Services fetched:', ['hospital_id' => $hospital->id, 'services' => $hospitalServices->toArray()]);

        return response()->json($hospitalServices);
    }



    public function store(Request $request)
    {
        $hospital = $this->getAuthenticatedHospital();

        $request->validate([
            'service_name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'capacity' => 'required|integer|min:0',
        ]);

        // Check if the service exists in the 'services' table
        $service = Service::where('service_name', $request->service_name)->first();

        DB::beginTransaction();

        try {
            // If the service doesn't exist, create it
            if (!$service) {
                $service = Service::create([
                    'service_name' => $request->service_name,
                ]);
            }

            // Check if this service is already associated with the hospital
            if (HospitalService::where('hospital_id', $hospital->id)
                ->where('service_id', $service->id)
                ->exists()) {
                return response()->json(['message' => 'This service already exists for this hospital.'], 409);
            }

            // Create the hospital-service association
            $hospitalService = HospitalService::create([
                'hospital_id' => $hospital->id,
                'service_id' => $service->id,
                'price' => $request->price,
                'capacity' => $request->capacity,
            ]);

            // Dispatch a job to send an email notification to the admin
            NotifyAdminNewService::dispatch($hospital, $service);

            DB::commit();

            Log::info('Hospital Service added:', ['service' => $hospitalService->toArray()]);

            return response()->json([
                'message' => 'Hospital service added successfully',
                'service' => $hospitalService->load('service')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error adding hospital service: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Failed to add hospital service', 'error' => $e->getMessage()], 500);
        }
    }


    public function show(string $id)
    {
        $hospital = $this->getAuthenticatedHospital();

        $hospitalService = HospitalService::where('hospital_id', $hospital->id)
                                        ->where('id', $id)
                                        ->with('service')
                                        ->first();

        if (!$hospitalService) {
            return response()->json(['message' => 'Hospital service not found'], 404);
        }

        Log::info('Hospital Service details:', ['service_id' => $id, 'details' => $hospitalService->toArray()]);

        return response()->json([
            'id' => $hospitalService->id,
            'hospital_id' => $hospitalService->hospital_id,
            'service_id' => $hospitalService->service_id,
            'service_name' => $hospitalService->service->service_name ,
            'price' => (float) $hospitalService->price,
            'capacity' => (int) $hospitalService->capacity,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function update(Request $request, $id)
    {
        $hospital = $this->getAuthenticatedHospital();

        $hospitalService = HospitalService::where('hospital_id', $hospital->id)
                                        ->where('id', $id)
                                        ->first();

        if (!$hospitalService) {
            return response()->json(['message' => 'Hospital service not found'], 404);
        }

        $validator = Validator::make($request->all(),[
            'price' => 'sometimes|numeric|min:0',
            'capacity' => 'sometimes|integer|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $validated = $validator->validated();
        DB::beginTransaction();
        try {
            $hospitalService->fill($validated);
            if ($hospitalService->isDirty())
                $hospitalService->save();

            DB::commit();

            Log::info('Hospital Service updated:', ['service_id' => $id, 'updated_data' => $hospitalService->toArray()]);

            return response()->json([
                'message' => 'Hospital service updated successfully',
                'service' => $hospitalService->load('service')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update hospital service'], 500);
        }
    }

    public function destroy(string $id)
    {
        $hospital = $this->getAuthenticatedHospital();

        $hospitalService = HospitalService::where('hospital_id', $hospital->id)
                                        ->where('id', $id)
                                        ->first();

        if (!$hospitalService) {
            return response()->json(['message' => 'Hospital service not found'], 404);
        }

        DB::beginTransaction();
        try {
            $hospitalService->delete();
            DB::commit();

            Log::info('Hospital Service deleted:', ['service_id' => $id]);

            return response()->json(['message' => 'Hospital service deleted successfully'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error deleting hospital service: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Failed to delete hospital service', 'error' => $e->getMessage()], 500);
        }
    }
}
