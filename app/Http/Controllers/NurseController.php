<?php

namespace App\Http\Controllers;

use App\Http\Requests\NurseFilterRequest;
use App\Models\Nurse;
use App\Http\Requests\StoreNurseRequest;
use App\Http\Requests\UpdateNurseRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use MatanYadaev\EloquentSpatial\Objects\Point;

class NurseController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function listForUsers(NurseFilterRequest $request): JsonResponse
    {

        // Start building the query for nurses
        $query = Nurse::query()
            ->with(['services.subservices','province']) // Eager load services and subservices
            ->Approved()
            ->Active();
//            ->select('id', 'full_name', 'address','location', 'graduation_type', 'age', 'gender', 'profile_description');

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
        if ($request->filled('latitude') && $request->filled('longitude'))
        {
            $radius = 5000;
            $point = new Point($request->latitude,$request->longitude);

            $query
                ->withDistanceSphere('location', $point, 'distance_meters') // تضيف المسافة في الـ select
                ->orderByDistanceSphere('location', $point, 'asc') // ترتيب حسب المسافة
                ->limit(10);
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
                'location'=>$nurse->location,
                'services' => $nurse->services,
                'avg_rating' => max(4,$nurse->avg_rating),
                'distance_meters' => $nurse->distance_meters,
                'profile_image' => $nurse->profile_image_path,
                'province' => $nurse->province
            ];
        });

        // Return the paginated nurses
        return response()->json($nurses);
    }

    public function getNearestNurses(Request $request): JsonResponse
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

        $nurses = Nurse::query()
            ->Active()
            ->Approved()
            ->whereNotNull('location')
            ->withDistanceSphere('location', $point, 'distance_meters')
            ->orderBy('distance_meters')
            ->limit(10)
            ->get()
            ->transform(function ($nurse){
                $nurse->avg_rating = max(4,$nurse->avg_rating);
                return $nurse;
            })
            ->makeHidden(['license_image_path', 'deleted_at', 'created_at', 'updated_at']);
        return response()->json([
            'nurses' => $nurses,
        ]);
    }
//    public function getNearestNurses(Request $request): JsonResponse
//    {
//        $latitude = $request->input('latitude');
//        $longitude = $request->input('longitude');
//        $radius = 500000000000; // 5 km
//
//        // أنشئ النقطة
//        $point = new Point($latitude, $longitude, 4326);
//        $pointWKT = $point->toWkt(); // "POINT(lon lat)"
//
//        $nurses = Nurse::
////            Approved()
////        Active()
//            select('*')
//            ->addSelect(DB::raw("ST_Distance_Sphere(location, ST_GeomFromText(?, 4326)) as distance"))
//            ->whereNotNull('location')
//            ->whereRaw("ST_Distance_Sphere(location, ST_GeomFromText(?, 4326)) <= ?", [$pointWKT, $pointWKT, $radius])
//            ->orderBy('distance', 'asc')
//            ->limit(10)
//            ->get()
//            ->makeHidden(['license_image_path','deleted_at','created_at','updated_at']);
//
//        return response()->json([
//            'nurses' => $nurses,
//        ]);
//    }
//TODO make api is_active

    public function activate(): JsonResponse
    {
        $nurse = auth()->user()->nurse;
        $nurse->is_active = !$nurse->is_active;
        $nurse->save();
        return response()->json([
            'is_active' => $nurse->is_active
        ]);
    }
}
