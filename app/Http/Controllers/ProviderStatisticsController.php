<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\Hospital;
use App\Models\Nurse;
use App\Services\ProviderStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Statistics screens.
 *
 * Doctor and hospital apps had lost their statistics endpoint (only the nurse
 * one remained), and the admin had no per-account statistics to audit
 * accounts. Query params everywhere: range = today|week|month|year|custom|all,
 * plus from / to when range = custom.
 */
class ProviderStatisticsController extends Controller
{
    public function __construct(protected ProviderStatisticsService $stats)
    {
    }

    /* ─────────────── provider apps ─────────────── */

    /** GET api/doctor/statistics */
    public function doctor(Request $request): JsonResponse
    {
        $request->validate(ProviderStatisticsService::rules());

        $doctor = Doctor::where('account_id', auth()->id())->firstOrFail();
        [$range, $from, $to] = $this->stats->resolveRange($request, 'today');

        return response()->json(
            $this->stats->envelope($range, $from, $to, $this->stats->doctor($doctor, $from, $to))
        );
    }

    /** GET api/hospital/statistics */
    public function hospital(Request $request): JsonResponse
    {
        $request->validate(ProviderStatisticsService::rules());

        $hospital = Hospital::where('account_id', auth()->id())->firstOrFail();
        [$range, $from, $to] = $this->stats->resolveRange($request, 'today');

        return response()->json(
            $this->stats->envelope($range, $from, $to, $this->stats->hospital($hospital, $from, $to))
        );
    }

    /* ─────────────── admin dashboard ─────────────── */

    /** GET api/admin/doctor/{id}/statistics */
    public function adminDoctor(Request $request, int $id): JsonResponse
    {
        $request->validate(ProviderStatisticsService::rules());

        $doctor = Doctor::withTrashed()->withSuspended()
            ->with(['account:id,email,phone_number,is_suspended,is_approved,subscription_expires_at,created_at', 'province:id,name_ar,name_en', 'specialization:id,name_ar,name_en'])
            ->findOrFail($id);

        [$range, $from, $to] = $this->stats->resolveRange($request, 'all');

        return response()->json(array_merge(
            ['doctor' => $doctor],
            $this->stats->envelope($range, $from, $to, $this->stats->doctor($doctor, $from, $to))
        ));
    }

    /** GET api/admin/nurse/{id}/statistics */
    public function adminNurse(Request $request, int $id): JsonResponse
    {
        $request->validate(ProviderStatisticsService::rules());

        $nurse = Nurse::withTrashed()->withSuspended()
            ->with(['account:id,email,phone_number,is_suspended,is_approved,subscription_expires_at,created_at', 'province:id,name_ar,name_en'])
            ->findOrFail($id);

        [$range, $from, $to] = $this->stats->resolveRange($request, 'all');

        return response()->json(array_merge(
            ['nurse' => $nurse],
            $this->stats->envelope($range, $from, $to, $this->stats->nurse($nurse, $from, $to))
        ));
    }

    /** GET api/admin/hospital/{id}/statistics */
    public function adminHospital(Request $request, int $id): JsonResponse
    {
        $request->validate(ProviderStatisticsService::rules());

        $hospital = Hospital::withTrashed()->withSuspended()
            ->with(['account:id,email,phone_number,is_suspended,is_approved,subscription_expires_at,created_at', 'province:id,name_ar,name_en'])
            ->findOrFail($id);

        [$range, $from, $to] = $this->stats->resolveRange($request, 'all');

        return response()->json(array_merge(
            ['hospital' => $hospital],
            $this->stats->envelope($range, $from, $to, $this->stats->hospital($hospital, $from, $to))
        ));
    }
}
