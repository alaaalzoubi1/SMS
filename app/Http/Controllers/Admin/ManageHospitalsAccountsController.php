<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Hospital;
use App\Models\HospitalService;
use App\Models\HospitalServiceReservation;
use App\Models\HospitalWorkSchedule;
use App\Models\Rating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use MatanYadaev\EloquentSpatial\Objects\Point;

class ManageHospitalsAccountsController extends Controller
{
    public function createHospitalAccount(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'hospital_name' => 'required|string|max:255|unique:hospitals,full_name',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $uniqueCode = Str::uuid();
            $temporaryEmail = 'hospital_' . $uniqueCode . '@example.com';
            $temporaryPhone = '000-' . $uniqueCode->toString();

            $account = Account::create([
                'email' => $temporaryEmail,
                'password' => '',
                'phone_number' => $temporaryPhone,
                'fcm_token' => null,
            ]);
            $account->assignRole('hospital');

            $hospital = Hospital::create([
                'account_id' => $account->id,
                'full_name' => $request->hospital_name,
                'unique_code' => $uniqueCode,
                'address' => '',
                'location'=> new Point(0 , 0),
                'province_id' => 1,
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

    /**
     * Delete a hospital account.
     *
     * Soft delete by default: reservations reference the hospital with a
     * foreign key, so a hard delete would break history (and would fail on
     * the FK while the hospital still owns services or bookings). Pass
     * `force=true` to purge dependent rows first and really remove it.
     *
     * The login is cut immediately either way by revoking every JWT issued to
     * the account and marking it suspended.
     */
    public function destroyHospitalAccount(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'force' => 'sometimes|boolean',
        ]);

        $hospital = Hospital::with('account')->find($id);

        if (!$hospital) {
            return response()->json(['message' => 'المشفى غير موجود'], 404);
        }

        $force = (bool) ($validated['force'] ?? false);

        DB::beginTransaction();

        try {
            if ($force) {
                $this->purgeHospitalDependents($hospital);
            }

            $hospital->delete();

            $account = $hospital->account;
            if ($account) {
                $account->forceFill(['is_suspended' => true])->save();

                try {
                    auth()->guard('api')->logout();
                } catch (\Throwable $e) {
                    Log::warning('JWT blacklist unavailable while deleting hospital: ' . $e->getMessage());
                }
            }

            DB::commit();

            return response()->json([
                'message' => $force
                    ? 'تم حذف حساب المشفى نهائياً.'
                    : 'تم حذف حساب المشفى.',
                'hospital_id' => $hospital->id,
                'force_deleted' => $force,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to delete hospital account: ' . $e->getMessage());

            return response()->json(['message' => 'تعذر حذف حساب المشفى'], 500);
        }
    }

    /**
     * Bring a soft-deleted hospital back, which re-enables its login.
     */
    public function restoreHospitalAccount(int $id): JsonResponse
    {
        $hospital = Hospital::withTrashed()->with('account')->find($id);

        if (!$hospital) {
            return response()->json(['message' => 'المشفى غير موجود'], 404);
        }

        if (!$hospital->trashed()) {
            return response()->json(['message' => 'الحساب ليس محذوفاً.'], 400);
        }

        DB::beginTransaction();

        try {
            $hospital->restore();

            if ($hospital->account) {
                $hospital->account->forceFill(['is_suspended' => false])->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'تم استعادة حساب المشفى.',
                'data' => $hospital->fresh(),
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to restore hospital account: ' . $e->getMessage());

            return response()->json(['message' => 'تعذر استعادة حساب المشفى'], 500);
        }
    }

    /**
     * Remove everything that points at the hospital so a hard delete does not
     * trip the foreign keys.
     */
    private function purgeHospitalDependents(Hospital $hospital): void
    {
        $reservationIds = HospitalServiceReservation::where('hospital_id', $hospital->id)
            ->pluck('id')
            ->all();

        if ($reservationIds !== []) {
            Rating::where('reservationable_type', HospitalServiceReservation::class)
                ->whereIn('reservationable_id', $reservationIds)
                ->delete();

            HospitalServiceReservation::whereIn('id', $reservationIds)->forceDelete();
        }

        HospitalWorkSchedule::where('hospital_id', $hospital->id)->forceDelete();

        $hospital->services()->each(function (HospitalService $service) {
            $service->delete();
        });

        $accountId = $hospital->account_id;

        $hospital->forceDelete();

        if ($accountId) {
            $account = Account::find($accountId);
            $account?->forceDelete();
        }
    }

}
