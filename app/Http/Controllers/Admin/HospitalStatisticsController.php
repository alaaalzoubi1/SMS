<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Models\HospitalServiceReservation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HospitalStatisticsController extends Controller
{
    public function hospitals(Request $request): JsonResponse
    {
        $query = Hospital::query()
            ->with('account:id,email,phone_number');

        $filters = collect($request->only([
            'full_name',
            'address',
            'location',
            'email',
            'phone_number'
        ]))->filter();

        $filters->each(function ($value, $key) use ($query) {

            match ($key) {

                'full_name',
                'address',
                'location' =>
                $query->where($key, 'like', "%{$value}%"),

                'email',
                'phone_number' =>
                $query->whereHas('account', function ($q) use ($key, $value) {
                    $q->where($key, 'like', "%{$value}%");
                }),

                default => null
            };
        });

        return response()->json([
            'hospitals' => $query->paginate(10)
        ]);
    }
    public function hospital($id): JsonResponse
    {
        return response()->json([
            'hospital' => Hospital::with(['account:id,email,phone_number,created_at,updated_at','services_2','workSchedule'])->where('id',$id)->first()
        ]);
    }
    /**
     * Search hospitals in a province that currently have capacity (> 0)
     * for a specific service.
     */
    public function searchByProvinceAndService(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'province_id' => 'required|integer|exists:provinces,id',
            'service_id'  => 'required|integer|exists:services,id',
        ]);

        $hospitals = Hospital::query()
            ->where('province_id', $validated['province_id'])
            ->whereHas('services_2', function ($q) use ($validated) {
                $q->where('service_id', $validated['service_id'])
                  ->where('capacity', '>', 0);
            })
            ->with(['account:id,email,phone_number', 'province:id,name_ar,name_en'])
            ->with(['services_2' => function ($q) use ($validated) {
                $q->where('service_id', $validated['service_id'])
                  ->where('capacity', '>', 0);
            }])
            ->paginate(10);

        return response()->json([
            'hospitals' => $hospitals,
        ]);
    }
    public function hospitalReservations(Request $request ,$id): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|in:pending,confirmed,cancelled',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:20',
        ]);
        $query = HospitalServiceReservation::where('hospital_id', $id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->from, fn($q) => $q->whereDate('start_date', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('start_date', '<=', $request->to))
            ->orderBy('start_date', 'desc')
            ->with(['user.account', 'hospitalService.service']);

        $perPage = $request->input('per_page', 10);

        return response()->json(
            $query->paginate($perPage)
        );
    }
}

