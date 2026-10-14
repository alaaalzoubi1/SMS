<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Extra admin filters shared by the doctor / nurse / hospital (and user)
 * account lists, so the admin can search precisely and pull the detailed
 * report used to claim the platform's dues.
 *
 * Query params (all optional):
 *   province_id            – المحافظة
 *   is_suspended           – 1/0 (true/false)
 *   is_approved            – approved | pending | rejected
 *   subscription           – active | expired | lifetime | expiring_soon
 *   expiring_within_days   – window for expiring_soon (default 7)
 *   created_from/created_to– account creation date range
 *   trashed                – with | only  (include / only deleted accounts)
 *   sort                   – newest | oldest | name
 */
trait FiltersProviderAccounts
{
    /**
     * Columns of the account that every admin list must expose. The lists
     * used to select only id/email/phone, so the dashboard could not show
     * (or toggle) the suspension state nor the subscription expiry.
     */
    protected function withAccount(): array
    {
        return [
            'account' => fn ($q) => $q->withTrashed()->select([
                'id', 'email', 'phone_number', 'is_suspended', 'is_approved',
                'subscription_expires_at', 'created_at', 'deleted_at',
            ]),
        ];
    }

    protected function accountFilterRules(): array
    {
        return [
            'province_id'          => 'nullable|integer|exists:provinces,id',
            'is_suspended'         => 'nullable|in:0,1,true,false',
            'is_approved'          => 'nullable|in:approved,pending,rejected',
            'subscription'         => 'nullable|in:active,expired,lifetime,expiring_soon',
            'expiring_within_days' => 'nullable|integer|min:1|max:365',
            'created_from'         => 'nullable|date',
            'created_to'           => 'nullable|date|after_or_equal:created_from',
            'trashed'              => 'nullable|in:with,only',
            'sort'                 => 'nullable|in:newest,oldest,name',
            'per_page'             => 'nullable|integer|min:1|max:100',
        ];
    }

    protected function applyAccountFilters(Builder $query, Request $request, bool $hasProvince = true): Builder
    {
        $table = $query->getModel()->getTable();

        if ($request->input('trashed') === 'with') {
            $query->withTrashed();
        } elseif ($request->input('trashed') === 'only') {
            $query->onlyTrashed();
        }

        if ($hasProvince && $request->filled('province_id')) {
            $query->where("{$table}.province_id", $request->province_id);
        }

        if ($request->filled('created_from')) {
            $query->whereDate("{$table}.created_at", '>=', $request->created_from);
        }
        if ($request->filled('created_to')) {
            $query->whereDate("{$table}.created_at", '<=', $request->created_to);
        }

        $accountFilter = function ($q) use ($request) {
            $q->withTrashed();

            if ($request->filled('is_suspended')) {
                $q->where('is_suspended', filter_var($request->is_suspended, FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->filled('is_approved')) {
                $q->where('is_approved', $request->is_approved);
            }

            switch ($request->input('subscription')) {
                case 'lifetime':
                    $q->whereNull('subscription_expires_at');
                    break;
                case 'active':
                    $q->where(fn ($s) => $s->whereNull('subscription_expires_at')
                        ->orWhere('subscription_expires_at', '>=', now()));
                    break;
                case 'expired':
                    $q->whereNotNull('subscription_expires_at')
                        ->where('subscription_expires_at', '<', now());
                    break;
                case 'expiring_soon':
                    $q->whereBetween('subscription_expires_at', [
                        now(),
                        now()->addDays((int) $request->input('expiring_within_days', 7)),
                    ]);
                    break;
            }
        };

        if ($request->filled('is_suspended') || $request->filled('is_approved') || $request->filled('subscription')) {
            $query->whereHas('account', $accountFilter);
        }

        match ($request->input('sort', 'newest')) {
            'oldest' => $query->orderBy("{$table}.created_at"),
            'name'   => $query->orderBy("{$table}.full_name"),
            default  => $query->orderByDesc("{$table}.created_at"),
        };

        return $query;
    }
}
