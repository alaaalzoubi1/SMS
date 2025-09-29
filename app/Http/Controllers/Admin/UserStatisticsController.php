<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserStatisticsController extends Controller
{
    public function users(): JsonResponse
    {
        return response()->json([
            'users' => User::with('account:id,email,phone_number')->paginate(10)
        ]);
    }

    public function userReservations(Request $request ,$id): JsonResponse

    {
        // حالات موحّدة تتعامل بها الواجهة: pending, approved, cancelled, rejected, completed
        $allowedApiStatuses = ['pending', 'approved', 'cancelled', 'rejected', 'completed'];

        $request->validate([
            'status' => ['nullable', Rule::in($allowedApiStatuses)],
        ]);

        // خريطة التحويل: من حالة API الموحّدة -> إلى قيمة enum في كل جدول
        $statusMap = [
            'pending'   => ['nurse' => 'pending',  'hospital' => 'pending',   'doctor' => 'pending'],
            'approved'  => ['nurse' => 'accepted', 'hospital' => 'confirmed', 'doctor' => 'approved'],
            'cancelled' => ['nurse' => null,       'hospital' => 'cancelled', 'doctor' => 'cancelled'],
            'rejected'  => ['nurse' => 'rejected', 'hospital' => null,        'doctor' => 'rejected'],
            'completed' => ['nurse' => 'completed','hospital' => null,        'doctor' => 'completed'],
        ];

        $with = [
            'nurseReservations',
            'hospitalReservations',
            'doctorReservations',
        ];

        if ($request->filled('status')) {
            $apiStatus = $request->string('status')->toString();
            $map = $statusMap[$apiStatus];

            $with = [
                'nurseReservations' => function ($q) use ($map) {
                    // إن كانت غير مدعومة في هذا الجدول نرجّع صفوفًا صفرية
                    return is_null($map['nurse']) ? $q->whereRaw('1=0') : $q->where('status', $map['nurse']);
                },
                'hospitalReservations' => function ($q) use ($map) {
                    return is_null($map['hospital']) ? $q->whereRaw('1=0') : $q->where('status', $map['hospital']);
                },
                'doctorReservations' => function ($q) use ($map) {
                    return is_null($map['doctor']) ? $q->whereRaw('1=0') : $q->where('status', $map['doctor']);
                },
            ];
        }

        $user = User::query()
            ->with($with) // تحميل مسبق مع قيود لكل علاقة
            ->where('id', $id)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'nurse_reservations'    => $user->nurseReservations,
                'hospital_reservations' => $user->hospitalReservations,
                'doctor_reservations'   => $user->doctorReservations,
            ],
        ]);
    }
}
