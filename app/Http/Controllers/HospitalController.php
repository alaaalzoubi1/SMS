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
        // Validate filters
        $validated = $request->validate([
            'full_name' => 'nullable|string|max:255',
            'address'   => 'nullable|string|max:255',
            // New: filter by hospital type (one id or several: hospital_type_ids[]).
            'hospital_type_id'    => 'nullable|integer|exists:hospital_types,id',
            'hospital_type_ids'   => 'nullable|array',
            'hospital_type_ids.*' => 'integer|exists:hospital_types,id',
            'province_id' => 'nullable|integer|exists:provinces,id',
        ]);

        $typeIds = array_filter(array_merge(
            (array) ($validated['hospital_type_ids'] ?? []),
            isset($validated['hospital_type_id']) ? [$validated['hospital_type_id']] : []
        ));

        $query = Hospital::query()
            ->with([
                'services.service',
                'province:id,name_ar,name_en',
                'hospitalType:id,name_ar,name_en,icon',
            ])
            ->when($typeIds !== [], fn ($q) => $q->whereIn('hospitals.hospital_type_id', $typeIds))
            ->when(!empty($validated['province_id']), fn ($q) => $q->where('hospitals.province_id', $validated['province_id']));
        if (!empty($validated['full_name'])) {
            $query->where('full_name', 'like', '%' . $validated['full_name'] . '%');
        }

        if (!empty($validated['address'])) {
            $query->where('address', 'like', '%' . $validated['address'] . '%');
        }

        $hospitals = $query
            ->select([
                'id',
                'account_id',
                'full_name',
                'address',
                'location',
                'avg_rating',
                'profile_image_path',
                'province_id',
                'hospital_type_id',
            ])
            ->paginate(10)
            ->through(function ($hospital) {
                return [
                    'id'         => $hospital->id,
                    'full_name'  => $hospital->full_name,
                    'address'    => $hospital->address,
                    'avg_rating' => max(4, $hospital->avg_rating),
                    'location'   => $hospital->location,

                    'province' => $hospital->province ? [
                        'id'      => $hospital->province->id,
                        'name_ar' => $hospital->province->name_ar,
                        'name_en' => $hospital->province->name_en,
                    ] : null,

                    'hospital_type' => $hospital->hospitalType ? [
                        'id'       => $hospital->hospitalType->id,
                        'name_ar'  => $hospital->hospitalType->name_ar,
                        'name_en'  => $hospital->hospitalType->name_en,
                        'icon_url' => $hospital->hospitalType->icon_url,
                    ] : null,

                    'services' => $hospital->services->map(function ($service) {
                        return [
                            'id'    => $service->id,
                            'name'  => $service->service->service_name ?? null,
                            'description' => $service->description,
                            'price' => $service->price,
                            'icon' => $service->service?->icon,
                        ];
                    }),
                ];
            });

        return response()->json($hospitals);
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

        // Format the response to include service name and price (capacity hidden)
        $formattedServices = $services->map(function ($service) {
            return [
                'service_id' => $service->pivot->id,
                'service_name' => $service->service_name,
                'description' => $service->pivot->description,
                'icon' => $service->icon,
                'price' => $service->pivot->price, // Access price from pivot table
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
            'hospital_type_id' => 'nullable|integer|exists:hospital_types,id',
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
            ->with('hospitalType:id,name_ar,name_en,icon')
            ->when($request->filled('hospital_type_id'), fn ($q) => $q->where('hospital_type_id', $request->hospital_type_id))
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
