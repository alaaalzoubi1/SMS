<?php

namespace Tests\Feature\Muafa4;

use App\Models\Account;
use App\Models\AdminNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminReservationNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Account $doctorAcc, $nurseAcc, $hospitalAcc, $patientAcc;
    private int $doctorId, $ds, $nurseId, $ns, $hospitalId, $hs, $staticUser, $realUser;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-19 10:00:00'); // Monday
        foreach (['admin', 'doctor', 'nurse', 'hospital', 'user'] as $r) Role::create(['name' => $r, 'guard_name' => 'api']);
        $mk = function ($email, $role) {
            $a = Account::create(['email' => $email, 'password' => 'x', 'phone_number' => $email, 'is_approved' => 'approved']);
            $a->assignRole($role);
            return $a;
        };
        $mk('admin@x', 'admin');
        $this->doctorAcc = $mk('doc@x', 'doctor');
        $this->nurseAcc = $mk('nurse@x', 'nurse');
        $this->hospitalAcc = $mk('hosp@x', 'hospital');
        $this->patientAcc = $mk('pat@x', 'user');

        DB::table('provinces')->insert(['id' => 1, 'name_ar' => 'حلب', 'name_en' => 'Aleppo']);
        DB::table('specializations')->insert(['id' => 1, 'name_ar' => 'عام', 'name_en' => 'General']);
        // patient created by the admin (no account) + a real patient
        $this->staticUser = DB::table('users')->insertGetId(['account_id' => null, 'full_name' => 'أبو أحمد', 'gender' => 'male', 'age' => 60, 'phone' => '0933111222']);
        $this->realUser = DB::table('users')->insertGetId(['account_id' => $this->patientAcc->id, 'full_name' => 'مريض', 'gender' => 'male', 'age' => 20]);

        $this->doctorId = DB::table('doctors')->insertGetId(['account_id' => $this->doctorAcc->id, 'full_name' => 'د. سالم', 'specialization_id' => 1,
            'province_id' => 1, 'address' => 'aaaaaaaaaaaa', 'gender' => 'male', 'age' => 30, 'birthdate' => '1990-01-01', 'location' => '']);
        $this->ds = DB::table('doctor_services')->insertGetId(['doctor_id' => $this->doctorId, 'name' => 'كشف', 'price' => 1, 'duration_minutes' => 30]);
        DB::table('doctor_work_schedules')->insert(['doctor_id' => $this->doctorId, 'day_of_week' => 'tuesday', 'start_time' => '14:00', 'end_time' => '18:00']);

        $this->nurseId = DB::table('nurses')->insertGetId(['account_id' => $this->nurseAcc->id, 'full_name' => 'رعد', 'province_id' => 1, 'address' => 'x',
            'graduation_type' => 'جامعة', 'license_image_path' => 'x', 'gender' => 'male', 'age' => 25, 'birthdate' => '2000-01-01', 'location' => '']);
        $svc = DB::table('services')->insertGetId(['service_name' => 'حقن', 'service_type' => 'nurse', 'requires_certificate' => 0]);
        $this->ns = DB::table('nurse_services')->insertGetId(['nurse_id' => $this->nurseId, 'service_id' => $svc, 'price' => 3000]);

        $this->hospitalId = DB::table('hospitals')->insertGetId(['account_id' => $this->hospitalAcc->id, 'full_name' => 'مشفى الشفاء', 'unique_code' => 'c',
            'address' => 'x', 'location' => '', 'province_id' => 1, 'reservation_confirmation_deadline' => 6]);
        $hsvc = DB::table('services')->insertGetId(['service_name' => 'سرير', 'service_type' => 'hospital', 'requires_certificate' => 0]);
        $this->hs = DB::table('hospital_services')->insertGetId(['hospital_id' => $this->hospitalId, 'service_id' => $hsvc, 'price' => 8000, 'capacity' => 3, 'description' => 'd']);
    }

    private function forwarded()
    {
        return AdminNotification::where('type', AdminNotification::TYPE_ADMIN_RESERVATION_UPDATE)->get();
    }

    public function test_doctor_update_for_admin_reservation_without_account_goes_to_admin(): void
    {
        $id = DB::table('doctor_reservations')->insertGetId(['user_id' => $this->staticUser, 'doctor_id' => $this->doctorId, 'doctor_service_id' => $this->ds,
            'date' => '2026-10-20', 'status' => 'pending', 'reserved_by_admin' => true]);
        $this->actingAs($this->doctorAcc, 'api')->patchJson("/api/doctor/reservations/{$id}/status", ['status' => 'approved'])->assertOk();

        $n = $this->forwarded()->sole();
        $this->assertSame('doctor', $n->data['reservation_type']);
        $this->assertSame('approved', $n->data['status']);
        $this->assertSame('0933111222', $n->data['patient_phone']);
        $this->assertStringContainsString('من الساعة 14:00 حتى 14:30', $n->body);
        $this->assertStringContainsString('أبو أحمد (0933111222)', $n->body);
    }

    public function test_patient_with_account_is_not_forwarded(): void
    {
        $id = DB::table('doctor_reservations')->insertGetId(['user_id' => $this->realUser, 'doctor_id' => $this->doctorId, 'doctor_service_id' => $this->ds,
            'date' => '2026-10-20', 'status' => 'pending', 'reserved_by_admin' => true]);
        $this->actingAs($this->doctorAcc, 'api')->patchJson("/api/doctor/reservations/{$id}/status", ['status' => 'approved'])->assertOk();
        $this->assertCount(0, $this->forwarded());
    }

    public function test_cancel_remaining_forwards_each(): void
    {
        foreach ([1, 2] as $_) {
            DB::table('doctor_reservations')->insert(['user_id' => $this->staticUser, 'doctor_id' => $this->doctorId, 'doctor_service_id' => $this->ds,
                'date' => '2026-10-20', 'status' => 'pending', 'reserved_by_admin' => true]);
        }
        $this->actingAs($this->doctorAcc, 'api')->patchJson('/api/doctor/reservations/cancel-remaining', ['date' => '2026-10-20', 'reason' => 'طارئ'])->assertOk();
        $this->assertCount(2, $this->forwarded());
        $this->assertSame('cancelled', $this->forwarded()->first()->data['status']);
    }

    public function test_nurse_and_hospital_updates_are_forwarded(): void
    {
        $nid = DB::table('nurse_reservations')->insertGetId(['user_id' => $this->staticUser, 'nurse_id' => $this->nurseId, 'nurse_service_id' => $this->ns,
            'reservation_type' => 'direct', 'price' => 3000, 'location' => '', 'status' => 'pending', 'reserved_by_admin' => true]);
        $this->actingAs($this->nurseAcc, 'api')->patchJson("/api/nurse/reservations/{$nid}/status", ['status' => 'rejected', 'reason' => 'بعيد'])->assertOk();

        $hid = DB::table('hospital_service_reservations')->insertGetId(['user_id' => $this->staticUser, 'hospital_service_id' => $this->hs,
            'hospital_id' => $this->hospitalId, 'unit_price' => 8000, 'status' => 'pending', 'reserved_by_admin' => true]);
        $this->actingAs($this->hospitalAcc, 'api')->patchJson("/api/hospital/reservations/{$hid}/status", ['status' => 'accepted'])->assertOk();

        $byType = $this->forwarded()->keyBy(fn ($n) => $n->data['reservation_type']);
        $this->assertSame('rejected', $byType['nurse']->data['status']);
        $this->assertStringContainsString('السبب: بعيد', $byType['nurse']->body);
        $this->assertSame('accepted', $byType['hospital']->data['status']);
        $this->assertSame('مشفى الشفاء', $byType['hospital']->data['provider_name']);

        // not admin-made + no account → nothing forwarded
        $nid2 = DB::table('nurse_reservations')->insertGetId(['user_id' => $this->staticUser, 'nurse_id' => $this->nurseId, 'nurse_service_id' => $this->ns,
            'reservation_type' => 'direct', 'price' => 3000, 'location' => '', 'status' => 'pending', 'reserved_by_admin' => false]);
        $this->actingAs($this->nurseAcc, 'api')->patchJson("/api/nurse/reservations/{$nid2}/status", ['status' => 'accepted'])->assertOk();
        $this->assertCount(2, $this->forwarded());
    }
}
