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
use MatanYadaev\EloquentSpatial\Objects\Point;

class ManageHospitalsAccountsController extends Controller
{
    public function createHospitalAccount(Request $request): JsonResponse
    {
        //TODO : a unique email and phone problem
        try {
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
            $temporaryEmail = 'hospital_' . $uniqueCode . '@example.com'; // توليد بريد إلكتروني مؤقت
            $temporaryPhone = '000-' . $uniqueCode->toString(); // توليد رقم هاتف مؤقت

            // إنشاء حساب للمستشفى
            $account = Account::create([
                'email' => $temporaryEmail, // تعيين البريد الإلكتروني المؤقت
                'password' => '', // سيتم تعيينه لاحقًا
                'phone_number' => $temporaryPhone, // تعيين رقم الهاتف المؤقت
                'fcm_token' => null, // يمكن ملؤه لاحقًا
            ]);

            $hospital = Hospital::create([
                'account_id' => $account->id,
                'full_name' => $request->hospital_name, // اسم المستشفى
                'unique_code' => $uniqueCode,  // الرمز الفريد
                'address' => '',
                'location'=> new Point(0 , 0),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Hospital created successfully. Unique code assigned.',
                'hospital_name' => $request->hospital_name,
                'password' => $uniqueCode
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create hospital. Please try again.'], 500);
        }
    }
    #todo 1- get all doctors(paginate , 10 per page) 2-doctor with all his information's(using doctor_id) 3-reservations with filters(using doctor_id) (filters : reservation status,start,end)
    #todo 1- get all hospitals (paginate , 10 per page), 2-hospital with all his information's , 3-reservations with filters (using hospital_id) (filters : reservation status,start,end)
    #todo 1- get all nurses (paginate , 10 per page), 2-nurses with all his information's (using nurse_id), 3-reservations with filters (using nurse_id) (filters : reservation status,start,end)
    #todo 1- get all users (paginate , 10 per page), 2-users with all his information's (using nurse_id), 3-reservations with filters (using user_id) (filters : reservation status,start,end)

}
