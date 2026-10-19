<?php

namespace Tests\Feature\Muafa4;

use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReservedByAdminFilterTest extends TestCase
{
    use RefreshDatabase;

    private Account $admin, $doctorAcc, $nurseAcc, $hospitalAcc, $userAcc;
    private int $doctorId, $nurseId, $hospitalId, $userId;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-19 10:00:00');
        foreach (['admin', 'doctor', 'nurse', 'hospital', 'user'] as $r) Role::create(['name' => $r, 'guard_name' => 'api']);
        $mkAcc = function ($email, $role) {
            $a = Account::create(['email' => $email, 'password' => 'x', 'phone_number' => $email, 'is_approved' => 'approved']);
            $a->assignRole($role);
            return $a;
        };
        $this->admin = $mkAcc('admin@x', 'admin');
        $this->doctorAcc = $mkAcc('doc@x', 'doctor');
        $this->nurseAcc = $mkAcc('nurse@x', 'nurse');
        $this->hospitalAcc = $mkAcc('hosp@x', 'hospital');
        $this->userAcc = $mkAcc('user@x', 'user');

        DB::table('provinces')->insert(['id' => 1, 'name_ar' => 'حلب', 'name_en' => 'Aleppo']);
        DB::table('specializations')->insert(['id' => 1, 'name_ar' => 'عام', 'name_en' => 'General']);
        $this->userId = DB::table('users')->insertGetId(['account_id' => $this->userAcc->id, 'full_name' => 'مريض', 'gender' => 'male', 'age' => 20]);

        $this->doctorId = DB::table('doctors')->insertGetId(['account_id' => $this->doctorAcc->id, 'full_name' => 'Dr', 'specialization_id' => 1,
            'province_id' => 1, 'address' => 'aaaaaaaaaaaa', 'gender' => 'male', 'age' => 30, 'birthdate' => '1990-01-01', 'location' => '']);
        $ds = DB::table('doctor_services')->insertGetId(['doctor_id' => $this->doctorId, 'name' => 'كشف', 'price' => 1, 'duration_minutes' => 30]);

        $this->nurseId = DB::table('nurses')->insertGetId(['account_id' => $this->nurseAcc->id, 'full_name' => 'Nurse', 'province_id' => 1,
            'address' => 'x', 'graduation_type' => 'جامعة', 'license_image_path' => 'x', 'gender' => 'female', 'age' => 25, 'birthdate' => '2000-01-01', 'location' => '']);
        $svc = DB::table('services')->insertGetId(['service_name' => 'حقن', 'service_type' => 'nurse', 'requires_certificate' => 0]);
        $ns = DB::table('nurse_services')->insertGetId(['nurse_id' => $this->nurseId, 'service_id' => $svc, 'price' => 3000]);

        $this->hospitalId = DB::table('hospitals')->insertGetId(['account_id' => $this->hospitalAcc->id, 'full_name' => 'Hosp', 'unique_code' => 'c',
            'address' => 'x', 'location' => '', 'province_id' => 1, 'reservation_confirmation_deadline' => 6]);
        $hsvc = DB::table('services')->insertGetId(['service_name' => 'سرير', 'service_type' => 'hospital', 'requires_certificate' => 0]);
        $hs = DB::table('hospital_services')->insertGetId(['hospital_id' => $this->hospitalId, 'service_id' => $hsvc, 'price' => 8000, 'capacity' => 3, 'description' => 'd']);

        // for each type: 1 by admin + 2 by the patient, all on 2026-10-19
        foreach ([true, false, false] as $byAdmin) {
            DB::table('doctor_reservations')->insert(['user_id' => $this->userId, 'doctor_id' => $this->doctorId, 'doctor_service_id' => $ds,
                'date' => '2026-10-19', 'status' => 'pending', 'reserved_by_admin' => $byAdmin, 'created_at' => now()]);
            DB::table('nurse_reservations')->insert(['user_id' => $this->userId, 'nurse_id' => $this->nurseId, 'nurse_service_id' => $ns,
                'reservation_type' => 'direct', 'price' => 3000, 'location' => '', 'status' => 'pending', 'reserved_by_admin' => $byAdmin,
                'start_at' => '2026-10-19 12:00:00', 'created_at' => now()]);
            DB::table('hospital_service_reservations')->insert(['user_id' => $this->userId, 'hospital_service_id' => $hs, 'hospital_id' => $this->hospitalId,
                'unit_price' => 8000, 'status' => 'pending', 'reserved_by_admin' => $byAdmin, 'start_date' => '2026-10-19 09:00:00', 'created_at' => now()]);
        }
    }

    private function rows($acc, string $url, string $path): int
    {
        $r = $this->actingAs($acc, 'api')->getJson($url);
        $this->assertSame(200, $r->getStatusCode(), $url . ' → ' . $r->getContent());
        $value = $r->json($path);
        return is_array($value) ? count($value) : (int) $value;
    }

    public function test_every_reservation_list_filters_by_admin(): void
    {
        $cases = [
            [$this->doctorAcc,   '/api/doctor/reservations',                          'data'],
            [$this->doctorAcc,   '/api/doctor/reservations/calendar?date=2026-10-19', 'reservations'],
            [$this->nurseAcc,    '/api/nurse/reservations',                           'data'],
            [$this->hospitalAcc, '/api/hospital/reservations',                        ''],
            [$this->hospitalAcc, '/api/hospital/reservations/calendar?date=2026-10-19','reservations'],
            [$this->admin,       "/api/admin/doctor/{$this->doctorId}/reservations",  'data'],
            [$this->admin,       "/api/admin/nurse/{$this->nurseId}/reservations",    'data'],
            [$this->admin,       "/api/admin/hospital/{$this->hospitalId}/reservations", 'data'],
            [$this->admin,       "/api/admin/user/{$this->userId}/reservations",      'data.doctor_reservations'],
            [$this->admin,       "/api/admin/user/{$this->userId}/reservations",      'data.nurse_reservations'],
            [$this->admin,       "/api/admin/user/{$this->userId}/reservations",      'data.hospital_reservations'],
            [$this->userAcc,     '/api/user/my-reservations',                         'data.doctor_reservations'],
            [$this->userAcc,     '/api/user/my-reservations',                         'data.nurse_reservations'],
            [$this->userAcc,     '/api/user/my-reservations',                         'data.hospital_reservations'],
            [$this->userAcc,     '/api/user/my-reservations?type=hospital',           'data.hospital_reservations'],
        ];

        foreach ($cases as [$acc, $url, $path]) {
            $sep = str_contains($url, '?') ? '&' : '?';
            $this->assertSame(3, $this->rows($acc, $url, $path ?: '*'), "all: $url");
            $this->assertSame(1, $this->rows($acc, $url . $sep . 'reserved_by_admin=true', $path ?: '*'), "true: $url");
            $this->assertSame(2, $this->rows($acc, $url . $sep . 'reserved_by_admin=false', $path ?: '*'), "false: $url");
            $this->assertSame(1, $this->rows($acc, $url . $sep . 'reserved_by_admin=1', $path ?: '*'), "1: $url");
            $this->actingAs($acc, 'api')->getJson($url . $sep . 'reserved_by_admin=maybe')
                ->assertStatus(422)->assertJsonValidationErrors(['reserved_by_admin']);
        }
    }

    public function test_status_and_date_fixes(): void
    {
        // doctor from/to now on `date`
        $this->assertSame(3, $this->rows($this->doctorAcc, '/api/doctor/reservations?from=2026-10-19&to=2026-10-19', 'data'));
        $this->assertSame(0, $this->rows($this->doctorAcc, '/api/doctor/reservations?from=2026-10-20', 'data'));
        // admin hospital statuses accepted / finished are accepted now
        $this->assertSame(0, $this->rows($this->admin, "/api/admin/hospital/{$this->hospitalId}/reservations?status=finished", 'data'));
        $this->assertSame(0, $this->rows($this->admin, "/api/admin/nurse/{$this->nurseId}/reservations?status=cancelled", 'data'));
        // my-reservations without type now applies the status
        $this->assertSame(0, $this->rows($this->userAcc, '/api/user/my-reservations?status=completed', 'data.doctor_reservations'));
        $this->assertSame(3, $this->rows($this->userAcc, '/api/user/my-reservations?status=pending&reserved_by_admin=', 'data.hospital_reservations'));
    }
}
