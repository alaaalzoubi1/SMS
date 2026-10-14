<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\FiltersProviderAccounts;
use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Hospital;
use App\Models\Nurse;
use App\Services\ProviderStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET api/admin/reports/providers
 *
 * Detailed per-account report for doctors, nurses or hospitals, filtered by
 * province (and every other admin account filter), used to follow up and
 * claim the platform's dues in an organised way.
 *
 * Query:
 *   type        – doctor | nurse | hospital            (required)
 *   range/from/to – reservation period (default: all), see ProviderStatisticsService
 *   province_id, is_suspended, is_approved, subscription, created_from,
 *   created_to, trashed, sort                          – see FiltersProviderAccounts
 *   format      – json (default) | csv  (CSV opens directly in Excel, Arabic OK)
 *   per_page    – json only, 1..200 (default 50)
 *
 * NOTE: dues are calculated by the dedicated endpoint GET api/admin/dues
 * (AdminDuesController); `dues` stays null in this account report.
 */
class AdminReportController extends Controller
{
    use FiltersProviderAccounts;

    public function __construct(protected ProviderStatisticsService $stats)
    {
    }

    public function providers(Request $request): JsonResponse|StreamedResponse
    {
        $request->validate(array_merge(
            $this->accountFilterRules(),
            ProviderStatisticsService::rules(),
            [
                'type'     => 'required|in:doctor,nurse,hospital',
                'format'   => 'nullable|in:json,csv',
                'per_page' => 'nullable|integer|min:1|max:200',
            ]
        ), [
            'type.required' => 'يجب تحديد نوع الحسابات: doctor أو nurse أو hospital.',
        ]);

        [$range, $from, $to] = $this->stats->resolveRange($request, 'all');

        $type = $request->input('type');

        $query = match ($type) {
            'doctor'   => Doctor::query()->with(array_merge($this->withAccount(), ['province:id,name_ar,name_en', 'specialization:id,name_ar'])),
            'nurse'    => Nurse::query()->with(array_merge($this->withAccount(), ['province:id,name_ar,name_en'])),
            'hospital' => Hospital::query()->with(array_merge($this->withAccount(), ['province:id,name_ar,name_en'])),
        };

        $this->applyAccountFilters($query, $request);

        $toRow = function ($provider) use ($type, $from, $to) {
            $stats = match ($type) {
                'doctor'   => $this->stats->doctor($provider, $from, $to),
                'nurse'    => $this->stats->nurse($provider, $from, $to),
                'hospital' => $this->stats->hospital($provider, $from, $to),
            };

            $data = $stats['data'];

            return [
                'id'                      => $provider->id,
                'full_name'               => $provider->full_name,
                'phone_number'            => $provider->account?->phone_number,
                'email'                   => $provider->account?->email,
                'province'                => $provider->province?->name_ar,
                'specialization'          => $type === 'doctor' ? $provider->specialization?->name_ar : null,
                'account_created_at'      => $provider->created_at?->toDateString(),
                'is_suspended'            => (bool) $provider->account?->is_suspended,
                'is_deleted'              => method_exists($provider, 'trashed') && $provider->trashed(),
                'subscription_expires_at' => $provider->account?->subscription_expires_at?->toDateTimeString(),
                'total_reservations'      => $data['total_reservations'] ?? 0,
                'completed_reservations'  => $data['completed_count'] ?? ($data['finished_count'] ?? 0),
                'cancelled_reservations'  => $data['cancelled_count'] ?? 0,
                'total_revenue'           => $data['total_revenue'] ?? 0,
                'dues'                    => null, // see GET api/admin/dues
            ];
        };

        if ($request->input('format') === 'csv') {
            return $this->csv($query->get()->map($toRow), $type);
        }

        $page = $query->paginate($request->input('per_page', 50));
        $rows = $page->getCollection()->map($toRow);

        return response()->json([
            'type'  => $type,
            'range' => $range,
            'from'  => $from?->toDateTimeString(),
            'to'    => $to?->toDateTimeString(),
            'totals' => [
                'accounts'               => $page->total(),
                'total_reservations'     => $rows->sum('total_reservations'),
                'completed_reservations' => $rows->sum('completed_reservations'),
                'total_revenue'          => round((float) $rows->sum('total_revenue'), 2),
                'note'                   => 'المجاميع تخص الصفحة الحالية. استخدم format=csv لتقرير كامل.',
            ],
            'dues_rule_defined' => false,
            'data' => $rows,
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    private function csv($rows, string $type): StreamedResponse
    {
        $headers = [
            'id' => 'الرقم', 'full_name' => 'الاسم', 'phone_number' => 'الهاتف', 'email' => 'البريد',
            'province' => 'المحافظة', 'specialization' => 'الاختصاص', 'account_created_at' => 'تاريخ إنشاء الحساب',
            'is_suspended' => 'موقوف', 'is_deleted' => 'محذوف', 'subscription_expires_at' => 'انتهاء الاشتراك',
            'total_reservations' => 'إجمالي الحجوزات', 'completed_reservations' => 'الحجوزات المكتملة',
            'cancelled_reservations' => 'الحجوزات الملغاة', 'total_revenue' => 'إجمالي الإيرادات', 'dues' => 'المستحقات',
        ];

        $filename = "report_{$type}_" . now()->format('Y_m_d_His') . '.csv';

        return response()->streamDownload(function () use ($rows, $headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Arabic correctly
            fputcsv($out, array_values($headers));
            foreach ($rows as $row) {
                $line = [];
                foreach (array_keys($headers) as $key) {
                    $value = $row[$key] ?? '';
                    $line[] = is_bool($value) ? ($value ? 'نعم' : 'لا') : $value;
                }
                fputcsv($out, $line);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
