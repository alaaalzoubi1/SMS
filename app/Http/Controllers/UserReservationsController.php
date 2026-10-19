<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersReservedByAdmin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserReservationsController extends Controller
{
    use FiltersReservedByAdmin;

    public function myReservations(Request $request)
    {
        $allowedApiStatuses = ['pending', 'approved', 'cancelled', 'rejected', 'completed'];
        $allowedTypes = ['nurse', 'doctor', 'hospital'];

        $request->validate([
            'status' => ['nullable', Rule::in($allowedApiStatuses)],
            'type'   => ['nullable', Rule::in($allowedTypes)],
            'reserved_by_admin' => ['nullable'],
        ]);

        // Unified status → real values per table (always arrays; [] = the
        // status does not exist for that provider → no rows).
        $statusMap = [
            'pending'   => ['nurse' => ['pending'],   'hospital' => ['pending'],               'doctor' => ['pending']],
            'approved'  => ['nurse' => ['accepted'],  'hospital' => ['confirmed', 'accepted'], 'doctor' => ['approved']],
            'cancelled' => ['nurse' => ['cancelled'], 'hospital' => ['cancelled'],             'doctor' => ['cancelled']],
            'rejected'  => ['nurse' => ['rejected'],  'hospital' => [],                        'doctor' => ['rejected']],
            'completed' => ['nurse' => ['completed'], 'hospital' => ['finished'],              'doctor' => ['completed']],
        ];

        $userId = auth()->user()->user->id;
        $apiStatus = $request->input('status');
        $type = $request->input('type');
        $byAdmin = $this->reservedByAdmin($request);

        // Status + reserved_by_admin, applied the same way whether one type
        // or all types are requested. (Without `type` the status filter used
        // to be ignored completely, and the hospital "approved" value was an
        // array passed to where().)
        $filter = function (string $kind) use ($statusMap, $apiStatus, $byAdmin) {
            return function ($q) use ($kind, $statusMap, $apiStatus, $byAdmin) {
                if ($apiStatus) {
                    $statuses = $statusMap[$apiStatus][$kind];
                    $statuses === [] ? $q->whereRaw('1=0') : $q->whereIn('status', $statuses);
                }
                $this->applyReservedByAdmin($q, $byAdmin);
                $q->orderByDesc('created_at');
            };
        };

        $nurseWith    = ['nurse', 'nurse.account:id', 'nurseService.service', 'services.service', 'cancellation', 'rate'];
        $hospitalWith = ['hospital.account:id,phone_number', 'hospital.hospitalType:id,name_ar,name_en,icon', 'hospitalService.service', 'cancellation', 'rate'];
        $doctorWith   = ['doctor.specialization', 'doctor.account:id,phone_number', 'doctorService', 'cancellation', 'rate'];

        $data = [];

        if ($type === null || $type === 'nurse') {
            $q = \App\Models\NurseReservation::query()->where('user_id', $userId)->with($nurseWith);
            $filter('nurse')($q);
            $data['nurse_reservations'] = $q->get();
        }

        if ($type === null || $type === 'hospital') {
            $q = \App\Models\HospitalServiceReservation::query()->where('user_id', $userId)->with($hospitalWith);
            $filter('hospital')($q);
            $data['hospital_reservations'] = $this->applyHospitalContactAccess($q->get());
        }

        if ($type === null || $type === 'doctor') {
            $q = \App\Models\DoctorReservation::query()->where('user_id', $userId)->with($doctorWith);
            $filter('doctor')($q);
            $data['doctor_reservations'] = $this->applyDoctorContactAccess($q->get());
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

                // Countdown to go to the hospital and confirm (تثبيت) an
                // accepted reservation. Computed from the already-loaded
                // hospital — no extra query per reservation.
                foreach ($reservation->confirmationCountdown(
                    $hospital->reservation_confirmation_deadline !== null
                        ? (float) $hospital->reservation_confirmation_deadline
                        : null
                ) as $key => $value) {
                    $reservation->setAttribute($key, $value);
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
