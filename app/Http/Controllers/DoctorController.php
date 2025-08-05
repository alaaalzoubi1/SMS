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
        $query = Doctor::query()->with('account:id,phone_number');

        if ($request->filled('specialization_type')) {
            $query->where('specialization_type', $request->specialization_type);
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

        $doctors = $query->select('id', 'full_name', 'address', 'age', 'gender', 'specialization_type', 'profile_description', 'account_id')
            ->paginate(10);

        // تعديل specialization_type ليصبح الاسم العربي
        $doctors->getCollection()->transform(function ($doctor) {
            $doctor->specialization_name = is_int($doctor->specialization_type)
                ? \App\Enums\SpecializationType::tryFrom($doctor->specialization_type)?->label()
                : ($doctor->specialization_type instanceof \App\Enums\SpecializationType
                    ? $doctor->specialization_type->label()
                    : null);



            // إظهار رقم الهاتف من العلاقة
            $doctor->phone_number = $doctor->account->phone_number ?? null;

            // إخفاء القيم غير الضرورية
            unset($doctor->specialization_type, $doctor->account);

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
