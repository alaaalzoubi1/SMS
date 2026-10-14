<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDuesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET api/admin/dues — platform dues (مستحقات الإدارة).
 * See ADMIN_DUES_FRONTEND.md for the full contract.
 */
class AdminDuesController extends Controller
{
    public function __construct(protected AdminDuesService $dues)
    {
    }

    public function index(Request $request): JsonResponse|StreamedResponse
    {
        // Accept "true"/"false"/"1"/"0" for reserved_by_admin; empty = both.
        if ($request->filled('reserved_by_admin')) {
            $request->merge([
                'reserved_by_admin' => filter_var($request->input('reserved_by_admin'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }

        $validated = $request->validate([
            'from'              => 'required|date',
            'to'                => 'required|date|after_or_equal:from',
            'province_id'       => 'nullable|integer|exists:provinces,id',
            'providers'         => 'nullable|array',
            'providers.*'       => ['string', Rule::in(AdminDuesService::PROVIDERS)],
            'statuses'          => 'nullable|array',
            'statuses.*'        => ['string', Rule::in(AdminDuesService::STATUSES)],
            'due_type'          => 'required|in:fixed,percentage',
            'amount'            => [
                'required', 'numeric', 'min:0',
                // A percentage cannot exceed 100%.
                Rule::when($request->input('due_type') === 'percentage', ['max:100']),
            ],
            'reserved_by_admin' => 'nullable|boolean',
            'sort'              => 'nullable|in:total_due,total_cost,total_count,provider_name',
            'direction'         => 'nullable|in:asc,desc',
            'per_page'          => 'nullable|integer|min:1|max:500',
            'page'              => 'nullable|integer|min:1',
            'format'            => 'nullable|in:json,csv',
        ], [
            'from.required'      => 'تاريخ البداية مطلوب.',
            'to.required'        => 'تاريخ النهاية مطلوب.',
            'to.after_or_equal'  => 'تاريخ النهاية يجب أن يكون بعد أو يساوي تاريخ البداية.',
            'due_type.required'  => 'نوع المستحقات مطلوب (fixed أو percentage).',
            'due_type.in'        => 'نوع المستحقات يجب أن يكون fixed أو percentage.',
            'amount.required'    => 'قيمة المستحقات مطلوبة.',
            'amount.max'         => 'النسبة المئوية يجب ألا تتجاوز 100.',
            'providers.*.in'     => 'نوع مقدم الخدمة يجب أن يكون doctor أو nurse أو hospital.',
            'statuses.*.in'      => 'الحالة يجب أن تكون: pending, approved, rejected, cancelled, completed.',
        ]);

        $filters = [
            'from'              => $validated['from'],
            'to'                => $validated['to'],
            'province_id'       => isset($validated['province_id']) ? (int) $validated['province_id'] : null,
            'providers'         => array_values(array_unique($validated['providers'] ?? AdminDuesService::PROVIDERS)) ?: AdminDuesService::PROVIDERS,
            'statuses'          => array_values(array_unique($validated['statuses'] ?? AdminDuesService::STATUSES)) ?: AdminDuesService::STATUSES,
            'due_type'          => $validated['due_type'],
            'amount'            => (float) $validated['amount'],
            'reserved_by_admin' => array_key_exists('reserved_by_admin', $validated) && $validated['reserved_by_admin'] !== null
                ? (bool) $validated['reserved_by_admin']
                : null,
        ];

        $sort = $validated['sort'] ?? 'total_due';
        $desc = ($validated['direction'] ?? ($sort === 'provider_name' ? 'asc' : 'desc')) === 'desc';

        $rows = $this->dues->rows($filters)
            ->sortBy($sort, $sort === 'provider_name' ? SORT_NATURAL | SORT_FLAG_CASE : SORT_REGULAR, $desc)
            ->values();

        // Grand totals are over ALL providers (not just the current page).
        $totals = [
            'total_count' => (int) $rows->sum('total_count'),
            'total_cost'  => round((float) $rows->sum('total_cost'), 2),
            'total_due'   => round((float) $rows->sum('total_due'), 2),
            'providers_count' => $rows->count(),
        ];

        $byType = [];
        foreach ($filters['providers'] as $type) {
            $typeRows = $rows->where('provider_type', $type);
            $byType[$type] = [
                'total_count' => (int) $typeRows->sum('total_count'),
                'total_cost'  => round((float) $typeRows->sum('total_cost'), 2),
                'total_due'   => round((float) $typeRows->sum('total_due'), 2),
                'providers_count' => $typeRows->count(),
            ];
        }

        if (($validated['format'] ?? 'json') === 'csv') {
            return $this->csv($rows, $totals);
        }

        $perPage = (int) ($validated['per_page'] ?? 50);
        $page    = (int) ($validated['page'] ?? 1);
        $lastPage = max(1, (int) ceil($rows->count() / $perPage));

        return response()->json([
            'filters' => $filters,
            'totals'  => $totals,
            'by_type' => $byType,
            'data'    => $rows->forPage($page, $perPage)->values(),
            'pagination' => [
                'current_page' => $page,
                'last_page'    => $lastPage,
                'per_page'     => $perPage,
                'total'        => $rows->count(),
            ],
        ]);
    }

    private function csv($rows, array $totals): StreamedResponse
    {
        $types = ['doctor' => 'طبيب', 'nurse' => 'ممرض', 'hospital' => 'مشفى'];

        return response()->streamDownload(function () use ($rows, $totals, $types) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM → Arabic opens correctly in Excel
            fputcsv($out, ['النوع', 'الرقم', 'الاسم', 'المحافظة', 'عدد الحجوزات', 'إجمالي التكلفة', 'إجمالي المستحقات']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $types[$r['provider_type']] ?? $r['provider_type'],
                    $r['provider_id'],
                    $r['provider_name'],
                    $r['province_name'],
                    $r['total_count'],
                    $r['total_cost'],
                    $r['total_due'],
                ]);
            }
            fputcsv($out, ['المجموع', '', '', '', $totals['total_count'], $totals['total_cost'], $totals['total_due']]);
            fclose($out);
        }, 'dues_' . now()->format('Y_m_d_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
