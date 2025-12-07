<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\DoctorReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorStatisticsController extends Controller
{
    public function doctors(): JsonResponse
    {
        return response()->json([
            'doctors' => Doctor::with(['account:id,email,phone_number','specialization:id,name_ar,name_en,image'])->paginate(10)
        ]);
    }
    public function doctor($id): JsonResponse
    {
        return response()->json([
            'doctor' => Doctor::with(['account:id,email,phone_number,created_at,updated_at','services','doctorWorkSchedule','specialization:id,name_en,name_ar,image,created_at,updated_at'])->where('id',$id)->first()
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
