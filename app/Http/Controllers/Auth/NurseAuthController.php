<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\NurseRegisterRequest;
use App\Models\Account;
use App\Models\Nurse;
use App\Notifications\SendVerificationCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tymon\JWTAuth\Facades\JWTAuth;

class NurseAuthController extends Controller
{
    public function register(NurseRegisterRequest $request): JsonResponse
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

            $licenseImagePath = null;
            if ($request->hasFile('license_image')) {
                $licenseFile = $request->file('license_image');
                $filename = uniqid('nurse_license_') . '.' . $licenseFile->getClientOriginalExtension();
                $licenseImagePath = $licenseFile->storeAs(
                    'nurses/licenses',
                    $filename,
                    'private'
                );
            }

            $profileImagePath = null;
            if ($request->hasFile('profile_image')) {
                $profileFile = $request->file('profile_image');
                $filename = uniqid('nurse_profile_') . '.' . $profileFile->getClientOriginalExtension();
                $profileImagePath = $profileFile->storeAs(
                    'nurses/profile_images',
                    $filename,
                    'public'
                );
            }

            Nurse::create([
                'account_id'          => $account->id,
                'full_name'           => $validated['full_name'],
                'address'             => $validated['address'],
                'graduation_type'     => $validated['graduation_type'],
                'location'            => new Point($validated['latitude'], $validated['longitude']),
                'age'                 => $validated['age'],
                'gender'              => $validated['gender'],
                'profile_description' => $validated['profile_description'] ?? null,
                'license_image_path'  => $licenseImagePath,
                'profile_image_path'  => $profileImagePath,
                'province_id' => $validated['province_id'],
            ]);

            $account->assignRole('nurse');

            DB::commit();

            return response()->json([
                'message' => 'Registration successful. Awaiting admin approval.',
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();
            Log::error('Nurse registration failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Registration failed. Please try again later.',
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
        $credentials = $request->only('email', 'password','fcm_token');

        $account = Account::where('email', $credentials['email'])->first();

        if (!$account || !Hash::check($credentials['password'], $account->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        if (!$account->hasRole('nurse')) {
            return response()->json(['message' => 'Not authorized as nurse'], 403);
        }
        if ($account->is_approved !== 'approved') {
            return response()->json(['message' => 'Your registration is not approved by admin yet.'], 403);
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
        $user = auth()->user();
        $nurse = $user->nurse;
        return response()->json(
            [$user , $nurse]
        );
    }
    public function requestLogin(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:accounts,email',
        ]);
        $account = Account::where('email', $request->email)->first();
        if (!$account->hasRole('nurse')) {
            return response()->json(['message' => 'Not authorized as nurse'], 403);
        }
        if ($account->is_approved !== 'approved') {
            return response()->json(['message' => 'Admin approval pending.'], 403);
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
        $nurse = $account->nurse;

        // Validate request fields including optional image
        $validated = $request->validate([
            'phone_number' => 'sometimes|string|unique:accounts,phone_number,' . $account->id,
            'full_name' => 'sometimes|string|max:255',
            'address' => 'nullable|string|max:255',
            'graduation_type' => 'sometimes|in:معهد,مدرسة,جامعة,ماجستير,دكتوراه',
            'longitude' => 'nullable|numeric',
            'latitude' => 'nullable|numeric',
            'age' => 'sometimes|integer|min:21|max:99',
            'gender' => 'sometimes|in:male,female',
            'profile_description' => 'nullable|string|max:500',

            // New rule for profile image
            'profile_image' => 'sometimes|image|mimes:jpg,jpeg,png,gif|max:20480', // 20 MB
            'province_id' => 'sometimes|integer|exists:provinces,id'
        ]);

        // ========== UPDATE ACCOUNT ==========
        if (isset($validated['phone_number'])) {
            $account->phone_number = $validated['phone_number'];
        }

        // ========== UPDATE NURSE INFO ==========
        if (isset($validated['full_name'])) {
            $nurse->full_name = $validated['full_name'];
        }

        if (isset($validated['address'])) {
            $nurse->address = $validated['address'];
        }

        if (isset($validated['graduation_type'])) {
            $nurse->graduation_type = $validated['graduation_type'];
        }

        if (isset($validated['longitude']) && isset($validated['latitude'])) {
            $nurse->location = new Point($validated['latitude'], $validated['longitude']);
        }

        if (isset($validated['age'])) {
            $nurse->age = $validated['age'];
        }

        if (isset($validated['gender'])) {
            $nurse->gender = $validated['gender'];
        }

        if (isset($validated['profile_description'])) {
            $nurse->profile_description = $validated['profile_description'];
        }
        if (isset($validated['province_id']))
        {
            $nurse->province_id = $validated['province_id'];
        }
        if ($request->hasFile('profile_image')) {

            if ($nurse->profile_image_path && Storage::disk('public')->exists($nurse->profile_image_path)) {
                Storage::disk('public')->delete($nurse->profile_image_path);
            }

            $path = $request->file('profile_image')->store('nurses/profile_images', 'public');

            $nurse->profile_image_path = $path;
        }

        // Save both models
        $account->save();
        $nurse->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'phone_number' => $account->phone_number,
            'nurse' => $nurse->fresh(),
        ]);
    }



}
