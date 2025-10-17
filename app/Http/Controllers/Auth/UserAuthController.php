<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\NurseRegisterRequest;
use App\Http\Requests\UserRegisterRequest;
use App\Models\Account;
use App\Models\User;
use App\Notifications\SendVerificationCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserAuthController extends Controller
{


    public function register(UserRegisterRequest $request) : JsonResponse
    {
        DB::beginTransaction();

        try {
            $account = Account::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone_number' => $request->phone_number,
                'is_approved' => 'approved'
            ]);

            $user = User::create([
                'account_id' => $account->id,
                'full_name' => $request->full_name,
                'age' => $request->age,
                'gender' => $request->gender,
            ]);
            $account->assignRole('user');
            $token = JWTAuth::fromUser($account);

            DB::commit();

            return response()->json([
                'message' => 'User registered successfully',
                'token' => $token,
                'role' => $account->getRoleNames()->first()
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->only('email', 'password','fcm_token');

        $account = Account::where('email', $credentials['email'])->first();

        if (!$account || !Hash::check($credentials['password'], $account->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        if (!$account->hasRole('user')) {
            return response()->json(['message' => 'Not authorized as user'], 403);
        }
        $token = JWTAuth::fromUser($account);
        $account->fcm_token = $credentials['fcm_token'];
        $account->save();

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'role' => $account->getRoleNames()->first()
        ]);
    }
    public function logout(): JsonResponse
    {
        auth()->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }
    public function me(): JsonResponse
    {
        $account = auth()->user();
        $user = $account->user;
        return response()->json(
            [$account , $user]
        );
    }
    public function requestLogin(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:accounts,email',
        ]);
        $account = Account::where('email', $request->email)->first();
        if (!$account->hasRole('user')) {
            return response()->json(['message' => 'Not authorized as user'], 403);
        }
        $code = rand(100000, 999999);
        $account->verification_code = $code;
        $account->verification_expires_at = now()->addMinutes(5);
        $account->save();
        $account->notify(new SendVerificationCode($code));

        return response()->json(['message' => 'Verification code sent to email.']);
    }
    public function verifyLogin(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:accounts,email',
            'code'  => 'required|numeric',
        ]);

        $account = Account::where('email', $request->email)->first();

        if (
            !$account->verification_code ||
            $account->verification_code != $request->code ||
            !$account->verification_expires_at ||
            now()->gt($account->verification_expires_at)
        ) {
            return response()->json(['message' => 'Invalid or expired verification code.'], 403);
        }

        $account->verification_code = null;
        $account->verification_expires_at = null;
        $account->save();

        $token = JWTAuth::fromUser($account);

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'role' => $account->getRoleNames()->first()
        ]);
    }
    public function updateProfile(Request $request): JsonResponse
    {
        $account = auth()->user();
        $user = $account->user; // الحصول على السجلات الخاصة بالممرض المرتبط بالحساب الحالي

        // التحقق من البيانات المدخلة
        $validated = $request->validate([
            'phone_number' => 'sometimes|string|unique:accounts,phone_number',
            'full_name' => 'sometimes|string|max:50',
            'age' => 'sometimes|integer|min:0|max:99',
            'gender' => 'sometimes|in:male,female',
        ]);
        if (isset($validated['phone_number']))     {
            $account->phone_number = $validated['phone_number'];
        }
        // تحديث الحقول في جدول الممرض
        if (isset($validated['full_name'])) {
            $user->full_name = $validated['full_name'];
        }


        if (isset($validated['age'])) {
            $user->age = $validated['age'];
        }

        if (isset($validated['gender'])) {
            $user->gender = $validated['gender'];
        }

        $account->save();
        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'phone_number' => $account->phone_number,
            'user' => $user,
        ]);
    }

}
