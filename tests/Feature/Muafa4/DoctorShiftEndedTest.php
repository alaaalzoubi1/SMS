<?php

namespace Tests\Feature\Muafa4;

use App\Models\Account;
use App\Models\DoctorReservation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Doctor works 13:00–14:00. A patient asking at 18:00 for *today* must be
 * refused and pointed to the next working day.
 */
class DoctorShiftEndedTest extends TestCase
{
    use RefreshDatabase;

    private Account $userAcc;
    private Account $adminAcc;
    private int $doctorId;
    private int $svcId;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'doctor', 'user', 'nurse', 'hospital'] as $r) {
            Role::create(['name' => $r, 'guard_name' => 'api']);
        }
        DB::table('provinces')->insert(['id' => 1, 'name_ar' => 'حلب', 'name_en' => 'Aleppo']);
        DB::table('specializations')->insert(['id' => 1, 'name_ar' => 'عام', 'name_en' => 'General']);

        $doc = Account::create(['email' => 'd@x.com', 'password' => bcrypt('secret123'), 'phone_number' => '1', 'is_approved' => 'approved']);
        $doc->assignRole('doctor');
        $this->doctorId = DB::table('doctors')->insertGetId(['account_id' => $doc->id, 'full_name' => 'Dr', 'specialization_id' => 1,
            'province_id' => 1, 'address' => 'aaaaaaaaaaaa', 'gender' => 'male', 'age' => 30, 'birthdate' => '1990-01-01', 'location' => '']);
        $this->svcId = DB::table('doctor_services')->insertGetId(['doctor_id' => $this->doctorId, 'name' => 'كشف', 'price' => 5000, 'duration_minutes' => 30]);

        // 2026-10-16 is a Friday. Works Friday and Sunday 13:00–14:00 (not Saturday).
        foreach (['friday', 'sunday'] as $d) {
            DB::table('doctor_work_schedules')->insert(['doctor_id' => $this->doctorId, 'day_of_week' => $d, 'start_time' => '13:00', 'end_time' => '14:00']);
        }

        $this->userAcc = Account::create(['email' => 'u@x.com', 'password' => bcrypt('secret123'), 'phone_number' => '2', 'is_approved' => 'approved']);
        $this->userAcc->assignRole('user');
        DB::table('users')->insert(['account_id' => $this->userAcc->id, 'full_name' => 'مريض', 'gender' => 'male', 'age' => 20]);

        $this->adminAcc = Account::create(['email' => 'a@x.com', 'password' => bcrypt('secret123'), 'phone_number' => '3', 'is_approved' => 'approved']);
        $this->adminAcc->assignRole('admin');
    }

    private function reserve(string $date)
    {
        return $this->actingAs($this->userAcc, 'api')->postJson('/api/user/doctors/reserve', [
            'doctor_id' => $this->doctorId, 'doctor_service_id' => $this->svcId, 'date' => $date,
        ]);
    }

    public function test_today_after_shift_is_refused_with_next_date(): void
    {
        Carbon::setTestNow('2026-10-16 18:00:00');

        $r = $this->reserve('2026-10-16');

        $r->assertStatus(422)
            ->assertJson(['reason' => 'shift_ended', 'next_available_date' => '2026-10-18']);
        $this->assertStringContainsString('انتهى دوام الطبيب لهذا اليوم', $r->json('message'));
        $this->assertSame(0, DoctorReservation::count());
    }

    public function test_exactly_at_shift_end_is_refused(): void
    {
        Carbon::setTestNow('2026-10-16 14:00:00');
        $this->reserve('2026-10-16')->assertStatus(422)->assertJson(['reason' => 'shift_ended']);
    }

    public function test_before_and_during_shift_is_accepted(): void
    {
        Carbon::setTestNow('2026-10-16 10:00:00');
        $this->reserve('2026-10-16')->assertCreated();

        Carbon::setTestNow('2026-10-16 13:30:00');
        DoctorReservation::query()->delete(); // avoid the "already pending" confirmation
        $this->reserve('2026-10-16')->assertCreated();
    }

    public function test_future_working_day_is_accepted_after_todays_shift(): void
    {
        Carbon::setTestNow('2026-10-16 18:00:00');
        $this->reserve('2026-10-18')->assertCreated();
    }

    public function test_non_working_day_still_refused_and_suggests_next(): void
    {
        Carbon::setTestNow('2026-10-16 10:00:00');
        $this->reserve('2026-10-17') // Saturday: day off
            ->assertStatus(422)
            ->assertJson(['reason' => 'not_working_day', 'next_available_date' => '2026-10-18']);
    }

    public function test_overnight_shift_is_not_ended_before_midnight(): void
    {
        DB::table('doctor_work_schedules')->where('doctor_id', $this->doctorId)->where('day_of_week', 'friday')
            ->update(['start_time' => '22:00', 'end_time' => '02:00']);
        Carbon::setTestNow('2026-10-16 23:00:00');
        $this->reserve('2026-10-16')->assertCreated();
    }

    public function test_available_dates_drop_today_after_shift(): void
    {
        Carbon::setTestNow('2026-10-16 10:00:00');
        $before = $this->actingAs($this->userAcc, 'api')
            ->getJson("/api/user/doctors/{$this->doctorId}/available-dates?month=10&year=2026")->assertOk()->json('available_dates');
        $this->assertContains('2026-10-16', $before);

        Carbon::setTestNow('2026-10-16 18:00:00');
        $after = $this->actingAs($this->userAcc, 'api')
            ->getJson("/api/user/doctors/{$this->doctorId}/available-dates?month=10&year=2026")->assertOk()->json('available_dates');
        $this->assertNotContains('2026-10-16', $after);
        $this->assertContains('2026-10-18', $after);
    }

    public function test_admin_booking_today_after_shift_is_refused_and_creates_no_patient(): void
    {
        Carbon::setTestNow('2026-10-16 18:00:00');
        $users = DB::table('users')->count();

        $this->actingAs($this->adminAcc, 'api')->postJson('/api/admin/doctor/reserve', [
            'doctor_id' => $this->doctorId, 'doctor_service_id' => $this->svcId, 'date' => '2026-10-16',
            'user_data' => ['full_name' => 'زائر', 'birthdate' => '1990-01-01', 'gender' => 'male', 'phone' => '0999'],
        ])->assertStatus(422)->assertJson(['reason' => 'shift_ended', 'next_available_date' => '2026-10-18']);

        $this->assertSame($users, DB::table('users')->count());
        $this->assertSame(0, DoctorReservation::count());
    }
}
