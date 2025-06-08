<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Notifications\SendResetPasswordCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ForgotPasswordController extends Controller
{
    public function requestResetCode(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:accounts,email']);

        $account = Account::where('email', $request->email)->first();
        $code = rand(100000, 999999);

        $account->reset_code = $code;
        $account->reset_expires_at = now()->addMinutes(5);
        $account->save();

        $account->notify(new SendResetPasswordCode($code));

        return response()->json(['message' => 'Reset code sent to your email.']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email|exists:accounts,email',
            'code'     => 'required|numeric',
            'password' => 'required|min:6'
        ]);

        $account = Account::where('email', $request->email)->first();

        if (
            !$account->reset_code ||
            $account->reset_code != $request->code ||
            !$account->reset_expires_at ||
            now()->gt($account->reset_expires_at)
        ) {
            return response()->json(['message' => 'Invalid or expired code.'], 403);
        }

        $account->password = Hash::make($request->password);
        $account->reset_code = null;
        $account->reset_expires_at = null;
        $account->save();

        return response()->json(['message' => 'Password reset successful.']);
    }
}
