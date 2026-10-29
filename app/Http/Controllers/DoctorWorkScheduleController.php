<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\DoctorWorkSchedule;
use App\Http\Requests\StoreDoctorWorkScheduleRequest;
use App\Http\Requests\UpdateDoctorWorkScheduleRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
class DoctorWorkScheduleController extends Controller
{
    use AuthorizesRequests;
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDoctorWorkScheduleRequest $request): JsonResponse
    {
        $doctor = Doctor::where('account_id', auth()->id())->first();

        if (!$doctor) {
            return response()->json(['message' => 'الطبيب غير موجود.'], 404);
        }
        $validateData = $request->validated();
        $schedule = DoctorWorkSchedule::create([
            'doctor_id' => $doctor->id,
            'day_of_week' => strtolower($validateData['day_of_week']),
            'start_time' => $validateData['start_time'],
            'end_time' => $validateData['end_time']
        ]);

        return response()->json([
            'message' => 'تمت إضافة الدوام بنجاح.',
            'data'    => $schedule
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     * @throws AuthorizationException
     */
    public function update(UpdateDoctorWorkScheduleRequest $request, $id): JsonResponse
    {
        $schedule = DoctorWorkSchedule::findOrFail($id);
        $this->authorize('all', $schedule);

        $data = $request->validated();

        if (isset($data['day_of_week'])) {
            $data['day_of_week'] = strtolower($data['day_of_week']);
        }

        $schedule->fill($data);

        if (!$schedule->isDirty()) {
            return response()->json(['message' => 'لا توجد تغييرات على الدوام.']);
        }

        $schedule->save();

        return response()->json([
            'message' => 'تم تعديل الدوام بنجاح.',
            'data' => $schedule->fresh()
        ]);

    }

    /**
     * Remove the specified resource from storage.
     * @throws AuthorizationException
     */
    public function destroy($id): JsonResponse
    {
        $schedule = DoctorWorkSchedule::findOrFail($id);
        $this->authorize('all', $schedule);
        $schedule->delete();

        return response()->json([
            'message' => 'تم حذف الدوام بنجاح.'
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    public function restore($id): JsonResponse
    {
        $schedule = DoctorWorkSchedule::withTrashed()->findOrFail($id);

        // Get the authenticated doctor
        $doctor = Doctor::where('account_id', auth()->id())->firstOrFail();

        // Authorization check (assuming policy is implemented)
        $this->authorize('all', $schedule);

        // Check for duplicates: same doctor_id and day_of_week, not soft deleted
        $dayAlreadyTaken = DoctorWorkSchedule::where('doctor_id', $doctor->id)
            ->where('day_of_week', $schedule->day_of_week)
            ->whereNull('deleted_at')
            ->exists();

        if ($dayAlreadyTaken) {
            return response()->json([
                'message' => 'لديك دوام مسجل في هذا اليوم بالفعل.'
            ], 409);
        }

        // Restore if it's soft deleted
        if ($schedule->trashed()) {
            $schedule->restore();

            return response()->json([
                'message' => 'تمت استعادة الدوام بنجاح.',
                'data' => $schedule
            ]);
        }

        return response()->json([
            'message' => 'هذا الدوام غير محذوف.'
        ], 400);
    }


    public function trashed(): JsonResponse
    {
        $doctor = Doctor::where('account_id', auth()->id())->first();

        if (!$doctor) {
            return response()->json(['message' => 'الطبيب غير موجود.'], 404);
        }
        $trashedSchedules = DoctorWorkSchedule::onlyTrashed()->where('doctor_id',$doctor->id)->get();
        return response()->json([
            'message' => 'تم جلب أيام الدوام المحذوفة بنجاح.',
            'data' => $trashedSchedules
        ]);
    }

    public function mySchedules(): JsonResponse
    {
        $doctor = Doctor::where('account_id', auth()->id())->first();

        if (!$doctor) {
            return response()->json(['message' => 'الطبيب غير موجود.'], 404);
        }

        $schedules = DoctorWorkSchedule::where('doctor_id', $doctor->id)
            ->get();

        return response()->json([
            'message' => 'تم جلب أوقات الدوام بنجاح.',
            'data' => $schedules
        ]);
    }


    public function getAvailableDates(Request $request, $doctorId): JsonResponse
    {
        $doctor = Doctor::find($doctorId);
        if (!$doctor) {
            return response()->json(['message' => 'الطبيب غير موجود.'], 404);
        }

        $month = (int) $request->query('month', now()->month);
        $year  = (int) $request->query('year', now()->year);

        $now = now()->startOfDay();

        // بداية الشهر المطلوب
        if (!checkdate($month, 1, $year)) {
            return response()->json([
                'message' => 'الشهر أو السنة غير صحيح.'
            ], 422);
        }

        $requestedMonthStart = Carbon::createFromDate($year, $month, 1)->startOfDay();        $requestedMonthEnd   = $requestedMonthStart->copy()->endOfMonth();

        if ($requestedMonthEnd->lt($now)) {
            return response()->json([
                'message' => 'لا يمكن عرض مواعيد الأشهر السابقة.'
            ], 422);
        }

        $startDate = $requestedMonthStart->isSameMonth($now)
            ? $now->copy()
            : $requestedMonthStart->copy();

        // Stored rows may carry any casing, so normalize before matching.
        $workDays = $doctor->doctorWorkSchedule()
            ->pluck('day_of_week')
            ->map(fn ($day) => strtolower(trim($day)))
            ->toArray();

        $dayNameToNum = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];

        $workDayNums = array_map(fn ($d) => $dayNameToNum[$d] ?? null, $workDays);
        $workDayNums = array_filter($workDayNums, fn ($d) => $d !== null);

        $dates = [];

        $reservations = app(\App\Services\DoctorReservationService::class);

        for ($date = $startDate->copy(); $date->lte($requestedMonthEnd); $date->addDay()) {
            if (in_array($date->dayOfWeek, $workDayNums)) {
                // Today stops being offered once the doctor's shift is over.
                if ($date->isToday() && $reservations->shiftEndedToday($doctor->id, $date->toDateString())) {
                    continue;
                }
                $dates[] = $date->toDateString();
            }
        }

        return response()->json([
            'doctor_id' => $doctor->id,
            'month' => $month,
            'year' => $year,
            'available_dates' => $dates,
        ]);
    }
}
