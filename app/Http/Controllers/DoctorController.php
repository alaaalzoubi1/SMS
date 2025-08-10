<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterDoctorsRequest;
use App\Models\Doctor;
use App\Http\Requests\StoreDoctorRequest;
use App\Http\Requests\UpdateDoctorRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    public function listForUsers(FilterDoctorsRequest $request): JsonResponse
    {
        $query = Doctor::query()->Approved()->with('specialization:id,name_en,name_ar,image' , 'account:id,phone_number');

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

        $doctors = $query->select('id', 'full_name', 'address', 'age', 'gender', 'specialization_id', 'profile_description', 'account_id')
            ->paginate(10);

        // تعديل specialization_type ليصبح الاسم العربي
        $doctors->getCollection()->transform(function ($doctor) {

            // إظهار رقم الهاتف من العلاقة
            $doctor->phone_number = $doctor->account->phone_number ?? null;

            // إخفاء القيم غير الضرورية
            unset($doctor->account);

            return $doctor;
        });

        return response()->json($doctors);
    }


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
    public function store(StoreDoctorRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Doctor $doctor)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Doctor $doctor)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDoctorRequest $request, Doctor $doctor)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Doctor $doctor)
    {
        //
    }
}
