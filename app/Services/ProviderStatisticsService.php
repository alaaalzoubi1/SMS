<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\DoctorReservation;
use App\Models\Hospital;
use App\Models\HospitalServiceReservation;
use App\Models\Nurse;
use App\Models\NurseReservation;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Reservation / revenue statistics for doctors, nurses and hospitals.
 *
 * Used by:
 *  - the provider apps   (GET api/doctor/statistics, GET api/hospital/statistics,
 *                          GET api/nurse/statistics already existed)
 *  - the admin dashboard (GET api/admin/{doctor|nurse|hospital}/{id}/statistics
 *                          and the providers report).
 *
 * The period filters on the reservation's `created_at` (when the request was
 * received), exactly like the original nurse statistics endpoint.
 */
class ProviderStatisticsService
{
    public const RANGES = ['today', 'week', 'month', 'year', 'custom', 'all'];

    /**
     * Validation rules shared by every statistics endpoint.
     */
    public static function rules(): array
    {
        return [
            'range' => 'nullable|in:' . implode(',', self::RANGES),
            'from'  => 'required_if:range,custom|nullable|date',
            'to'    => 'required_if:range,custom|nullable|date|after_or_equal:from',
        ];
    }

    /**
     * @return array{0:string,1:?Carbon,2:?Carbon}
     */
    public function resolveRange(Request $request, string $default = 'today'): array
    {
        $range = $request->input('range', $default);

        return match ($range) {
            'today'  => [$range, now()->startOfDay(), now()->endOfDay()],
            'week'   => [$range, now()->startOfWeek(), now()->endOfWeek()],
            'month'  => [$range, now()->startOfMonth(), now()->endOfMonth()],
            'year'   => [$range, now()->startOfYear(), now()->endOfYear()],
            'custom' => [
                $range,
                Carbon::parse($request->input('from'))->startOfDay(),
                Carbon::parse($request->input('to'))->endOfDay(),
            ],
            default  => ['all', null, null],
        };
    }

    public function doctor(Doctor $doctor, ?Carbon $from, ?Carbon $to): array
    {
        $base = DoctorReservation::query()
            ->where('doctor_reservations.doctor_id', $doctor->id)
            ->when($from && $to, fn ($q) => $q->whereBetween('doctor_reservations.created_at', [$from, $to]));

        $counts = (clone $base)
            ->selectRaw("
                COUNT(*) as total_reservations,
                COALESCE(SUM(status = 'pending'), 0)   as pending_count,
                COALESCE(SUM(status = 'approved'), 0)  as approved_count,
                COALESCE(SUM(status = 'completed'), 0) as completed_count,
                COALESCE(SUM(status = 'rejected'), 0)  as rejected_count,
                COALESCE(SUM(status = 'cancelled'), 0) as cancelled_count,
                COALESCE(SUM(reserved_by_admin = 1), 0) as admin_reservations_count
            ")
            ->first();

        $revenue = (clone $base)
            ->where('doctor_reservations.status', 'completed')
            ->join('doctor_services', 'doctor_services.id', '=', 'doctor_reservations.doctor_service_id')
            ->sum('doctor_services.price');

        return [
            'data' => $this->normalizeCounts($counts) + [
                'total_revenue' => round((float) $revenue, 2),
            ],
            'avg_rate'      => (float) ($doctor->avg_rating ?? 0),
            'ratings_count' => (int) ($doctor->ratings_count ?? 0),
            'services_count'      => $doctor->services()->count(),
            'working_days_count'  => $doctor->doctorWorkSchedule()->count(),
        ];
    }

    public function nurse(Nurse $nurse, ?Carbon $from, ?Carbon $to): array
    {
        $base = NurseReservation::query()
            ->where('nurse_id', $nurse->id)
            ->when($from && $to, fn ($q) => $q->whereBetween('created_at', [$from, $to]));

        $counts = (clone $base)
            ->selectRaw("
                COUNT(*) as total_reservations,
                COALESCE(SUM(status = 'pending'), 0)   as pending_count,
                COALESCE(SUM(status = 'accepted'), 0)  as accepted_count,
                COALESCE(SUM(status = 'completed'), 0) as completed_count,
                COALESCE(SUM(status = 'rejected'), 0)  as rejected_count,
                COALESCE(SUM(status = 'cancelled'), 0) as cancelled_count,
                COALESCE(SUM(reserved_by_admin = 1), 0) as admin_reservations_count,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN price ELSE 0 END), 0) as total_revenue
            ")
            ->first();

        $data = $this->normalizeCounts($counts);
        $data['total_revenue'] = round((float) ($counts->total_revenue ?? 0), 2);

        return [
            'data'          => $data,
            'avg_rate'      => (float) ($nurse->avg_rating ?? 0),
            'ratings_count' => (int) ($nurse->ratings_count ?? 0),
            'services_count' => $nurse->services()->count(),
        ];
    }

    public function hospital(Hospital $hospital, ?Carbon $from, ?Carbon $to): array
    {
        $base = HospitalServiceReservation::query()
            ->where('hospital_id', $hospital->id)
            ->when($from && $to, fn ($q) => $q->whereBetween('created_at', [$from, $to]));

        $counts = (clone $base)
            ->selectRaw("
                COUNT(*) as total_reservations,
                COALESCE(SUM(status = 'pending'), 0)   as pending_count,
                COALESCE(SUM(status = 'accepted'), 0)  as accepted_count,
                COALESCE(SUM(status = 'confirmed'), 0) as confirmed_count,
                COALESCE(SUM(status = 'finished'), 0)  as finished_count,
                COALESCE(SUM(status = 'cancelled'), 0) as cancelled_count,
                COALESCE(SUM(reserved_by_admin = 1), 0) as admin_reservations_count
            ")
            ->first();

        // final_price is computed (unit_price × inclusive days), so it is
        // summed in PHP rather than in SQL.
        $priced = (clone $base)
            ->whereIn('status', ['confirmed', 'finished'])
            ->get(['id', 'unit_price', 'start_date', 'end_date', 'status']);

        $finishedRevenue = $priced->where('status', 'finished')->sum(fn ($r) => $r->final_price);
        $runningRevenue  = $priced->where('status', 'confirmed')->sum(fn ($r) => $r->final_price);

        $services = $hospital->services()->get(['id', 'capacity']);

        return [
            'data' => $this->normalizeCounts($counts) + [
                'total_revenue'   => round((float) $finishedRevenue, 2),
                'running_revenue' => round((float) $runningRevenue, 2),
            ],
            'avg_rate'         => (float) ($hospital->avg_rating ?? 0),
            'ratings_count'    => (int) ($hospital->ratings_count ?? 0),
            'services_count'   => $services->count(),
            'available_beds'   => (int) $services->sum('capacity'),
        ];
    }

    /**
     * Wrap a stats block with the period it covers.
     */
    public function envelope(string $range, ?Carbon $from, ?Carbon $to, array $stats): array
    {
        return array_merge([
            'range' => $range,
            'from'  => $from?->toDateTimeString(),
            'to'    => $to?->toDateTimeString(),
        ], $stats);
    }

    private function normalizeCounts($row): array
    {
        $out = [];
        foreach ((array) ($row?->getAttributes() ?? []) as $key => $value) {
            if ($key === 'total_revenue') {
                continue;
            }
            $out[$key] = (int) $value;
        }

        return $out;
    }
}
