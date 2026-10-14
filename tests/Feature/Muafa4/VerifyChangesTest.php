<?php

namespace Tests\Feature\Muafa4;

use App\Models\Account;
use App\Services\DoctorReservationService;
use App\Support\SubscriptionTrial;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VerifyChangesTest extends TestCase
{
    use RefreshDatabase;

    private function seedDoctor(): int
    {
        DB::table('provinces')->insert(['id' => 1, 'name_ar' => 'حلب', 'name_en' => 'Aleppo']);
        DB::table('specializations')->insert(['id' => 1, 'name_ar' => 'عام', 'name_en' => 'General']);
        $accId = DB::table('accounts')->insertGetId(['email' => 'd@x.com', 'password' => 'x', 'phone_number' => '1']);
        $docId = DB::table('doctors')->insertGetId([
            'account_id' => $accId, 'full_name' => 'Dr', 'specialization_id' => 1, 'province_id' => 1,
            'address' => 'aaaaaaaaaaaa', 'gender' => 'male', 'age' => 30, 'birthdate' => '1990-01-01', 'location' => DB::raw("''"),
        ]);
        DB::table('doctor_work_schedules')->insert(['doctor_id' => $docId, 'day_of_week' => 'friday', 'start_time' => '14:00', 'end_time' => '18:00']);
        return $docId;
    }

    public function test_validation_messages_are_arabic(): void
    {
        $v = Validator::make([], ['reservation_id' => 'required']);
        $this->assertSame('حقل رقم الحجز مطلوب.', $v->errors()->first());
    }

    public function test_force_slot_after_hours_is_accepted(): void
    {
        $docId = $this->seedDoctor();
        Carbon::setTestNow('2026-10-16 20:30:00'); // a Friday, after 18:00
        $svc = new DoctorReservationService();

        $this->assertNull($svc->findSlotForApproval($docId, '2026-10-16', 30));
        $slot = $svc->forceSlotForApproval($docId, '2026-10-16', 30);
        $this->assertSame('20:30', $slot['start_time']->format('H:i'));
        $this->assertSame('21:00', $slot['end_time']->format('H:i'));
        $this->assertTrue($slot['outside_working_hours']);
    }

    public function test_find_slot_today_skips_past_time(): void
    {
        $docId = $this->seedDoctor();
        Carbon::setTestNow('2026-10-16 15:10:00');
        $slot = (new DoctorReservationService())->findSlotForApproval($docId, '2026-10-16', 30);
        $this->assertSame('15:10', $slot['start_time']->format('H:i'));

        // future friday: from opening time
        $slot = (new DoctorReservationService())->findSlotForApproval($docId, '2026-10-23', 30);
        $this->assertSame('14:00', $slot['start_time']->format('H:i'));
    }

    public function test_trial_anchor(): void
    {
        Role::create(['name' => 'doctor', 'guard_name' => 'api']);
        Role::create(['name' => 'user', 'guard_name' => 'api']);
        Carbon::setTestNow('2026-10-13 10:00:00');
        $acc = Account::create(['email' => 'a@a.com', 'password' => 'x', 'phone_number' => '2',
            'subscription_expires_at' => SubscriptionTrial::expiryFromNow()]);
        $acc->assignRole('doctor');
        Carbon::setTestNow('2026-10-20 10:00:00');
        SubscriptionTrial::anchorToApproval($acc);
        $this->assertSame('2026-12-04 10:00:00', $acc->subscription_expires_at->toDateTimeString());
        // admin gave longer: kept
        $acc->subscription_expires_at = '2027-06-01 00:00:00';
        SubscriptionTrial::anchorToApproval($acc);
        $this->assertSame('2027-06-01 00:00:00', $acc->subscription_expires_at->toDateTimeString());
        $this->assertSame('2027-06-01 00:00:00', $acc->toArray()['subscription_expires_at']);
    }

    public function test_toggle_suspension_accepts_string_booleans(): void
    {
        Role::create(['name' => 'admin', 'guard_name' => 'api']);
        $admin = Account::create(['email' => 'admin@a.com', 'password' => bcrypt('x'), 'phone_number' => '3', 'is_approved' => 'approved']);
        $admin->assignRole('admin');
        $target = Account::create(['email' => 't@a.com', 'password' => 'x', 'phone_number' => '4']);

        $this->actingAs($admin, 'api')
            ->patchJson('/api/admin/toggleSuspension', ['account_id' => $target->id, 'is_suspended' => 'true'])
            ->assertOk()->assertJson(['is_suspended' => true]);
        $this->actingAs($admin, 'api')
            ->patchJson('/api/admin/toggleSuspension', ['account_id' => $target->id])
            ->assertOk()->assertJson(['is_suspended' => false]);
    }

    public function test_admin_endpoints_smoke(): void
    {
        Role::create(['name' => 'admin', 'guard_name' => 'api']);
        $admin = Account::create(['email' => 'admin@a.com', 'password' => bcrypt('x'), 'phone_number' => '3', 'is_approved' => 'approved']);
        $admin->assignRole('admin');
        $this->seedDoctor();

        foreach ([
            '/api/admin/doctor/all?subscription=lifetime&province_id=1&is_suspended=0',
            '/api/admin/nurse/all?province_id=1&service_name=x',
            '/api/admin/hospital/all?province_id=1',
            '/api/admin/user/all?is_suspended=false',
            '/api/admin/notifications',
            '/api/admin/reports/providers?type=doctor&province_id=1',
            '/api/admin/reports/providers?type=nurse',
            '/api/admin/reports/providers?type=hospital&range=month',
            '/api/admin/nurse/services/search?name=x',
            '/api/admin/doctor/1/statistics',
            '/api/admin/doctor/1',
        ] as $url) {
            $res = $this->actingAs($admin, 'api')->getJson($url);
            $this->assertSame(200, $res->getStatusCode(), $url . ' => ' . $res->getContent());
        }

        $res = $this->actingAs($admin, 'api')->get('/api/admin/reports/providers?type=doctor&format=csv');
        $this->assertSame(200, $res->getStatusCode());
        $this->assertStringContainsString('الاسم', $res->streamedContent());

        $res = $this->actingAs($admin, 'api')->deleteJson('/api/admin/doctor/1');
        $this->assertSame(200, $res->getStatusCode(), $res->getContent());
        $this->assertSoftDeleted('doctors', ['id' => 1]);
        $res = $this->actingAs($admin, 'api')->patchJson('/api/admin/doctor/1/restore');
        $this->assertSame(200, $res->getStatusCode(), $res->getContent());
        $this->assertNotSoftDeleted('doctors', ['id' => 1]);
    }
}
