<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GraduationType;
use App\Http\Controllers\Controller;
use App\Models\Nurse;
use App\Models\NurseReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use TarfinLabs\LaravelSpatial\Types\Point;

class NurseStatisticsController extends Controller
{
    public function nurses(Request $request): JsonResponse
    {
        $request->validate([
            'graduation_type' => ['nullable', Rule::in(GraduationType::values())],
            'gender'          => ['nullable', 'in:male,female'],
            'full_name'       => ['nullable', 'string', 'max:255'],
            'address'         => ['nullable', 'string', 'max:255'],
            'age'             => ['nullable', 'integer', 'min:0', 'max:150'],
        ]);

        if ($request->filled('graduation_type')) {
            $request->merge([
                'graduation_type' => GraduationType::normalize((string) $request->input('graduation_type')),
            ]);
        }

        $query = Nurse::query()
            ->with('account:id,email,phone_number')

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
        return response()->json([
            'nurses' => $query->paginate(10)
        ]);
    }
    public function nurse($id): JsonResponse
    {
        return response()->json([
            'nurse' => Nurse::with(['account','services.subservices'])->where('id',$id)->first()
        ]);
    }
    public function nurseReservations(Request $request ,$id): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|in:pending,accepted,rejected,completed',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);


        $query = NurseReservation::where('nurse_id', $id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
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
