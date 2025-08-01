<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Hospital;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ManageHospitalsAccountsController extends Controller
{
    public function createHospitalAccount(Request $request): JsonResponse
    {
//        try {
            DB::beginTransaction();
            $validator = Validator::make($request->all(), [
                'hospital_name' => 'required|string|max:255|unique:hospitals,full_name', // تحقق من أن الاسم غير موجود مسبقًا
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            // توليد UUID للمستشفى
            $uniqueCode = Str::uuid(); // توليد UUID فريد

            // إنشاء حساب للمستشفى
            $account = Account::create([
                'email' => '', // يتم تعيينه لاحقًا
                'password' => '', // يتم تعيينه لاحقًا
                'phone_number' => '', // سيتم تعيينه لاحقًا
                'fcm_token' => null, // يمكن ملؤه لاحقًا

            ]);

            // إنشاء المستشفى مع الكود الفريد
            $hospital = Hospital::create([
                'account_id' => $account->id,
                'full_name' => $request->hospital_name, // اسم المستشفى
                'unique_code' => $uniqueCode,  // الرمز الفريد
                'address' => ''
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Hospital created successfully. Unique code assigned.',
                'hospital_name' => $request->hospital_name,
                'password' => $uniqueCode
            ], 201);

//        } catch (\Exception $e) {
//            DB::rollBack();
//            return response()->json(['message' => 'Failed to create hospital. Please try again.'], 500);
//        }
    }
}
