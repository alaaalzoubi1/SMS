<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SpecializationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\DoctorEditProfileRequest;
use App\Http\Requests\DoctorRegisterRequest;
use App\Jobs\SendVerificationCodeJob;
use App\Models\Account;
use App\Models\Doctor;
use App\Notifications\SendVerificationCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelOptions\Options;
use Tymon\JWTAuth\Facades\JWTAuth;

class DoctorAuthController extends Controller
{


    public function register(DoctorRegisterRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            DB::beginTransaction();

            $account = Account::create([
                'email'        => $validated['email'],
                'password'     => Hash::make($validated['password']),
                'phone_number' => $validated['phone_number'],
                'fcm_token'    => $request->fcm_token ?? null,
            ]);

            // تخزين صورة الرخصة
            $licensePath = null;
            if ($request->hasFile('license_image')) {
                $licenseFile = $request->file('license_image');
                $filename = uniqid('license_') . '.' . $licenseFile->getClientOriginalExtension();
                $licensePath = $licenseFile->storeAs('doctors/licenses', $filename, 'public');
            }

            Doctor::create([
                'account_id'         => $account->id,
                'full_name'          => $validated['full_name'],
                'specialization_id'=> $validated['specialization_id'],
                'address'            => $validated['address'],
                'age'                => $validated['age'],
                'gender'             => $validated['gender'],
                'profile_description' => $validated['profile_description'],
                'license_image_path' => $licensePath,
            ]);

            $account->assignRole('doctor');

            DB::commit();

            return response()->json([
                'message' => 'تم التسجيل بنجاح. بانتظار موافقة الإدارة.',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Doctor registration failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'فشل في التسجيل. حاول مرة أخرى لاحقاً.',
            ], 500);
        }
    }




    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string'
        ]);

        $account = Account::where('email', $request->email)
            ->where('verification_code', $request->code)
            ->first();

        if (!$account || $account->is_approved != 'approved') {
            return response()->json(['message' => 'Invalid code or not approved.'], 401);
        }

        $account->update(['verification_code' => null]);

        $token = JWTAuth::fromUser($account);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
        ]);
    }
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        $account = Account::where('email', $credentials['email'])->first();

        if (!$account || !Hash::check($credentials['password'], $account->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        if (!$account->hasRole('doctor')) {
            return response()->json(['message' => 'Not authorized as doctor'], 403);
        }
        if ($account->is_approved !== 'approved') {
            return response()->json(['message' => 'Your registration is not approved by admin yet.'], 403);
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
        $doctor = $user->doctor;
        return response()->json(
            [$user , $doctor , $doctor->specialization]
        );
    }
    public function requestLogin(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:accounts,email',
        ]);
        $account = Account::where('email', $request->email)->first();
        if (!$account->hasRole('doctor')) {
            return response()->json(['message' => 'Not authorized as doctor'], 403);
        }
        if ($account->is_approved !== 'approved') {
            return response()->json(['message' => 'Admin approval pending.'], 403);
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

    public function editProfile(DoctorEditProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $account = auth()->user();
        $doctor = $account->doctor;

        $accountData = [];
        $doctorData = [];

        // تحديث الحقول في حساب المستخدم
        if (array_key_exists('full_name', $validated)) {
            $doctorData['full_name'] = $validated['full_name'];
        }

        if (array_key_exists('phone_number', $validated)) {
            $accountData['phone_number'] = $validated['phone_number'];
        }

        // تحديث الحقول في جدول الأطباء
        foreach (['address', 'age', 'gender', 'profile_description'] as $field) {
            if (array_key_exists($field, $validated)) {
                $doctorData[$field] = $validated[$field];
            }
        }

        // معالجة التخصص (enum) إذا وُجد
        if (array_key_exists('specialization_id', $validated)) {
            $doctorData['specialization_id'] = $validated['specialization_id'];
        }

        // تنفيذ التحديثات
        if (!empty($accountData)) {
            $account->update($accountData);
        }

        if (!empty($doctorData)) {
            $doctor->update($doctorData);
        }

        return response()->json([
            'message' => 'تم تحديث الملف الشخصي بنجاح.',
            'doctor' => $doctor->fresh(),
        ]);
    }




}
