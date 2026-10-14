<?php

namespace Tests\Feature\Muafa4;

use App\Models\Account;
use App\Models\DoctorReservation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VerifyFlowsTest extends TestCase
{
    use RefreshDatabase;

    private Account $doctorAcc; private Account $userAcc; private int $doctorId; private int $userId; private int $svcId;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin','doctor','user','nurse','hospital'] as $r) Role::create(['name' => $r, 'guard_name' => 'api']);
        DB::table('provinces')->insert(['id' => 1, 'name_ar' => 'حلب', 'name_en' => 'Aleppo']);
        DB::table('specializations')->insert(['id' => 1, 'name_ar' => 'عام', 'name_en' => 'General']);
        $this->doctorAcc = Account::create(['email' => 'd@x.com', 'password' => bcrypt('secret123'), 'phone_number' => '1', 'is_approved' => 'approved']);
        $this->doctorAcc->assignRole('doctor');
        $this->doctorId = DB::table('doctors')->insertGetId(['account_id' => $this->doctorAcc->id, 'full_name' => 'Dr', 'specialization_id' => 1,
            'province_id' => 1, 'address' => 'aaaaaaaaaaaa', 'gender' => 'male', 'age' => 30, 'birthdate' => '1990-01-01', 'location' => '']);
        $this->svcId = DB::table('doctor_services')->insertGetId(['doctor_id' => $this->doctorId, 'name' => 'كشف', 'price' => 5000, 'duration_minutes' => 30]);
        foreach (['friday','saturday','sunday','monday','tuesday','wednesday','thursday'] as $d)
            DB::table('doctor_work_schedules')->insert(['doctor_id' => $this->doctorId, 'day_of_week' => $d, 'start_time' => '14:00', 'end_time' => '18:00']);
        $this->userAcc = Account::create(['email' => 'u@x.com', 'password' => bcrypt('secret123'), 'phone_number' => '2', 'is_approved' => 'approved', 'fcm_token' => 'tok']);
        $this->userAcc->assignRole('user');
        $this->userId = DB::table('users')->insertGetId(['account_id' => $this->userAcc->id, 'full_name' => 'مريض', 'gender' => 'male', 'age' => 20]);
        Carbon::setTestNow('2026-10-16 10:00:00');
    }

    private function res(string $status, ?string $start = null): DoctorReservation
    {
        return DoctorReservation::create(['user_id' => $this->userId, 'doctor_id' => $this->doctorId, 'doctor_service_id' => $this->svcId,
            'date' => '2026-10-16', 'status' => $status, 'start_time' => $start, 'end_time' => $start ? '14:30:00' : null]);
    }

    public function test_cancel_remaining_includes_approved(): void
    {
        $a = $this->res('pending'); $b = $this->res('approved', '14:00:00'); $c = $this->res('completed', '14:00:00');
        $r = $this->actingAs($this->doctorAcc, 'api')->patchJson('/api/doctor/reservations/cancel-remaining', ['date' => '2026-10-16', 'reason' => 'طارئ']);
        $r->assertOk()->assertJson(['cancelled_count' => 2]);
        $this->assertSame('cancelled', $a->fresh()->status);
        $this->assertSame('cancelled', $b->fresh()->status);
        $this->assertSame('completed', $c->fresh()->status);
    }

    public function test_doctor_can_cancel_approved(): void
    {
        $b = $this->res('approved', '14:00:00');
        $this->actingAs($this->doctorAcc, 'api')->patchJson("/api/doctor/reservations/{$b->id}/status", ['status' => 'cancelled', 'reason' => 'طارئ'])->assertOk();
        $this->assertSame('cancelled', $b->fresh()->status);
    }

    public function test_force_confirm_accepts_after_hours(): void
    {
        Carbon::setTestNow('2026-10-16 20:00:00');
        $a = $this->res('pending');
        $this->actingAs($this->doctorAcc, 'api')->patchJson("/api/doctor/reservations/{$a->id}/status", ['status' => 'approved'])->assertStatus(409);
        $r = $this->actingAs($this->doctorAcc, 'api')->patchJson("/api/doctor/reservations/{$a->id}/status", ['status' => 'approved', 'force_confirm' => true]);
        $r->assertOk()->assertJson(['outside_working_hours' => true]);
        $this->assertSame('approved', $a->fresh()->status);
        $this->assertSame('20:00:00', $a->fresh()->start_time);
    }

    public function test_user_cancel_doctor_reservation_arabic(): void
    {
        $a = $this->res('pending');
        $this->actingAs($this->userAcc, 'api')->patchJson('/api/user/doctors/reservation/cancel', ['reservation_id' => $a->id, 'reason' => 'سبب'])->assertOk();
        $r = $this->actingAs($this->userAcc, 'api')->patchJson('/api/user/doctors/reservation/cancel', ['reservation_id' => 999, 'reason' => 'سبب']);
        $r->assertStatus(422);
        $this->assertSame('الحجز غير موجود.', $r->json('errors.reservation_id.0'));
        $r = $this->actingAs($this->userAcc, 'api')->patchJson('/api/user/doctors/reservation/cancel', ['reservation_id' => $a->id, 'reason' => 'سبب']);
        $r->assertStatus(422);
        $this->assertSame('لا يمكن إلغاء هذا الحجز في حالته الحالية.', $r->json('message'));
    }

    public function test_reserve_other_doctor_not_blocked(): void
    {
        $this->res('pending');
        $acc2 = Account::create(['email' => 'd2@x.com', 'password' => 'x', 'phone_number' => '9', 'is_approved' => 'approved']);
        $doc2 = DB::table('doctors')->insertGetId(['account_id' => $acc2->id, 'full_name' => 'Dr2', 'specialization_id' => 1,
            'province_id' => 1, 'address' => 'aaaaaaaaaaaa', 'gender' => 'male', 'age' => 30, 'birthdate' => '1990-01-01', 'location' => '']);
        $svc2 = DB::table('doctor_services')->insertGetId(['doctor_id' => $doc2, 'name' => 'كشف', 'price' => 5000, 'duration_minutes' => 30]);
        DB::table('doctor_work_schedules')->insert(['doctor_id' => $doc2, 'day_of_week' => 'saturday', 'start_time' => '14:00', 'end_time' => '18:00']);
        $r = $this->actingAs($this->userAcc, 'api')->postJson('/api/user/doctors/reserve', ['doctor_id' => $doc2, 'doctor_service_id' => $svc2, 'date' => '2026-10-17']);
        $r->assertStatus(201);
        $r = $this->actingAs($this->userAcc, 'api')->postJson('/api/user/doctors/reserve', ['doctor_id' => $this->doctorId, 'doctor_service_id' => $this->svcId, 'date' => '2026-10-17']);
        $r->assertOk()->assertJson(['requires_confirmation' => true]);
    }

    public function test_login_remember_me_and_fcm(): void
    {
        $r = $this->postJson('/api/doctor/login', ['email' => 'd@x.com', 'password' => 'secret123', 'fcm_token' => 'newtok', 'remember_me' => true]);
        $r->assertOk()->assertJson(['expires_in' => 43200 * 60]);
        $this->assertSame('newtok', $this->doctorAcc->fresh()->fcm_token);
        $payload = \Tymon\JWTAuth\Facades\JWTAuth::setToken($r->json('token'))->getPayload();
        $this->assertGreaterThan(now()->addDays(29)->timestamp, $payload->get('exp'));
    }

    public function test_doctor_statistics(): void
    {
        $this->res('completed', '14:00:00'); $this->res('pending');
        $r = $this->actingAs($this->doctorAcc, 'api')->getJson('/api/doctor/statistics?range=all');
        $r->assertOk()->assertJsonPath('data.total_reservations', 2)->assertJsonPath('data.completed_count', 1)->assertJsonPath('data.total_revenue', 5000);
    }
}
