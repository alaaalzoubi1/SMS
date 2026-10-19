<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GraduationType;
use App\Http\Controllers\Admin\Concerns\FiltersProviderAccounts;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\FiltersReservedByAdmin;
use App\Models\NurseService;
use App\Models\Nurse;
use App\Models\NurseReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use TarfinLabs\LaravelSpatial\Types\Point;

class NurseStatisticsController extends Controller
{
    use FiltersProviderAccounts, FiltersReservedByAdmin;

    public function nurses(Request $request): JsonResponse
    {
        $request->validate(array_merge($this->accountFilterRules(), [
            'graduation_type' => ['nullable', Rule::in(GraduationType::values())],
            'gender'          => ['nullable', 'in:male,female'],
            'full_name'       => ['nullable', 'string', 'max:255'],
            'address'         => ['nullable', 'string', 'max:255'],
            'age'             => ['nullable', 'integer', 'min:0', 'max:150'],
            'email'           => ['nullable', 'string', 'max:255'],
            'phone_number'    => ['nullable', 'string', 'max:20'],
            'service_id'      => ['nullable', 'integer', 'exists:services,id'],
            'service_name'    => ['nullable', 'string', 'max:255'],
            'is_active'       => ['nullable', 'in:0,1,true,false'],
        ]));

        if ($request->filled('graduation_type')) {
            $request->merge([
                'graduation_type' => GraduationType::normalize((string) $request->input('graduation_type')),
            ]);
        }

        $query = Nurse::query()
            ->with(array_merge($this->withAccount(), ['province:id,name_ar,name_en', 'services.service']))
            ->withCount([
                'reservations',
                'reservations as completed_reservations_count' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->when($request->filled('email'),
                fn ($q) => $q->whereRelation('account', 'email', 'like', '%' . $request->email . '%')
            )
            ->when($request->filled('phone_number'),
                fn ($q) => $q->whereRelation('account', 'phone_number', 'like', '%' . $request->phone_number . '%')
            )
            ->when($request->filled('service_id'),
                fn ($q) => $q->whereHas('services', fn ($s) => $s->where('service_id', $request->service_id))
            )
            ->when($request->filled('service_name'),
                fn ($q) => $q->whereHas('services.service', fn ($s) => $s->where('service_name', 'like', '%' . $request->service_name . '%'))
            )
            ->when($request->filled('is_active'),
                fn ($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN))
            )

            ->when($request->filled('full_name'),
                fn ($q) => $q->where('full_name', 'like', '%' . $request->full_name . '%')
            )
            ->when($request->filled('address'),
                fn ($q) => $q->where('address', 'like', '%' . $request->address . '%')
            )
            ->when($request->filled('graduation_type'),
                fn ($q) => $q->where('graduation_type', $request->graduation_type)
            )
            ->when($request->filled('age'),
                fn ($q) => $q->where('age', $request->age)
            )
            ->when($request->filled('gender'),
                fn ($q) => $q->where('gender', $request->gender)
            );

        // province, suspension, approval, subscription, dates, deleted, sort
        $this->applyAccountFilters($query, $request);

        return response()->json([
            'nurses' => $query->paginate($request->input('per_page', 10))
        ]);
    }

    /**
     * GET api/admin/nurse/{id}
     *
     * Opening a nurse's account in the dashboard showed nothing: the query
     * eager-loaded `services.subservices`, a relation that no longer exists
     * (NurseSubservice is commented out), so the endpoint crashed with a 500
     * for every nurse. It now loads the real service catalog entry.
     */
    public function nurse($id): JsonResponse
    {
        $nurse = Nurse::withTrashed()
            ->with(array_merge($this->withAccount(), ['services.service', 'province:id,name_ar,name_en']))
            ->withCount([
                'reservations',
                'reservations as pending_reservations_count' => fn ($q) => $q->where('status', 'pending'),
                'reservations as completed_reservations_count' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->find($id);

        if (!$nurse) {
            return response()->json(['message' => 'الممرض غير موجود.'], 404);
        }

        return response()->json([
            'nurse' => $nurse,
        ]);
    }

    /**
     * GET api/admin/nurse/services/search
     *
     * Admin search "which nurse offers service X" (to book it on behalf of a
     * patient). The only endpoint that searched nurse services lived under
     * api/user (role:user), so from the admin panel it returned nothing.
     * Unlike the patient endpoint this also returns nurses who are currently
     * inactive, flagged with `nurse.is_active`.
     *
     * Query: name | service_id, max_price, province_id, gender, only_active,
     *        per_page.
     */
    public function searchServices(Request $request): JsonResponse
    {
        $request->validate([
            'name'        => 'nullable|string|max:255',
            'service_id'  => 'nullable|integer|exists:services,id',
            'max_price'   => 'nullable|numeric|min:0',
            'province_id' => 'nullable|integer|exists:provinces,id',
            'gender'      => 'nullable|in:male,female',
            'only_active' => 'nullable|in:0,1,true,false',
            'per_page'    => 'nullable|integer|min:1|max:100',
        ]);

        $services = NurseService::query()
            ->with([
                'service:id,service_name,icon,service_type',
                'nurse:id,account_id,full_name,address,gender,graduation_type,is_active,province_id,location',
                'nurse.account:id,phone_number,is_suspended,subscription_expires_at',
                'nurse.province:id,name_ar,name_en',
            ])
            ->whereHas('nurse', function ($q) use ($request) {
                $q->approved()
                    ->when($request->filled('province_id'), fn ($n) => $n->where('province_id', $request->province_id))
                    ->when($request->filled('gender'), fn ($n) => $n->where('gender', $request->gender))
                    ->when(filter_var($request->input('only_active'), FILTER_VALIDATE_BOOLEAN), fn ($n) => $n->active());
            })
            ->when($request->filled('service_id'), fn ($q) => $q->where('service_id', $request->service_id))
            ->when($request->filled('name'), fn ($q) => $q->whereHas('service', fn ($s) => $s->where('service_name', 'like', '%' . trim($request->name) . '%')))
            ->when($request->filled('max_price'), fn ($q) => $q->where('price', '<=', $request->max_price))
            ->orderBy('price')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'services' => $services,
        ]);
    }
    public function nurseReservations(Request $request ,$id): JsonResponse
    {
        $request->validate([
            // `cancelled` was missing from the allowed values.
            'status' => 'nullable|in:pending,accepted,rejected,completed,cancelled',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:100',
            'reserved_by_admin' => 'nullable',
        ]);
        $byAdmin = $this->reservedByAdmin($request);

        $query = NurseReservation::where('nurse_id', $id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($byAdmin !== null, fn ($q) => $q->where('reserved_by_admin', $byAdmin))
            ->when($request->from, fn($q) => $q->whereDate('start_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('start_at', '<=', $request->to))
            ->orderBy('start_at', 'desc')
            ->with(['user.account', 'nurseService.service', 'services.service']);

        $perPage = $request->input('per_page', 10);

        return response()->json(
            $query->paginate($perPage)
        );
    }
    public function getNurseLicense($nurseId)
    {
        $nurse = Nurse::findOrFail($nurseId);

        if (!$nurse->license_image_path) {
            abort(404, 'لا توجد شهادة مخزنة لهذا الممرض');
        }

        $path = storage_path('app/private/' . $nurse->license_image_path);

        if (!file_exists($path)) {
            abort(404, 'ملف الشهادة غير موجود');
        }

        return response()->file($path);
    }

}
