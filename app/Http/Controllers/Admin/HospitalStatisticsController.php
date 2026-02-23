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

