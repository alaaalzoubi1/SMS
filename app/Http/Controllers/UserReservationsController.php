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
            'type'   => ['nullable', Rule::in($allowedTypes)],
        ]);

        $statusMap = [
            'pending'   => ['nurse' => 'pending',  'hospital' => 'pending',   'doctor' => 'pending'],
            'approved'  => ['nurse' => 'accepted', 'hospital' => ['confirmed','accepted'], 'doctor' => 'approved'],
            'cancelled' => ['nurse' => 'cancelled','hospital' => 'cancelled', 'doctor' => 'cancelled'],
            'rejected'  => ['nurse' => 'rejected', 'hospital' => null,        'doctor' => null],
            'completed' => ['nurse' => 'completed','hospital' => 'finished',  'doctor' => 'completed'],
        ];

        $userId = auth()->user()->user->id;
        $apiStatus = $request->input('status');
        $type = $request->input('type');

        // نحضّر المتغيرات
        $data = [];

        switch ($type) {
            case 'nurse':
                $reservations = \App\Models\NurseReservation::query()
                    ->where('user_id', $userId)
                    ->with(['nurse','nurse.account:id,phone_number', 'nurseService', 'subserviceReservations'])
                    ->when($apiStatus, function ($q) use ($statusMap, $apiStatus) {
                        $status = $statusMap[$apiStatus]['nurse'] ?? null;
                        if ($status) $q->where('status', $status);
                        else $q->whereRaw('1=0');
                    })
                    ->orderByDesc('created_at')
                    ->get();

                $data['nurse_reservations'] = $reservations;
                break;

            case 'hospital':
                $reservations = \App\Models\HospitalServiceReservation::query()
                    ->where('user_id', $userId)
                    ->with(['hospital', 'hospitalService.service'])
                    ->when($apiStatus, function ($q) use ($statusMap, $apiStatus) {
                        $status = $statusMap[$apiStatus]['hospital'] ?? null;
                        if ($status) $q->where('status', $status);
                        else $q->whereRaw('1=0');
                    })
                    ->orderByDesc('created_at')
                    ->get();

                $data['hospital_reservations'] = $reservations;
                break;

            case 'doctor':
                $reservations = \App\Models\DoctorReservation::query()
                    ->where('user_id', $userId)
                    ->with(['doctor.specialization', 'doctorService'])
                    ->when($apiStatus, function ($q) use ($statusMap, $apiStatus) {
                        $status = $statusMap[$apiStatus]['doctor'] ?? null;
                        if ($status) $q->where('status', $status);
                        else $q->whereRaw('1=0');
                    })
                    ->orderByDesc('created_at')
                    ->get();

                $data['doctor_reservations'] = $reservations;
                break;

            default:
                // في حال لم يُحدد نوع، رجّع الكل (لكن مع eager loading محدد)
                $user = \App\Models\User::query()
                    ->with([
                        'nurseReservations' => fn($q) => $q->with(['nurse','nurseService','subserviceReservations'])->orderByDesc('created_at'),
                        'hospitalReservations' => fn($q) => $q->with(['hospital','hospitalService.service'])->orderByDesc('created_at'),
                        'doctorReservations' => fn($q) => $q->with(['doctor.specialization','doctorService'])->orderByDesc('created_at'),
                    ])
                    ->findOrFail($userId);

                $data = [
                    'nurse_reservations'    => $user->nurseReservations,
                    'hospital_reservations' => $user->hospitalReservations,
                    'doctor_reservations'   => $user->doctorReservations,
                ];
                break;
        }

        return response()->json(['data' => $data]);
    }





}
