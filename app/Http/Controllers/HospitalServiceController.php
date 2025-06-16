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
            'service_id' => 'required|exists:services,id',
            'price' => 'required|numeric|min:0',
            'capacity' => 'nullable|integer|min:0',
        ]);

        if (HospitalService::where('hospital_id', $hospital->id)
                            ->where('service_id', $request->service_id)
                            ->exists()) {
            return response()->json(['message' => 'This service already exists for this hospital.'], 409);
        }

        DB::beginTransaction();

        try {
            $hospitalService = $hospital->hospitalServices()->create([
                'service_id' => $request->service_id,
                'price' => $request->price,
                'capacity' => $request->capacity,
            ]);

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

    public function update(Request $request, string $id)
    {
        $hospital = $this->getAuthenticatedHospital();

        $hospitalService = HospitalService::where('hospital_id', $hospital->id)
                                        ->where('id', $id)
                                        ->first();

        if (!$hospitalService) {
            return response()->json(['message' => 'Hospital service not found'], 404);
        }

        $request->validate([
            'price' => 'sometimes|required|numeric|min:0',
            'capacity' => 'nullable|integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            $hospitalService->update($request->only(['price', 'capacity']));

            DB::commit();

            Log::info('Hospital Service updated:', ['service_id' => $id, 'updated_data' => $hospitalService->toArray()]);

            return response()->json([
                'message' => 'Hospital service updated successfully',
                'service' => $hospitalService->load('service')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error updating hospital service: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Failed to update hospital service', 'error' => $e->getMessage()], 500);
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