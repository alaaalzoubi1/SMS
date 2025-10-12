<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserReservationsController extends Controller
{
    public function myReservations(Request $request)
    {
        $allowedApiStatuses = ['pending', 'approved', 'cancelled', 'rejected', 'completed'];
        $allowedTypes = ['nurse', 'doctor', 'hospital'];

        $request->validate([
            'status' => ['nullable', Rule::in($allowedApiStatuses)],
            'type'   => ['nullable', Rule::in($allowedTypes)], // نوع الحجز المطلوب
        ]);

        $statusMap = [
            'pending'   => ['nurse' => 'pending',  'hospital' => 'pending',   'doctor' => 'pending'],
            'approved'  => ['nurse' => 'accepted', 'hospital' => 'confirmed', 'doctor' => 'approved'],
            'cancelled' => ['nurse' => null,       'hospital' => 'cancelled', 'doctor' => 'cancelled'],
            'rejected'  => ['nurse' => 'rejected', 'hospital' => null,        'doctor' => 'rejected'],
            'completed' => ['nurse' => 'completed','hospital' => null,        'doctor' => 'completed'],
        ];

        $with = [
            'nurseReservations.nurse',
            'hospitalReservations.hospital',
            'doctorReservations.doctor',
        ];

        // فلترة حسب الحالة
        if ($request->filled('status')) {
            $apiStatus = $request->string('status')->toString();
            $map = $statusMap[$apiStatus];

            $with = [
                'nurseReservations' => function ($q) use ($map) {
                    return is_null($map['nurse'])
                        ? $q->whereRaw('1=0')
                        : $q->where('status', $map['nurse'])
                            ->with(['nurse','nurseService','subserviceReservations'])
                            ->orderByDesc('created_at');
                },
                'hospitalReservations' => function ($q) use ($map) {
                    return is_null($map['hospital'])
                        ? $q->whereRaw('1=0')
                        : $q->where('status', $map['hospital'])
                            ->with(['hospital','hospitalService'])
                            ->orderByDesc('created_at');
                },
                'doctorReservations' => function ($q) use ($map) {
                    return is_null($map['doctor'])
                        ? $q->whereRaw('1=0')
                        : $q->where('status', $map['doctor'])
                            ->with(['doctor.specialization','doctorService'])
                            ->orderByDesc('created_at');
                },
            ];
        } else {
            // في حال لم يتم تحديد حالة، نرتب الكل من الأحدث للأقدم
            $with = [
                'nurseReservations' => fn($q) => $q->with(['nurse','nurseService','subserviceReservations'])->orderByDesc('created_at'),
                'hospitalReservations' => fn($q) => $q->with(['hospital','hospitalService'])->orderByDesc('created_at'),
                'doctorReservations' => fn($q) => $q->with(['doctor.specialization','doctorService'])->orderByDesc('created_at'),
            ];
        }

        $user = User::query()
            ->with($with)
            ->where('id', auth()->user()->user->id)
            ->firstOrFail();

        // فلترة حسب نوع الحجز المطلوب من الـ request
        $response = [];

        switch ($request->input('type')) {
            case 'nurse':
                $response['nurse_reservations'] = $user->nurseReservations;
                break;
            case 'hospital':
                $response['hospital_reservations'] = $user->hospitalReservations;
                break;
            case 'doctor':
                $response['doctor_reservations'] = $user->doctorReservations;
                break;
            default:
                // إذا ما حدد النوع، رجع الكل
                $response = [
                    'nurse_reservations'    => $user->nurseReservations,
                    'hospital_reservations' => $user->hospitalReservations,
                    'doctor_reservations'   => $user->doctorReservations,
                ];
                break;
        }

        return response()->json(['data' => $response]);
    }




}
