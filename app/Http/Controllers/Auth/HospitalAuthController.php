<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\HospitalEditProfileRequest;
use App\Http\Requests\StoreHospitalRequest;
use App\Jobs\SendVerificationCodeJob;
use App\Models\Account;
use App\Models\Hospital;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class HospitalAuthController extends Controller
{
    public function updateHospitalData(StoreHospitalRequest $request): JsonResponse
    {
        // Step 1: The request is already validated via the form request class

        try {
            DB::beginTransaction();
            $validated = $request->validated();
            // Step 2: Find hospital by name and unique_code
            $hospital = Hospital::where('full_name', $validated['hospital_name'])
                ->where('unique_code', $validated['unique_code'])
                ->first();

            if (!$hospital) {
                return response()->json(['message' => 'Hospital not found or invalid code.'], 404);
            }

            // Step 3: Find associated account
            $account = $hospital->account;
            if ($account->is_approved === 'approved')
                return response()->json([
                    'message' => 'account already have been created'
                ]);
            // Step 4: Update hospital data
            $hospital->update([
                'full_name' => $validated['hospital_name'], // Update hospital name
                'address' => $validated['address'], // Update address
            ]);

            // Step 5: Update account data
            $account->update([
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'password'     => Hash::make($validated['password']),
                'is_approved' => 'approved'
            ]);
            $account->assignRole('hospital');
            $token = JWTAuth::fromUser($account);
            DB::commit();

            return response()->json([
                'message' => 'Hospital data updated successfully.',
                'hospital' => $hospital,
                'account' => $account,
                'token' => $token
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Hospital update failed: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred. Please try again later.'], 500);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        $account = Account::where('email', $credentials['email'])->first();

        if (!$account || !Hash::check($credentials['password'], $account->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        if (!$account->hasRole('hospital')) {
            return response()->json(['message' => 'Not authorized as hospital'], 403);
        }
        if ($account->is_approved !== 'approved') {
            return response()->json(['message' => 'Your registration is not completed yet.'], 403);
        }
        $token = JWTAuth::fromUser($account);

        return response()->json([
            'message' => 'Login successful',
            'token' => $token
        ]);
    }
    public function logout(): JsonResponse
    {
        auth()->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }
    public function me(): JsonResponse
    {
        $user = auth()->user();
        $hospital = $user->hospital;
        return response()->json(
            [$user , $hospital]
        );
    }
    public function requestLogin(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:accounts,email',
        ]);
        $account = Account::where('email', $request->email)->first();
        if (!$account->hasRole('hospital')) {
            return response()->json(['message' => 'Not authorized as hospital'], 403);
        }
        if ($account->is_approved !== 'approved') {
            return response()->json(['message' => 'you need to complete your registration.'], 403);
        }
        $code = rand(100000, 999999);
        $account->verification_code = $code;
        $account->verification_expires_at = now()->addMinutes(5);
        $account->save();
        SendVerificationCodeJob::dispatch($account);

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
            'token' => $token
        ]);
    }
    public function editProfile(HospitalEditProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $account = auth()->user();
        $hospital = $account->hospital;

        $accountData = [];
        $hospitalData = [];

        // تحديث الحقول في حساب المستخدم
        if (array_key_exists('full_name', $validated)) {
            $hospitalData['full_name'] = $validated['full_name'];
        }

        if (array_key_exists('phone_number', $validated)) {
            $accountData['phone_number'] = $validated['phone_number'];
        }

        // تحديث الحقول في جدول الأطباء
        $field = 'address';
        if (array_key_exists($field, $validated)) {
            $hospitalData[$field] = $validated[$field];
        }


        // تنفيذ التحديثات
        if (!empty($accountData)) {
            $account->update($accountData);
        }

        if (!empty($hospitalData)) {
            $hospital->update($hospitalData);
        }

        return response()->json([
            'message' => 'تم تحديث الملف الشخصي بنجاح.',
            'hospital' => $hospital->fresh(),
            'account' => $account
        ]);
    }
}
