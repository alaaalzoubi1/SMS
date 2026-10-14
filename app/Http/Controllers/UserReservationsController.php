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
            'rejected'  => ['nurse' => 'rejected', 'hospital' => null,        'doctor' => 'rejected'],
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
                    ->with(['nurse','nurse.account:id', 'nurseService.service','services.service','cancellation','rate'])
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
                    ->with(['hospital.account:id,phone_number', 'hospitalService.service','cancellation','rate'])
                    ->when($apiStatus, function ($q) use ($statusMap, $apiStatus) {
                        $status = $statusMap[$apiStatus]['hospital'] ?? null;
                        if ($status) $q->where('status', $status);
                        else $q->whereRaw('1=0');
                    })
                    ->orderByDesc('created_at')
                    ->get();

                $data['hospital_reservations'] = $this->applyHospitalContactAccess($reservations);
                break;

            case 'doctor':
                $reservations = \App\Models\DoctorReservation::query()
                    ->where('user_id', $userId)
                    ->with(['doctor.specialization', 'doctor.account:id,phone_number', 'doctorService','cancellation','rate'])
                    ->when($apiStatus, function ($q) use ($statusMap, $apiStatus) {
                        $status = $statusMap[$apiStatus]['doctor'] ?? null;
                        if ($status) $q->where('status', $status);
                        else $q->whereRaw('1=0');
                    })
                    ->orderByDesc('created_at')
                    ->get();

                $data['doctor_reservations'] = $this->applyDoctorContactAccess($reservations);
                break;

            default:
                $user = \App\Models\User::query()
                    ->with([
                        'nurseReservations' => fn($q) => $q->with(['nurse','nurseService.service','services.service','cancellation','rate'])->orderByDesc('created_at'),
                        'hospitalReservations' => fn($q) => $q->with(['hospital.account:id,phone_number','hospitalService.service','cancellation','rate'])->orderByDesc('created_at'),
                        'doctorReservations' => fn($q) => $q->with(['doctor.specialization','doctor.account:id,phone_number','doctorService','cancellation','rate'])->orderByDesc('created_at'),
                    ])
                    ->findOrFail($userId);

                $data = [
                    'nurse_reservations'    => $user->nurseReservations,
                    'hospital_reservations' => $this->applyHospitalContactAccess($user->hospitalReservations),
                    'doctor_reservations'   => $this->applyDoctorContactAccess($user->doctorReservations),
                ];
                break;
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Doctor location & phone are only revealed to the user when the
     * reservation is approved. Any other status hides them.
     */
    protected function applyDoctorContactAccess($reservations)
    {
        return $reservations->map(function ($reservation) {
            $doctor = $reservation->doctor;

            if ($doctor) {
                // never expose the underlying account object
                $doctor->makeHidden('account');

                if ($reservation->status === 'approved') {
                    $doctor->setAttribute('phone_number', $doctor->account?->phone_number);
                    $doctor->makeVisible('location');

                    // `location` is GeoJSON ([lng, lat]); the app could not
                    // open the clinic on a map from it. Plain coordinates and
                    // a ready-to-open maps link are now included.
                    foreach ($this->mapFields($doctor->location) as $key => $value) {
                        $doctor->setAttribute($key, $value);
                    }
                } else {
                    $doctor->makeHidden('location');
                }
            }

            return $reservation;
        });
    }

    /**
     * Hospital phone number and location were never part of the patient's
     * reservation payload (the `hospital` relation was loaded without its
     * account and without usable coordinates), so the confirmation screen
     * could show neither. A hospital is a public institution, so its location
     * is always returned; the phone number is returned once the hospital has
     * accepted / confirmed the request.
     */
    protected function applyHospitalContactAccess($reservations)
    {
        return $reservations->map(function ($reservation) {
            $hospital = $reservation->hospital;

            if ($hospital) {
                $hospital->makeHidden('account');

                foreach ($this->mapFields($hospital->location) as $key => $value) {
                    $hospital->setAttribute($key, $value);
                }

                $hospital->setAttribute(
                    'phone_number',
                    in_array($reservation->status, ['accepted', 'confirmed', 'finished'], true)
                        ? $hospital->account?->phone_number
                        : null
                );
            }

            return $reservation;
        });
    }

    /**
     * @return array{latitude: ?float, longitude: ?float, maps_url: ?string}
     */
    protected function mapFields($point): array
    {
        if (!$point || !isset($point->latitude, $point->longitude)
            || ((float) $point->latitude === 0.0 && (float) $point->longitude === 0.0)) {
            return ['latitude' => null, 'longitude' => null, 'maps_url' => null];
        }

        $lat = (float) $point->latitude;
        $lng = (float) $point->longitude;

        return [
            'latitude'  => $lat,
            'longitude' => $lng,
            'maps_url'  => "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}",
        ];
    }





}
