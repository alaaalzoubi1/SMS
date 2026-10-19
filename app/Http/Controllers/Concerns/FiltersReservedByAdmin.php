<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * `reserved_by_admin` filter shared by every endpoint that lists reservations.
 *
 *   reserved_by_admin=true   → only reservations the admin made for a patient
 *   reserved_by_admin=false  → only reservations made by patients / providers
 *   (missing or empty)       → both
 *
 * Accepts true/false, 1/0 and the strings "true"/"false"/"1"/"0"/"yes"/"no",
 * so it works from query strings and form-data alike.
 */
trait FiltersReservedByAdmin
{
    /**
     * @throws ValidationException when the value is not a boolean
     */
    protected function reservedByAdmin(Request $request): ?bool
    {
        if (!$request->filled('reserved_by_admin')) {
            return null;
        }

        $value = filter_var($request->input('reserved_by_admin'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($value === null) {
            throw ValidationException::withMessages([
                'reserved_by_admin' => ['قيمة reserved_by_admin يجب أن تكون true أو false.'],
            ]);
        }

        return $value;
    }

    /**
     * Apply the filter to a reservation query (or relation). `$column` lets
     * joined queries qualify the column.
     */
    protected function applyReservedByAdmin($query, ?bool $value, string $column = 'reserved_by_admin')
    {
        return $value === null ? $query : $query->where($column, $value);
    }
}
