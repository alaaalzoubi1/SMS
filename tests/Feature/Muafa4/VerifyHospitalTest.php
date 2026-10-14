<?php

namespace Tests\Feature\Muafa4;

use App\Models\Account;
use App\Models\AdminNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VerifyHospitalTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_vacancy(): void
    {
        foreach (['admin','user','hospital'] as $r) Role::create(['name' => $r, 'guard_name' => 'api']);
        DB::table('provinces')->insert(['id' => 1, 'name_ar' => 'حلب', 'name_en' => 'Aleppo']);
        $svc = DB::table('services')->insertGetId(['service_name' => 'سرير', 'service_type' => 'hospital', 'requires_certificate' => 0]);
        $mk = function ($name, $cap, $phone) use ($svc) {
            $acc = Account::create(['email' => $name.'@h.com', 'password' => 'x', 'phone_number' => $phone, 'is_approved' => 'approved']);
            $acc->assignRole('hospital');
            $h = DB::table('hospitals')->insertGetId(['account_id' => $acc->id, 'full_name' => $name, 'unique_code' => $name, 'address' => 'x', 'location' => '', 'province_id' => 1]);
            return DB::table('hospital_services')->insertGetId(['hospital_id' => $h, 'service_id' => $svc, 'price' => 8000, 'capacity' => $cap, 'description' => 'd']);
        };
        $full = $mk('Full', 0, '111'); $mk('Free', 3, '222');
        $u = Account::create(['email' => 'u@x.com', 'password' => 'x', 'phone_number' => '0999', 'is_approved' => 'approved']);
        $u->assignRole('user');
        DB::table('users')->insert(['account_id' => $u->id, 'full_name' => 'مريض', 'gender' => 'male', 'age' => 20]);

        $r = $this->actingAs($u, 'api')->postJson('/api/user/hospitals/make-reservation', ['hospital_service_id' => $full]);
        $r->assertStatus(422)->assertJson(['no_vacancy' => true])->assertJsonPath('alternatives.0.hospital_name', 'Free');
        $n = AdminNotification::first();
        $this->assertSame('hospital_no_vacancy', $n->type);
        $this->assertStringContainsString('0999', $n->body);

        $ok = DB::table('hospital_services')->where('capacity', 3)->value('id');
        $r = $this->actingAs($u, 'api')->postJson('/api/user/hospitals/make-reservation', ['hospital_service_id' => $ok]);
        $r->assertOk();
        $this->assertSame(2, AdminNotification::count());
        $this->assertStringContainsString('Free', AdminNotification::latest('id')->first()->body);
    }
}
