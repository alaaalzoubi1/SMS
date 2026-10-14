<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Platform dues (مستحقات الإدارة) per provider.
 *
 * Performance notes — this is the hot path of the dues screen:
 *  - ONE aggregated `GROUP BY provider` SQL query per provider type
 *    (max 3 queries for the whole report), whatever the number of providers
 *    or reservations. No per-row queries, so no N+1.
 *  - Uses the query builder, not Eloquent: no model hydration, and none of
 *    the model `$appends` (NurseReservation appends services_count /
 *    service_names / … which would each run an extra query per row).
 *  - Provider name / province come from a JOIN in the same query.
 *  - The period filter is on `created_at`, backed by the
 *    (created_at, status) indexes added in
 *    2026_10_13_000002_add_dues_indexes_to_reservation_tables.
 *  - The due is computed from the aggregated totals (count × amount, or
 *    cost × % / 100) — cheap arithmetic on a handful of grouped rows.
 */
class AdminDuesService
{
    public const PROVIDERS = ['doctor', 'nurse', 'hospital'];

    /** Unified statuses the dashboard sends. */
    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled', 'completed'];

    /**
     * Unified status → real enum values per table.
     * An empty array means "this status does not exist for this provider".
     */
    private const STATUS_MAP = [
        'doctor' => [
            'pending'   => ['pending'],
            'approved'  => ['approved'],
            'rejected'  => ['rejected'],
            'cancelled' => ['cancelled'],
            'completed' => ['completed'],
        ],
        'nurse' => [
            'pending'   => ['pending'],
            'approved'  => ['accepted'],
            'rejected'  => ['rejected'],
            'cancelled' => ['cancelled'],
            'completed' => ['completed'],
        ],
        'hospital' => [
            'pending'   => ['pending'],
            'approved'  => ['accepted', 'confirmed'],
            'rejected'  => [],
            'cancelled' => ['cancelled'],
            'completed' => ['finished'],
        ],
    ];

    /**
     * @param array{
     *   from: string, to: string, province_id: ?int, providers: string[],
     *   statuses: string[], due_type: string, amount: float, reserved_by_admin: ?bool
     * } $f
     * @return Collection<int, array> one row per provider
     */
    public function rows(array $f): Collection
    {
        $from = Carbon::parse($f['from'])->startOfDay();
        $to   = Carbon::parse($f['to'])->endOfDay();

        $rows = collect();

        foreach ($f['providers'] as $type) {
            $statuses = $this->resolveStatuses($type, $f['statuses']);

            // The selected statuses don't exist for this provider type
            // (e.g. only "rejected" for hospitals) → nothing to query.
            if ($statuses === []) {
                continue;
            }

            $query = match ($type) {
                'doctor'   => $this->doctorQuery(),
                'nurse'    => $this->nurseQuery(),
                'hospital' => $this->hospitalQuery(),
            };

            $query->whereNull('r.deleted_at')
                ->whereBetween('r.created_at', [$from, $to])
                ->whereIn('r.status', $statuses)
                ->when($f['province_id'] !== null, fn (Builder $q) => $q->where('p.province_id', $f['province_id']))
                ->when($f['reserved_by_admin'] !== null, fn (Builder $q) => $q->where('r.reserved_by_admin', $f['reserved_by_admin']));

            foreach ($query->get() as $row) {
                $count = (int) $row->total_count;
                $cost  = round((float) $row->total_cost, 2);

                $rows->push([
                    'provider_type' => $type,
                    'provider_id'   => (int) $row->provider_id,
                    'provider_name' => $row->provider_name,
                    'province_id'   => $row->province_id !== null ? (int) $row->province_id : null,
                    'province_name' => $row->province_name,
                    'is_deleted'    => $row->provider_deleted_at !== null,
                    'total_count'   => $count,
                    'total_cost'    => $cost,
                    'total_due'     => $this->due($f['due_type'], (float) $f['amount'], $count, $cost),
                ]);
            }
        }

        return $rows;
    }

    public function due(string $dueType, float $amount, int $count, float $cost): float
    {
        return $dueType === 'fixed'
            ? round($count * $amount, 2)          // amount per reservation
            : round($cost * $amount / 100, 2);    // amount is a percentage
    }

    /**
     * @return string[] real enum values for this provider; [] = none apply
     */
    private function resolveStatuses(string $type, array $unified): array
    {
        $values = [];
        foreach ($unified as $status) {
            array_push($values, ...(self::STATUS_MAP[$type][$status] ?? []));
        }

        return array_values(array_unique($values));
    }

    /**
     * Common select. `$costExpression` is the per-reservation cost in SQL.
     */
    private function base(string $reservationTable, string $providerTable, string $fk, string $costExpression): Builder
    {
        return DB::table("{$reservationTable} as r")
            ->join("{$providerTable} as p", 'p.id', '=', "r.{$fk}")
            ->leftJoin('provinces as pr', 'pr.id', '=', 'p.province_id')
            ->groupBy('p.id', 'p.full_name', 'p.province_id', 'pr.name_ar', 'p.deleted_at')
            ->selectRaw("
                p.id          as provider_id,
                p.full_name   as provider_name,
                p.province_id as province_id,
                pr.name_ar    as province_name,
                p.deleted_at  as provider_deleted_at,
                COUNT(r.id)   as total_count,
                COALESCE(SUM({$costExpression}), 0) as total_cost
            ");
    }

    /** Doctor cost = price of the booked doctor service. */
    private function doctorQuery(): Builder
    {
        return $this->base('doctor_reservations', 'doctors', 'doctor_id', 'COALESCE(ds.price, 0)')
            ->leftJoin('doctor_services as ds', 'ds.id', '=', 'r.doctor_service_id');
    }

    /** Nurse cost = reservation price (already the sum of all selected services). */
    private function nurseQuery(): Builder
    {
        return $this->base('nurse_reservations', 'nurses', 'nurse_id', 'COALESCE(r.price, 0)');
    }

    /**
     * Hospital cost = unit_price × days, with the same rule as
     * HospitalServiceReservation::getDaysAttribute(): inclusive days from
     * start_date to end_date (or today while the stay is running), at least
     * one day; 0 when the stay has not started (no start_date yet).
     */
    private function hospitalQuery(): Builder
    {
        $days = DB::connection()->getDriverName() === 'sqlite'
            ? "(MAX(CAST(julianday(date(COALESCE(r.end_date, 'now'))) - julianday(date(r.start_date)) AS INTEGER), 0) + 1)"
            : '(GREATEST(DATEDIFF(DATE(COALESCE(r.end_date, NOW())), DATE(r.start_date)), 0) + 1)';

        $cost = "CASE WHEN r.start_date IS NULL THEN 0 ELSE COALESCE(r.unit_price, 0) * {$days} END";

        return $this->base('hospital_service_reservations', 'hospitals', 'hospital_id', $cost);
    }
}
