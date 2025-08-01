<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendVerificationCodeJob;
use App\Models\Account;
use App\Models\Doctor;
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

        $verificationCode = rand(100000, 999999);
        $account->update([
            'is_approved' => 'approved',
            'verification_code' => $verificationCode
        ]);

        // أرسل الإشعار باستخدام الـ Job
        SendVerificationCodeJob::dispatch($account);

        $role = $account->getRoleNames()->first();
        return response()->json(['message' => ucfirst($role) . ' approved and verification code sent.']);
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


}
