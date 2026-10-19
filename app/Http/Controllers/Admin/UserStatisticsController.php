<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\FiltersReservedByAdmin;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserStatisticsController extends Controller
{
    use FiltersReservedByAdmin;

    /**
     * GET api/admin/user/all
     *
     * Now filterable and exposes the account's suspension state, so users can
     * be restricted / deleted from the dashboard.
     * Query: full_name, email, phone_number, gender, is_suspended,
     *        has_account (registered vs. created manually), created_from,
     *        created_to, trashed (with|only), per_page.
     */
    public function users(Request $request): JsonResponse
    {
        $request->validate([
            'full_name'    => 'nullable|string|max:255',
            'email'        => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:25',
            'gender'       => 'nullable|in:male,female',
            'is_suspended' => 'nullable|in:0,1,true,false',
            'has_account'  => 'nullable|in:0,1,true,false',
            'created_from' => 'nullable|date',
            'created_to'   => 'nullable|date|after_or_equal:created_from',
            'trashed'      => 'nullable|in:with,only',
            'per_page'     => 'nullable|integer|min:1|max:100',
        ]);

        $query = User::query()
            ->with(['account' => fn ($q) => $q->withTrashed()->select([
                'id', 'email', 'phone_number', 'is_suspended', 'is_approved', 'created_at', 'deleted_at',
            ])])
            ->when($request->input('trashed') === 'with', fn ($q) => $q->withTrashed())
            ->when($request->input('trashed') === 'only', fn ($q) => $q->onlyTrashed())
            ->when($request->filled('full_name'), fn ($q) => $q->where('full_name', 'like', '%' . $request->full_name . '%'))
            ->when($request->filled('gender'), fn ($q) => $q->where('gender', $request->gender))
            ->when($request->filled('email'), fn ($q) => $q->whereHas('account', fn ($a) => $a->withTrashed()->where('email', 'like', '%' . $request->email . '%')))
            ->when($request->filled('phone_number'), function ($q) use ($request) {
                $phone = '%' . $request->phone_number . '%';
                $q->where(fn ($w) => $w->where('phone', 'like', $phone)
                    ->orWhereHas('account', fn ($a) => $a->withTrashed()->where('phone_number', 'like', $phone)));
            })
            ->when($request->filled('is_suspended'), fn ($q) => $q->whereHas('account', fn ($a) => $a->withTrashed()
                ->where('is_suspended', filter_var($request->is_suspended, FILTER_VALIDATE_BOOLEAN))))
            ->when($request->filled('has_account'), fn ($q) => filter_var($request->has_account, FILTER_VALIDATE_BOOLEAN)
                ? $q->whereNotNull('account_id')
                : $q->whereNull('account_id'))
            ->when($request->filled('created_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->created_from))
            ->when($request->filled('created_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->created_to))
            ->orderByDesc('created_at');

        return response()->json([
            'users' => $query->paginate($request->input('per_page', 10))
        ]);
    }

    public function userReservations(Request $request ,$id): JsonResponse

    {
        // حالات موحّدة تتعامل بها الواجهة: pending, approved, cancelled, rejected, completed
        $allowedApiStatuses = ['pending', 'approved', 'cancelled', 'rejected', 'completed'];

        $request->validate([
            'status' => ['nullable', Rule::in($allowedApiStatuses)],
            'reserved_by_admin' => ['nullable'],
        ]);
        $byAdmin = $this->reservedByAdmin($request);

        // خريطة التحويل: من حالة API الموحّدة -> إلى قيم enum في كل جدول
        // ([] = الحالة غير موجودة في هذا الجدول → لا نتائج).
        // Same map as the user app: nurse `cancelled` and hospital
        // `completed` (finished) / `approved` (accepted) used to return nothing.
        $statusMap = [
            'pending'   => ['nurse' => ['pending'],   'hospital' => ['pending'],               'doctor' => ['pending']],
            'approved'  => ['nurse' => ['accepted'],  'hospital' => ['confirmed', 'accepted'], 'doctor' => ['approved']],
            'cancelled' => ['nurse' => ['cancelled'], 'hospital' => ['cancelled'],             'doctor' => ['cancelled']],
            'rejected'  => ['nurse' => ['rejected'],  'hospital' => [],                        'doctor' => ['rejected']],
            'completed' => ['nurse' => ['completed'], 'hospital' => ['finished'],              'doctor' => ['completed']],
        ];

        $apiStatus = $request->filled('status') ? $request->string('status')->toString() : null;

        $scope = function (string $kind) use ($statusMap, $apiStatus, $byAdmin) {
            return function ($q) use ($kind, $statusMap, $apiStatus, $byAdmin) {
                if ($apiStatus !== null) {
                    $statuses = $statusMap[$apiStatus][$kind];
                    $statuses === [] ? $q->whereRaw('1=0') : $q->whereIn('status', $statuses);
                }
                $this->applyReservedByAdmin($q, $byAdmin);
            };
        };

        $with = [
            'nurseReservations'    => $scope('nurse'),
            'hospitalReservations' => $scope('hospital'),
            'doctorReservations'   => $scope('doctor'),
        ];

        $user = User::query()
            ->with(array_merge($with, ['account:id,email,phone_number']))
            ->where('id', $id)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'user'                  => $user,
                'nurse_reservations'    => $user->nurseReservations,
                'hospital_reservations' => $user->hospitalReservations,
                'doctor_reservations'   => $user->doctorReservations,
            ],
        ]);
    }
}
