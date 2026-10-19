<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\FiltersProviderAccounts;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\FiltersReservedByAdmin;
use App\Models\Hospital;
use App\Models\HospitalServiceReservation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HospitalStatisticsController extends Controller
{
    use FiltersProviderAccounts, FiltersReservedByAdmin;

    public function hospitals(Request $request): JsonResponse
    {
        $request->validate(array_merge($this->accountFilterRules(), [
            'service_id' => 'nullable|integer|exists:services,id',
            'has_vacancy' => 'nullable|in:0,1,true,false',
            'hospital_type_id' => 'nullable|integer|exists:hospital_types,id',
        ]));

        $query = Hospital::query()
            ->with(array_merge($this->withAccount(), ['province:id,name_ar,name_en', 'hospitalType:id,name_ar,name_en']))
            ->when($request->filled('hospital_type_id'), fn ($q) => $q->where('hospitals.hospital_type_id', $request->hospital_type_id))
            ->withCount([
                'reservations',
                'reservations as finished_reservations_count' => fn ($q) => $q->where('status', 'finished'),
            ])
            ->when($request->filled('service_id'),
                fn ($q) => $q->whereHas('services', fn ($s) => $s->where('service_id', $request->service_id))
            )
            ->when(filter_var($request->input('has_vacancy'), FILTER_VALIDATE_BOOLEAN),
                fn ($q) => $q->whereHas('services', fn ($s) => $s->where('capacity', '>', 0)
                    ->when($request->filled('service_id'), fn ($x) => $x->where('service_id', $request->service_id)))
            );

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

        // province, suspension, subscription, dates, deleted, sort
        $this->applyAccountFilters($query, $request);

        return response()->json([
            'hospitals' => $query->paginate($request->input('per_page', 10))
        ]);
    }
    public function hospital($id): JsonResponse
    {
        return response()->json([
            'hospital' => Hospital::withTrashed()
                ->with(array_merge($this->withAccount(), ['services_2','workSchedule','province:id,name_ar,name_en','hospitalType:id,name_ar,name_en']))
                ->withCount('reservations')
                ->where('id',$id)->first()
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
            // `accepted` and `finished` were rejected before.
            'status' => 'nullable|in:pending,accepted,confirmed,finished,cancelled',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:20',
            'reserved_by_admin' => 'nullable',
        ]);
        $byAdmin = $this->reservedByAdmin($request);

        // Requests that have not started yet have no start_date, so the date
        // range falls back to the request date (created_at) for them —
        // otherwise pending / accepted requests could never be found by date.
        $query = HospitalServiceReservation::where('hospital_id', $id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->from, fn($q) => $q->whereRaw('DATE(COALESCE(start_date, created_at)) >= ?', [$request->from]))
            ->when($request->to, fn($q) => $q->whereRaw('DATE(COALESCE(start_date, created_at)) <= ?', [$request->to]))
            ->when($byAdmin !== null, fn ($q) => $q->where('reserved_by_admin', $byAdmin))
            ->orderByRaw('COALESCE(start_date, created_at) DESC')
            ->with(['user.account', 'hospitalService.service']);

        $perPage = $request->input('per_page', 10);

        return response()->json(
            $query->paginate($perPage)
        );
    }
}

