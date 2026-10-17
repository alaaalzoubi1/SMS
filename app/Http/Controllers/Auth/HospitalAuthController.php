<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\HospitalEditProfileRequest;
use App\Http\Requests\StoreHospitalRequest;
use App\Jobs\SendVerificationCodeJob;
use App\Models\Account;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Support\IssuesJwtTokens;
use Illuminate\Support\Facades\Validator;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tymon\JWTAuth\Facades\JWTAuth;

class HospitalAuthController extends Controller
{
    use IssuesJwtTokens;

    public function updateHospitalData(StoreHospitalRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();

            $hospital = Hospital::where('full_name', $validated['hospital_name'])
                ->where('unique_code', $validated['unique_code'])
                ->withSuspended()
                ->first();

            if (!$hospital) {
                DB::rollBack();
                return response()->json(['message' => 'المشفى غير موجود أو الرمز غير صحيح.'], 404);
            }

            $account = $hospital->account;

            if ($account->is_approved === 'approved') {
                DB::rollBack();
                return response()->json([
                    'message' => 'تم إنشاء حساب هذا المشفى مسبقاً، يرجى تسجيل الدخول.'
                ], 409);
            }

            $profileImagePath = $hospital->profile_image_path;

            if ($request->hasFile('profile_image')) {
                $file = $request->file('profile_image');
                $filename = uniqid('hospital_profile_') . '.' . $file->getClientOriginalExtension();

                $newPath = $file->storeAs('hospitals/profile_images', $filename, 'public');

                if ($profileImagePath && Storage::disk('public')->exists($profileImagePath)) {
                    Storage::disk('public')->delete($profileImagePath);
                }

                $profileImagePath = $newPath;
            }

            $hospital->update([
                'full_name' => $validated['hospital_name'],
                'hospital_type_id' => $validated['hospital_type_id'],
                'address'   => $validated['address'],
                'location'  => new Point($validated['latitude'], $validated['longitude']),
                'profile_image_path' => $profileImagePath,
                'profile_description' => $validated['profile_description'],
                'province_id' => $validated['province_id'],
                'reservation_confirmation_deadline' => $validated['reservation_confirmation_deadline']
            ]);

            $account->update([
                'email'        => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'password'     => Hash::make($validated['password']),
                'is_approved'  => 'approved'
            ]);

            User::create(
                [
                    'full_name' => $hospital->full_name,
                    'age' => 0,
                    'gender' => 'male',
                ]
            );
            $account->assignRole('user');


            $token = JWTAuth::fromUser($account);

            DB::commit();

            return response()->json([
                'message'  => 'Hospital data updated successfully.',
                'hospital' => $hospital->fresh(['hospitalType']),
                'account'  => $account->fresh(),
                'token'    => $token,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Hospital update failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }


    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'       => 'required|email',
            'password'    => 'required|string',
            'fcm_token'   => 'nullable|string|max:1024',
            'remember_me' => 'sometimes|boolean',
        ]);

        $credentials = $request->only('email', 'password');

        $account = Account::where('email', $credentials['email'])->first();

        if (!$account || !Hash::check($credentials['password'], $account->password)) {
            return response()->json(['message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.'], 401);
        }
        if (!$account->hasRole('hospital')) {
            return response()->json(['message' => 'هذا الحساب ليس حساب مشفى.'], 403);
        }
        if ($account->is_approved !== 'approved') {
            return response()->json(['message' => 'لم يتم استكمال تسجيل حساب المشفى بعد.'], 403);
        }
        if ($account->is_suspended)
        {
            return response()->json(['message' => 'تم إيقاف حسابك من قبل الإدارة يرجى التواصل مع الدعم الفني.'], 403);
        }

        // Without this the hospital never had an FCM token, so it could not
        // be told about new reservation requests.
        $this->storeFcmToken($account, $request);
        $token = $this->issueToken($account, $request);

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح.',
            'token' => $token,
            'expires_in' => $this->tokenTtlMinutes($request) * 60,
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
            return response()->json(['message' => 'لم يتم استكمال تسجيل حساب المشفى بعد.'], 403);
        }
        if ($account->is_suspended) {
            return response()->json(['message' => 'تم إيقاف حسابك من قبل الإدارة يرجى التواصل مع الدعم الفني.'], 403);
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

        if ($account->is_suspended) {
            return response()->json(['message' => 'تم إيقاف حسابك من قبل الإدارة يرجى التواصل مع الدعم الفني.'], 403);
        }

        $account->verification_code = null;
        $account->verification_expires_at = null;
        $account->save();

        $this->storeFcmToken($account, $request);
        $token = $this->issueToken($account, $request);

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح.',
            'token' => $token,
            'expires_in' => $this->tokenTtlMinutes($request) * 60,
        ]);
    }
    public function editProfile(HospitalEditProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $account = auth()->user();
        $hospital = $account->hospital;

        $accountData = [];
        $hospitalData = [];

        if (array_key_exists('full_name', $validated)) {
            $hospitalData['full_name'] = $validated['full_name'];
        }

        if (array_key_exists('phone_number', $validated)) {
            $accountData['phone_number'] = $validated['phone_number'];
        }

        if (array_key_exists('address', $validated)) {
            $hospitalData['address'] = $validated['address'];
        }
        if (array_key_exists('province_id',$validated))
        {
            $hospitalData['province_id'] = $validated['province_id'];
        }
        if (array_key_exists('reservation_confirmation_deadline',$validated))
        {
            $hospitalData['reservation_confirmation_deadline'] = $validated['reservation_confirmation_deadline'];
        }
        if (array_key_exists('profile_description',$validated))
        {
            $hospitalData['profile_description'] = $validated['profile_description'];
        }
        if (array_key_exists('hospital_type_id', $validated))
        {
            $hospitalData['hospital_type_id'] = $validated['hospital_type_id'];
        }

        if ($request->hasFile('profile_image')) {

            $oldImage = $hospital->profile_image_path;

            $file = $request->file('profile_image');
            $filename = uniqid('hospital_profile_') . '.' . $file->getClientOriginalExtension();
            $newPath = $file->storeAs('hospitals/profile_images', $filename, 'public');

            $hospitalData['profile_image_path'] = $newPath;

            if ($oldImage && Storage::disk('public')->exists($oldImage)) {
                Storage::disk('public')->delete($oldImage);
            }
        }

        if (!empty($accountData)) {
            $account->update($accountData);
        }

        if (!empty($hospitalData)) {
            $hospital->update($hospitalData);
        }

        return response()->json([
            'message' => 'تم تحديث الملف الشخصي بنجاح.',
            'hospital' => $hospital->fresh(['hospitalType']),
            'account' => $account
        ]);
    }

}
