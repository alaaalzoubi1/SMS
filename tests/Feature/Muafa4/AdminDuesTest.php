<?php

namespace Tests\Feature\Muafa4;

use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDuesTest extends TestCase
{
    use RefreshDatabase;

    private Account $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-13 12:00:00');
        Role::create(['name' => 'admin', 'guard_name' => 'api']);
        $this->admin = Account::create(['email' => 'admin@a.com', 'password' => 'x', 'phone_number' => '0', 'is_approved' => 'approved']);
        $this->admin->assignRole('admin');

        DB::table('provinces')->insert([['id' => 1, 'name_ar' => 'حلب', 'name_en' => 'Aleppo'], ['id' => 2, 'name_ar' => 'دمشق', 'name_en' => 'Damascus']]);
        DB::table('specializations')->insert(['id' => 1, 'name_ar' => 'عام', 'name_en' => 'General']);
        $userId = DB::table('users')->insertGetId(['full_name' => 'مريض', 'gender' => 'male', 'age' => 20]);
        $acc = fn ($i) => DB::table('accounts')->insertGetId(['email' => "p$i@x.com", 'password' => 'x', 'phone_number' => "$i"]);

        // Doctors: 10 in Aleppo, each with a 5000 service and 2 completed + 1 cancelled reservation
        for ($i = 1; $i <= 10; $i++) {
            $d = DB::table('doctors')->insertGetId(['account_id' => $acc($i), 'full_name' => "Dr $i", 'specialization_id' => 1, 'province_id' => 1,
                'address' => 'aaaaaaaaaaaa', 'gender' => 'male', 'age' => 30, 'birthdate' => '1990-01-01', 'location' => '']);
            $s = DB::table('doctor_services')->insertGetId(['doctor_id' => $d, 'name' => 'كشف', 'price' => 5000, 'duration_minutes' => 30]);
            foreach (['completed', 'completed', 'cancelled'] as $k => $st) {
                DB::table('doctor_reservations')->insert(['user_id' => $userId, 'doctor_id' => $d, 'doctor_service_id' => $s, 'date' => '2026-10-05',
                    'status' => $st, 'reserved_by_admin' => $k === 0, 'created_at' => '2026-10-05 10:00:00']);
            }
        }
        // one doctor reservation outside the period
        DB::table('doctor_reservations')->insert(['user_id' => $userId, 'doctor_id' => 1, 'doctor_service_id' => 1, 'date' => '2026-09-01',
            'status' => 'completed', 'created_at' => '2026-09-01 10:00:00']);

        // Nurse in Damascus: one completed reservation, price 3000
        $n = DB::table('nurses')->insertGetId(['account_id' => $acc(100), 'full_name' => 'Nurse', 'province_id' => 2, 'address' => 'x',
            'graduation_type' => 'جامعة', 'license_image_path' => 'x', 'gender' => 'female', 'age' => 25, 'birthdate' => '2000-01-01', 'location' => '']);
        $svc = DB::table('services')->insertGetId(['service_name' => 'حقن', 'service_type' => 'nurse', 'requires_certificate' => 0]);
        $ns = DB::table('nurse_services')->insertGetId(['nurse_id' => $n, 'service_id' => $svc, 'price' => 3000]);
        DB::table('nurse_reservations')->insert(['user_id' => $userId, 'nurse_id' => $n, 'nurse_service_id' => $ns, 'reservation_type' => 'direct',
            'price' => 3000, 'location' => '', 'status' => 'completed', 'created_at' => '2026-10-06 10:00:00']);

        // Hospital in Aleppo: finished 1st→5th at 8000/day = 40000, + a pending (cost 0)
        $h = DB::table('hospitals')->insertGetId(['account_id' => $acc(200), 'full_name' => 'Hosp', 'unique_code' => 'c', 'address' => 'x', 'location' => '', 'province_id' => 1]);
        $hsvc = DB::table('services')->insertGetId(['service_name' => 'سرير', 'service_type' => 'hospital', 'requires_certificate' => 0]);
        $hs = DB::table('hospital_services')->insertGetId(['hospital_id' => $h, 'service_id' => $hsvc, 'price' => 8000, 'capacity' => 3, 'description' => 'd']);
        DB::table('hospital_service_reservations')->insert([
            ['user_id' => $userId, 'hospital_service_id' => $hs, 'hospital_id' => $h, 'unit_price' => 8000, 'status' => 'finished',
             'start_date' => '2026-10-01 09:00:00', 'end_date' => '2026-10-05 00:00:00', 'created_at' => '2026-10-01 08:00:00'],
            ['user_id' => $userId, 'hospital_service_id' => $hs, 'hospital_id' => $h, 'unit_price' => 8000, 'status' => 'pending',
             'start_date' => null, 'end_date' => null, 'created_at' => '2026-10-02 08:00:00'],
        ]);
    }

    private function dues(array $q)
    {
        return $this->actingAs($this->admin, 'api')->getJson('/api/admin/dues?' . http_build_query($q));
    }

    public function test_percentage_all_providers_completed(): void
    {
        $r = $this->dues(['from' => '2026-10-01', 'to' => '2026-10-31', 'statuses' => ['completed'], 'due_type' => 'percentage', 'amount' => 10]);
        $r->assertOk();
        // doctors 20×5000=100000, nurse 3000, hospital 40000
        $r->assertJsonPath('totals.total_count', 22);
        $this->assertEquals(143000, $r->json('totals.total_cost'));
        $this->assertEquals(14300, $r->json('totals.total_due'));
        $this->assertEquals(40000, $r->json('by_type.hospital.total_cost'));
        $this->assertSame('hospital', $r->json('data.0.provider_type')); // highest due first
        $this->assertEquals(4000, $r->json('data.0.total_due'));
    }

    public function test_fixed_province_admin_filter_and_no_n_plus_one(): void
    {
        DB::enableQueryLog();
        $r = $this->dues(['from' => '2026-10-01', 'to' => '2026-10-31', 'province_id' => 1, 'providers' => ['doctor', 'nurse'],
            'statuses' => ['completed', 'cancelled'], 'due_type' => 'fixed', 'amount' => 500, 'reserved_by_admin' => 'false']);
        $r->assertOk();
        // per doctor: 1 completed(not admin) + 1 cancelled = 2 → 10 doctors = 20; nurse is in province 2
        $r->assertJsonPath('totals.total_count', 20);
        $this->assertEquals(10000, $r->json('totals.total_due'));
        $this->assertSame(10, $r->json('totals.providers_count'));
        $this->assertSame(0, $r->json('by_type.nurse.total_count'));

        $reservationQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], '_reservations'))->count();
        $this->assertLessThanOrEqual(2, $reservationQueries, 'one aggregated query per provider type');
    }

    public function test_validation(): void
    {
        $this->dues(['due_type' => 'fixed', 'amount' => 1])->assertStatus(422)->assertJsonValidationErrors(['from', 'to']);
        $this->dues(['from' => '2026-10-01', 'to' => '2026-10-31', 'due_type' => 'percentage', 'amount' => 150])
            ->assertStatus(422)->assertJsonValidationErrors(['amount']);
        $this->dues(['from' => '2026-10-01', 'to' => '2026-10-31', 'due_type' => 'fixed', 'amount' => 150, 'statuses' => ['rejected'], 'providers' => ['hospital']])
            ->assertOk()->assertJsonPath('totals.total_count', 0);
    }

    public function test_csv(): void
    {
        $r = $this->actingAs($this->admin, 'api')->get('/api/admin/dues?' . http_build_query(['from' => '2026-10-01', 'to' => '2026-10-31', 'due_type' => 'fixed', 'amount' => 100, 'format' => 'csv']));
        $this->assertSame(200, $r->getStatusCode());
        $this->assertStringContainsString('إجمالي المستحقات', $r->streamedContent());
    }
}
