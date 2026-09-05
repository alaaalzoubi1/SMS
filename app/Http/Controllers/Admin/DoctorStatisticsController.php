<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\DoctorReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorStatisticsController extends Controller
{
    public function doctors(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'age' => 'nullable|integer|min:0|max:150',
            'gender' => 'nullable|in:male,female',
            'birthdate' => 'nullable|date',
            'specialization_id' => 'nullable|integer|exists:specializations,id',
            'province_id' => 'nullable|integer|exists:provinces,id',
            'email' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'name_ar' => 'nullable|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Doctor::query()
            ->with([
                'account:id,email,phone_number',
                'specialization:id,name_ar,name_en,image'
            ]);

        $query
            ->when($validated['full_name'] ?? null, fn ($q, $v) => $q->where('full_name', 'like', "%{$v}%"))
            ->when($validated['address'] ?? null, fn ($q, $v) => $q->where('address', 'like', "%{$v}%"))
            ->when($validated['location'] ?? null, fn ($q, $v) => $q->where('location', 'like', "%{$v}%"))
            ->when($validated['age'] ?? null, fn ($q, $v) => $q->where('age', $v))
            ->when($validated['gender'] ?? null, fn ($q, $v) => $q->where('gender', $v))
            ->when($validated['birthdate'] ?? null, fn ($q, $v) => $q->whereDate('birthdate', $v))
            ->when($validated['specialization_id'] ?? null, fn ($q, $v) => $q->where('specialization_id', $v))
            ->when($validated['province_id'] ?? null, fn ($q, $v) => $q->where('province_id', $v))
            ->when($validated['email'] ?? null, fn ($q, $v) => $q->whereRelation('account', 'email', 'like', "%{$v}%"))
            ->when($validated['phone_number'] ?? null, fn ($q, $v) => $q->whereRelation('account', 'phone_number', 'like', "%{$v}%"))
            ->when($validated['name_ar'] ?? null, fn ($q, $v) => $q->whereRelation('specialization', 'name_ar', 'like', "%{$v}%"))
            ->when($validated['name_en'] ?? null, fn ($q, $v) => $q->whereRelation('specialization', 'name_en', 'like', "%{$v}%"));

        $perPage = $validated['per_page'] ?? 10;

        return response()->json([
            'doctors' => $query->paginate($perPage)
        ]);
    }
    public function doctor($id): JsonResponse
    {
        return response()->json([
            'doctor' => Doctor::with(['account:id,email,phone_number,created_at,updated_at','services','doctorWorkSchedule','specialization:id,name_en,name_ar,image,created_at,updated_at'])->where('id',$id)->firstOrFail()
        ]);
    }
    public function doctorReservations(Request $request ,$id): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|in:pending,approved,rejected,cancelled,completed',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:20',
        ]);


        $query = DoctorReservation::where('doctor_id', $id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->from, fn($q) => $q->whereDate('start_time', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('start_time', '<=', $request->to))
            ->orderBy('start_time', 'desc')
            ->with(['user.account:id,email,phone_number,created_at,updated_at', 'doctorService']);

        $perPage = $request->input('per_page', 10);

        return response()->json(
            $query->paginate($perPage)
        );
    }
    public function getDoctorLicense($doctorId)
    {
        $doctor = Doctor::findOrFail($doctorId);

        if (!$doctor->license_image_path) {
            return response()->json([
                'message' => 'لا توجد شهادة مخزنة لهذا الطبيب'
            ], 404);
        }

        $path = storage_path('app/private/' . $doctor->license_image_path);

        if (!file_exists($path)) {
            return response()->json([
                'message' => 'ملف الشهادة غير موجود'
            ], 404);
        }

        $mimeType = mime_content_type($path);
        $fileName = basename($path);

        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => "inline; filename=\"$fileName\""
        ]);
    }


}
