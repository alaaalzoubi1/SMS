<?php

namespace App\Http\Controllers;

use App\Http\Requests\NurseFilterRequest;
use App\Models\Nurse;
use App\Http\Requests\StoreNurseRequest;
use App\Http\Requests\UpdateNurseRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use TarfinLabs\LaravelSpatial\Types\Point;

class NurseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreNurseRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Nurse $nurse)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Nurse $nurse)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateNurseRequest $request, Nurse $nurse)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Nurse $nurse)
    {
        //
    }
    public function listForUsers(NurseFilterRequest $request): JsonResponse
    {

        // Start building the query for nurses
        $query = Nurse::query()
            ->with(['services.subservices']) // Eager load services and subservices
            ->select('id', 'full_name', 'address', 'graduation_type', 'age', 'gender', 'profile_description');

        // Apply filters if present
        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        if ($request->filled('graduation_type')) {
            $query->where('graduation_type', $request->graduation_type);
        }

        if ($request->filled('address')) {
            $query->where('address', 'like', '%' . $request->address . '%');
        }

        if ($request->filled('full_name')) {
            $query->where('full_name', 'like', '%' . $request->full_name . '%');
        }

        // Get the nurses
        $nurses = $query->paginate(10); // Adjust pagination as needed

        // Transform the nurses with relevant data only
        $nurses->getCollection()->transform(function ($nurse) {
            // Get services and subservices with only necessary columns
            $nurse->services = $nurse->services->map(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'price' => $service->price,
                    'subservices' => $service->subservices->map(function ($subservice) {
                        return [
                            'id' => $subservice->id,
                            'name' => $subservice->name,
                            'price' => $subservice->price,
                        ];
                    })
                ];
            });

            // Return only the necessary data
            return [
                'id' => $nurse->id,
                'full_name' => $nurse->full_name,
                'address' => $nurse->address,
                'graduation_type' => $nurse->graduation_type,
                'age' => $nurse->age,
                'gender' => $nurse->gender,
                'profile_description' => $nurse->profile_description,
                'services' => $nurse->services,
            ];
        });

        // Return the paginated nurses
        return response()->json($nurses);
    }
    public function getNearestNurses(Request $request)
    {
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $minNurses = 10;
        $radius = 5000; // Start with 1 km
        $maxRadius = 50; // Maximum limit to prevent overload

        $point = new Point(lat: $latitude, lng: $longitude,srid: 4326);

        $nurses = Nurse::withinDistanceTo('location', $point, $radius)
            ->selectDistanceTo('location', $point)
            ->orderByDistanceTo('location', $point, 'asc')
            ->limit(10)
            ->get();

        return response()->json([
            'nurses' => $nurses,
        ]);
    }


}
