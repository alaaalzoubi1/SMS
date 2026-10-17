<?php

namespace Tests\Feature\Muafa4;

use App\Models\Account;
use App\Models\AdminNotification;
use App\Models\DoctorReservation;
use App\Services\DoctorReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HospitalTypesAndCountdownTest extends TestCase
{
    use RefreshDatabase;

    private Account $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-17 10:00:00');
        foreach (['admin', 'user', 'hospital', 'doctor'] as $r) Role::create(['name' => $r, 'guard_name' => 'api']);
        $this->admin = Account::create(['email' => 'admin@a.com', 'password' => 'x', 'phone_number' => '0', 'is_approved' => 'approved']);
        $this->admin->assignRole('admin');
        DB::table('provinces')->insert(['id' => 1, 'name_ar' => 'حلب', 'name_en' => 'Aleppo']);
    }

    public function test_completed_reservation_blocks_its_slot(): void
    {
        DB::table('specializations')->insert(['id' => 1, 'name_ar' => 'عام', 'name_en' => 'General']);
        $acc = DB::table('accounts')->insertGetId(['email' => 'd@x.com', 'password' => 'x', 'phone_number' => '1']);
        $doc = DB::table('doctors')->insertGetId(['account_id' => $acc, 'full_name' => 'Dr', 'specialization_id' => 1, 'province_id' => 1,
            'address' => 'aaaaaaaaaaaa', 'gender' => 'male', 'age' => 30, 'birthdate' => '1990-01-01', 'location' => '']);
        $svc = DB::table('doctor_services')->insertGetId(['doctor_id' => $doc, 'name' => 'كشف', 'price' => 1, 'duration_minutes' => 30]);
        DB::table('doctor_work_schedules')->insert(['doctor_id' => $doc, 'day_of_week' => 'friday', 'start_time' => '14:00', 'end_time' => '18:00']);
        $user = DB::table('users')->insertGetId(['full_name' => 'u', 'gender' => 'male', 'age' => 1]);
        DoctorReservation::create(['user_id' => $user, 'doctor_id' => $doc, 'doctor_service_id' => $svc, 'date' => '2026-10-23',
            'status' => 'completed', 'start_time' => '14:00:00', 'end_time' => '14:30:00']);
        DoctorReservation::create(['user_id' => $user, 'doctor_id' => $doc, 'doctor_service_id' => $svc, 'date' => '2026-10-23',
            'status' => 'approved', 'start_time' => '14:30:00', 'end_time' => '15:00:00']);

        $slot = (new DoctorReservationService())->findSlotForApproval($doc, '2026-10-23', 30);
        $this->assertSame('15:00', $slot['start_time']->format('H:i'));
    }

    public function test_hospital_type_crud_public_list_and_filter(): void
    {
        $r = $this->actingAs($this->admin, 'api')->postJson('/api/admin/hospital-types', ['name_ar' => 'مشفى خاص', 'name_en' => 'Private']);
        $r->assertStatus(201);
        $private = $r->json('data.id');
        $public = $this->actingAs($this->admin, 'api')->postJson('/api/admin/hospital-types', ['name_ar' => 'مشفى عام'])->json('data.id');
        $this->actingAs($this->admin, 'api')->postJson('/api/admin/hospital-types', ['name_ar' => 'مشفى خاص'])->assertStatus(422);
        $this->actingAs($this->admin, 'api')->putJson("/api/admin/hospital-types/{$public}", ['name_en' => 'Public'])->assertOk()->assertJsonPath('data.name_en', 'Public');

        $mk = function ($name, $type) {
            $acc = Account::create(['email' => "$name@h.com", 'password' => 'x', 'phone_number' => $name, 'is_approved' => 'approved']);
            return DB::table('hospitals')->insertGetId(['account_id' => $acc->id, 'full_name' => $name, 'unique_code' => $name,
                'address' => 'x', 'location' => '', 'province_id' => 1, 'hospital_type_id' => $type]);
        };
        $mk('H1', $private); $mk('H2', $private); $mk('H3', $public);

        $types = $this->getJson('/api/hospital-types')->assertOk()->json('data');
        $byId = collect($types)->keyBy('id');
        $this->assertSame(2, $byId[$private]['hospitals_count']);
        $this->assertSame(1, $byId[$public]['hospitals_count']);

        $u = Account::create(['email' => 'u@x.com', 'password' => 'x', 'phone_number' => '99', 'is_approved' => 'approved']);
        $u->assignRole('user');
        DB::table('users')->insert(['account_id' => $u->id, 'full_name' => 'u', 'gender' => 'male', 'age' => 20]);
        $r = $this->actingAs($u, 'api')->getJson("/api/user/hospitals?hospital_type_id={$private}");
        $r->assertOk();
        $this->assertSame(2, $r->json('total'));
        $this->assertSame('مشفى خاص', $r->json('data.0.hospital_type.name_ar'));

        $this->actingAs($this->admin, 'api')->deleteJson("/api/admin/hospital-types/{$public}")->assertOk()->assertJson(['linked_hospitals_count' => 1]);
        $this->assertCount(1, $this->getJson('/api/hospital-types')->json('data'));
        $this->actingAs($this->admin, 'api')->patchJson("/api/admin/hospital-types/{$public}/restore")->assertOk();
    }

    public function test_registration_requires_type(): void
    {
        $acc = Account::create(['email' => 'tmp@h.com', 'password' => '', 'phone_number' => 't', 'is_approved' => 'pending']);
        $acc->assignRole('hospital');
        DB::table('hospitals')->insert(['account_id' => $acc->id, 'full_name' => 'مشفى الشفاء', 'unique_code' => 'CODE1',
            'address' => '', 'location' => '', 'province_id' => 1]);

        $payload = ['hospital_name' => 'مشفى الشفاء', 'unique_code' => 'CODE1', 'address' => 'شارع النيل', 'email' => 'h@h.com', 'phone_number' => '0911',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'latitude' => 36.2, 'longitude' => 37.1,
            'province_id' => 1, 'reservation_confirmation_deadline' => 6];
        $this->postJson('/api/hospital/register', $payload)->assertStatus(422)->assertJsonValidationErrors(['hospital_type_id']);
        $this->postJson('/api/hospital/register', $payload + ['hospital_type_id' => 999])->assertStatus(422)->assertJsonValidationErrors(['hospital_type_id']);
    }

    public function test_accept_starts_countdown_and_admin_notification_has_status(): void
    {
        $acc = Account::create(['email' => 'h@h.com', 'password' => 'x', 'phone_number' => '0911', 'is_approved' => 'approved']);
        $acc->assignRole('hospital');
        $h = DB::table('hospitals')->insertGetId(['account_id' => $acc->id, 'full_name' => 'Hosp', 'unique_code' => 'c', 'address' => 'x',
            'location' => '', 'province_id' => 1, 'reservation_confirmation_deadline' => 6]);
        $svc = DB::table('services')->insertGetId(['service_name' => 'سرير', 'service_type' => 'hospital', 'requires_certificate' => 0]);
        $hs = DB::table('hospital_services')->insertGetId(['hospital_id' => $h, 'service_id' => $svc, 'price' => 8000, 'capacity' => 3, 'description' => 'd']);
        $u = Account::create(['email' => 'u@x.com', 'password' => 'x', 'phone_number' => '0999', 'is_approved' => 'approved']);
        $u->assignRole('user');
        DB::table('users')->insert(['account_id' => $u->id, 'full_name' => 'مريض', 'gender' => 'female', 'age' => 30]);

        $id = $this->actingAs($u, 'api')->postJson('/api/user/hospitals/make-reservation', ['hospital_service_id' => $hs])->assertOk()->json('reservation_id');
        $n = AdminNotification::first();
        $this->assertStringContainsString('قيد الانتظار', $n->title);
        $this->assertStringContainsString('الحالة: قيد الانتظار', $n->body);
        $this->assertSame('pending', $n->data['status']);
        $this->assertSame(3, $n->data['available_beds']);

        $this->actingAs($acc, 'api')->patchJson("/api/hospital/reservations/{$id}/status", ['status' => 'accepted'])
            ->assertOk()->assertJsonPath('reservation.confirmation_remaining_seconds', 6 * 3600);

        Carbon::setTestNow('2026-10-17 12:00:00'); // 2h later
        $r = $this->actingAs($u, 'api')->getJson('/api/user/my-reservations?type=hospital')->assertOk();
        $this->assertSame(4 * 3600, $r->json('data.hospital_reservations.0.confirmation_remaining_seconds'));
        $this->assertFalse($r->json('data.hospital_reservations.0.confirmation_expired'));

        Carbon::setTestNow('2026-10-17 17:00:00');
        $r = $this->actingAs($u, 'api')->getJson('/api/user/my-reservations?type=hospital');
        $this->assertSame(0, $r->json('data.hospital_reservations.0.confirmation_remaining_seconds'));
        $this->assertTrue($r->json('data.hospital_reservations.0.confirmation_expired'));
    }
}
