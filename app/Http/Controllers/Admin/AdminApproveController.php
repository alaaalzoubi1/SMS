<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendApprovalEmail;
use App\Jobs\SendFirebaseNotificationJob;
use App\Jobs\SendVerificationCodeJob;
use App\Models\Account;
use App\Models\Doctor;
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

        $account->update([
            'is_approved' => 'approved',
            'verification_code' => null,  // No need for verification code anymore
        ]);

        // Dispatch job to send the approval email
        SendApprovalEmail::dispatch($account);

        $role = $account->getRoleNames()->first();
        return response()->json(['message' => ucfirst($role) . ' approved and approval email sent.']);
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
    public function toggleSuspension(Request $request)
    {
        $request->validate([
            'is_suspended' => 'required|boolean',
            'account_id' => 'required|exists:accounts,id'
        ]);
        $id = $request->account_id;
        $account = Account::findOrFail($id);
        $account->is_suspended = $request->is_suspended;
        $account->save();
        return response()->json([
            'message' => 'suspension status updated successfully.',
        ]);
    }
    public function extendSubscription(Request $request)
    {
        $request->validate([
            'days' => 'required_without:lifetime|integer|min:1',
            'lifetime' => 'sometimes|boolean',
            'account_id' => 'required|integer'
        ]);

        $account = Account::findOrFail($request->account_id);
        $now = Carbon::now();

        if ($request->boolean('lifetime')) {
            $account->update([
                'subscription_expires_at' => null
            ]);

            $message = 'تم تحويل الاشتراك إلى مدى الحياة.';
            $expiryText = 'مدى الحياة';
        } else {
            $currentExpiry = $account->subscription_expires_at;

            $newExpiry = $currentExpiry && $currentExpiry->isFuture()
                ? $currentExpiry->addDays( (integer) $request->days)
                : $now->addDays((integer)$request->days);

            $account->update([
                'subscription_expires_at' => $newExpiry
            ]);

            $message = "تم تمديد الاشتراك {$request->days} يوم(أيام) بنجاح.";
            $expiryText = $newExpiry->toDateTimeString();
        }

        if ($account->fcm_token) {
            $body = $request->boolean('lifetime')
                ? "تم تحويل اشتراكك إلى مدى الحياة."
                : sprintf(
                    "تم تمديد اشتراكك بمقدار %d يوم(أيام). تاريخ الانتهاء الجديد: %s",
                    $request->days,
                    $expiryText
                );

            SendFirebaseNotificationJob::dispatch(
                $account->fcm_token,
                $request->boolean('lifetime') ? 'اشتراك مدى الحياة' : 'تم تمديد الاشتراك',
                $body
            );
        }

        return response()->json([
            'message' => $message,
            'subscription_expires_at' => $expiryText
        ]);
    }

}
