<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterDoctorsRequest;
use App\Models\Doctor;
use App\Http\Requests\StoreDoctorRequest;
use App\Http\Requests\UpdateDoctorRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use MatanYadaev\EloquentSpatial\Objects\Point;

class DoctorController extends Controller
{
    public function listForUsers(FilterDoctorsRequest $request): JsonResponse
    {
        $query = Doctor::query()->with('specialization:id,name_en,name_ar,image' , 'account:id,phone_number')->Approved();

        if ($request->filled('specialization_id')) {
            $query->where('specialization_id', $request->specialization_id);
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        if ($request->filled('address')) {
            $query->where('address', 'like', '%' . $request->address . '%');
        }

        if ($request->filled('full_name')) {
            $query->where('full_name', 'like', '%' . $request->full_name . '%');
        }

        $doctors = $query->select('id', 'full_name', 'address', 'age', 'gender', 'specialization_id', 'profile_description', 'account_id','location','profile_image_path')
            ->paginate(10);

        $doctors->getCollection()->transform(function ($doctor) {
            $doctor->avg_rating = max(4,$doctor->avg_rating);

            $doctor->phone_number = $doctor->account->phone_number ?? null;

            unset($doctor->account);

            return $doctor;
        });

        return response()->json($doctors);
    }
    public function getNearestDoctors(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'specialization_id' => 'required|exists:specializations,id'
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

        $doctors = Doctor::query()
            ->Approved()
            ->where('specialization_id',$request->specialization_id)
            ->whereNotNull('location')
            ->withDistanceSphere('location', $point, 'distance_meters')
            ->orderBy('distance_meters')
            ->limit(10)
            ->get()
            ->transform(function ($doctor) {
                $doctor->avg_rating = max(4, $doctor->avg_rating);
                return $doctor;
            })
            ->makeHidden(['license_image_path', 'deleted_at', 'created_at', 'updated_at']);
        return response()->json([
            'doctors' => $doctors,
        ]);
    }
}
