<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendApprovalEmail;
use App\Jobs\SendFirebaseNotificationJob;
use App\Jobs\SendVerificationCodeJob;
use App\Models\Account;
use App\Models\Doctor;
use App\Support\SubscriptionTrial;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Notifications\SendVerificationCode;

class AdminApproveController extends Controller
{
    public function approve($accountId): JsonResponse
    {
        $account = Account::find($accountId);
        if (!$account) {
            return response()->json(['message' => 'Invalid ID'], 404);
        }
        if ($account->is_approved == 'approved') {
            return response()->json(['message' => 'Already approved.']);
        }

        $account->is_approved = 'approved';
        $account->verification_code = null;  // No need for verification code anymore

        // Doctors / nurses get the automatic 45-day trial counted from today
        // (the approval date), so the admin never has to add it by hand. A
        // longer expiry the admin already set manually is kept.
        SubscriptionTrial::anchorToApproval($account);

        $account->save();

        // Dispatch job to send the approval email
        SendApprovalEmail::dispatch($account);

        $role = $account->getRoleNames()->first();
        return response()->json([
            'message' => 'تمت الموافقة على الحساب وإرسال بريد التفعيل.',
            'role' => $role,
            'subscription_expires_at' => $account->subscription_expires_at
                ? Carbon::parse($account->subscription_expires_at)->toDateTimeString()
                : null,
            'trial_days' => SubscriptionTrial::appliesTo($account) ? SubscriptionTrial::days() : null,
        ]);
    }
    protected function validateRole(string $role)
    {
        $validRoles = ['doctor', 'nurse'];
        if (!in_array($role, $validRoles)) {
            abort(422, 'Invalid role: ' . $role);
        }
    }

    protected function getPendingAccountsByRole(string $role)
    {
        $this->validateRole($role);
        return Account::with($role)->role($role)
        ->where('is_approved', 'pending')
        ->orderByDesc('created_at')
        ->paginate(10);
    }
    public function index(Request $request): JsonResponse
    {
        $role = $request->role;
        $accounts = $this->getPendingAccountsByRole($role);

        return response()->json($accounts);
    }
    /**
     * PATCH api/admin/toggleSuspension
     *
     * Restrict (suspend) or re-activate any account.
     *
     * `is_suspended` used to be validated with the strict `boolean` rule,
     * which rejects the strings "true" / "false" that dashboards send from
     * form-data or query strings — so the request always failed with a 422
     * and restricting accounts looked like it "was not enabled". It is now
     * parsed leniently, and may be omitted to simply flip the current state.
     */
    public function toggleSuspension(Request $request)
    {
        if ($request->has('is_suspended')) {
            $parsed = filter_var($request->input('is_suspended'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $request->merge(['is_suspended' => $parsed]);
        }

        $request->validate([
            'is_suspended' => 'nullable|boolean',
            'account_id' => 'required|integer|exists:accounts,id'
        ], [
            'is_suspended.boolean' => 'قيمة حالة الإيقاف يجب أن تكون true أو false.',
            'account_id.exists' => 'الحساب غير موجود.',
        ]);

        $account = Account::findOrFail($request->account_id);

        if ($account->hasRole('admin')) {
            return response()->json(['message' => 'لا يمكن إيقاف حساب مدير.'], 422);
        }

        $suspend = $request->input('is_suspended');
        $account->is_suspended = is_null($suspend) ? !$account->is_suspended : (bool) $suspend;
        $account->save();

        if ($account->fcm_token) {
            SendFirebaseNotificationJob::dispatch(
                $account->fcm_token,
                $account->is_suspended ? 'تم إيقاف الحساب' : 'تم تفعيل الحساب',
                $account->is_suspended
                    ? 'تم إيقاف حسابك من قبل الإدارة يرجى التواصل مع الدعم الفني.'
                    : 'تمت إعادة تفعيل حسابك، يمكنك استخدام التطبيق الآن.'
            );
        }

        return response()->json([
            'message' => $account->is_suspended
                ? 'تم إيقاف (تقييد) الحساب بنجاح.'
                : 'تم تفعيل الحساب بنجاح.',
            'account_id' => $account->id,
            'is_suspended' => (bool) $account->is_suspended,
        ]);
    }
    /**
     * PATCH api/admin/extend-subscription
     *
     * `days` extends the subscription from its current expiry (or from now
     * when it has already lapsed).
     *
     * Nurse accounts are day-based, like doctor accounts: sending
     * `lifetime` for a nurse is rejected instead of silently storing null,
     * which is what previously left the nurse with no expiry date at all.
     */
    public function extendSubscription(Request $request)
    {
        $request->validate([
            'days' => 'required_without:lifetime|integer|min:1',
            'lifetime' => 'sometimes|boolean',
            'account_id' => 'required|integer'
        ]);

        $account = Account::findOrFail($request->account_id);

        $wantsLifetime = $request->boolean('lifetime');

        if ($wantsLifetime && $account->hasRole('nurse')) {
            return response()->json([
                'message' => 'اشتراك الممرض يُحدَّد بالأيام فقط، لا يمكن تحويله إلى مدى الحياة.',
                'errors' => [
                    'lifetime' => ['اشتراك الممرض يُحدَّد بالأيام فقط، لا يمكن تحويله إلى مدى الحياة.'],
                ],
            ], 422);
        }

        $now = Carbon::now();

        if ($wantsLifetime) {
            $account->update([
                'subscription_expires_at' => null
            ]);

            $message = 'تم تحويل الاشتراك إلى مدى الحياة.';
            $expiryText = 'مدى الحياة';
        } else {
            $currentExpiry = $account->subscription_expires_at;

            $newExpiry = $currentExpiry && $currentExpiry->isFuture()
                ? $currentExpiry->copy()->addDays((int) $request->days)
                : $now->copy()->addDays((int) $request->days);

            $account->update([
                'subscription_expires_at' => $newExpiry
            ]);

            $message = "تم تمديد الاشتراك {$request->days} يوم(أيام) بنجاح.";
            $expiryText = $newExpiry->toDateTimeString();
        }

        if ($account->fcm_token) {
            $body = $wantsLifetime
                ? "تم تحويل اشتراكك إلى مدى الحياة."
                : sprintf(
                    "تم تمديد اشتراكك بمقدار %d يوم(أيام). تاريخ الانتهاء الجديد: %s",
                    $request->days,
                    $expiryText
                );

            SendFirebaseNotificationJob::dispatch(
                $account->fcm_token,
                $wantsLifetime ? 'اشتراك مدى الحياة' : 'تم تمديد الاشتراك',
                $body
            );
        }

        return response()->json([
            'message' => $message,
            'subscription_expires_at' => $expiryText
        ]);
    }

}
