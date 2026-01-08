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
use MatanYadaev\EloquentSpatial\Objects\Point;

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



    public function getHospitalsWithServices(Request $request): JsonResponse
    {
        // Validate input filters
        $validated = $request->validate([
            'full_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
        ]);

        // Build the query
        $query = Hospital::with(['services.service','province']);

        // Apply filters if provided
        if (isset($validated['full_name'])) {
            $query->where('full_name', 'like', '%' . $validated['full_name'] . '%');
        }

        if (isset($validated['address'])) {
            $query->where('address', 'like', '%' . $validated['address'] . '%');
        }

        // Paginate the results
        $hospitals = $query->select('hospitals.id', 'hospitals.full_name', 'hospitals.address','hospitals.location','hospitals.avg_rating','hospitals.profile_image_path')
            ->paginate(10);

        // Format the response to map it into a clean structure
        $formattedHospitals = $hospitals->map(function ($hospital) {
            return [
                'id' => $hospital->id,
                'full_name' => $hospital->full_name,
                'address' => $hospital->address,
                'avg_rating' => max(4, $hospital->avg_rating),
                'location' => $hospital->location,
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
    public function getNearestHospitals(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ], [
            'latitude.required' => 'يجب إدخال خط العرض.',
            'latitude.numeric' => 'خط العرض يجب أن يكون رقمًا.',
            'latitude.between' => 'قيمة خط العرض يجب أن تكون بين -90 و 90.',
            'longitude.required' => 'يجب إدخال خط الطول.',
            'longitude.numeric' => 'خط الطول يجب أن يكون رقمًا.',
            'longitude.between' => 'قيمة خط الطول يجب أن تكون بين -180 و 180.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');

        $point = new Point($latitude, $longitude);

        $hospitals = Hospital::query()
            ->whereNotNull('location')
            ->withDistanceSphere('location', $point, 'distance_meters')
            ->orderBy('distance_meters')
            ->limit(10)
            ->get()
            ->transform(function ($hospital){
                $hospital->avg_rating = max(4,$hospital->avg_rating);
                return $hospital;
            })
            ->makeHidden(['license_image_path', 'deleted_at', 'created_at', 'updated_at']);
        return response()->json([
            'hospitals' => $hospitals,
        ]);
    }


}
