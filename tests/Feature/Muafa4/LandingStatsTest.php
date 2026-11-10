<?php

namespace Tests\Feature\Muafa4;

use App\Models\Account;
use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LandingStatsTest extends TestCase
{
    use RefreshDatabase;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('provinces')->insert(['id' => 1, 'name_ar' => 'حلب', 'name_en' => 'Aleppo']);
        DB::table('specializations')->insert(['id' => 1, 'name_ar' => 'عام', 'name_en' => 'General']);
    }

    private function account(string $approved = 'approved', bool $suspended = false): Account
    {
        $this->n++;

        return Account::create(['email' => "a{$this->n}@x", 'password' => 'x', 'phone_number' => "p{$this->n}",
            'is_approved' => $approved, 'is_suspended' => $suspended]);
    }

    private function doctor(Account $a): int
    {
        return DB::table('doctors')->insertGetId(['account_id' => $a->id, 'full_name' => 'Dr', 'specialization_id' => 1,
            'province_id' => 1, 'address' => 'aaaaaaaaaaaa', 'gender' => 'male', 'age' => 30, 'birthdate' => '1990-01-01', 'location' => '']);
    }

    private function seedAll(): void
    {
        $this->doctor($this->account());
        $this->doctor($this->account());
        $this->doctor($this->account('pending'));
        $this->doctor($this->account('rejected'));
        $this->doctor($this->account('approved', true));

        DB::table('nurses')->insert(['account_id' => $this->account()->id, 'full_name' => 'N', 'province_id' => 1, 'address' => 'x',
            'graduation_type' => 'كلية التمريض', 'license_image_path' => 'x', 'gender' => 'male', 'age' => 25, 'birthdate' => '2000-01-01', 'location' => '']);
        DB::table('hospitals')->insert(['account_id' => $this->account()->id, 'full_name' => 'H', 'unique_code' => 'c',
            'address' => 'x', 'location' => '', 'province_id' => 1]);

        DB::table('users')->insert(['account_id' => $this->account()->id, 'full_name' => 'U', 'gender' => 'male', 'age' => 20]);
        DB::table('users')->insert(['account_id' => $this->account()->id, 'full_name' => 'U2', 'gender' => 'female', 'age' => 22]);
        // created by the admin, no account: not a registered user
        DB::table('users')->insert(['account_id' => null, 'full_name' => 'Static', 'gender' => 'male', 'age' => 60]);
    }

    public function test_landing_returns_counts_of_live_accounts_only(): void
    {
        $this->seedAll();

        $this->getJson('/api/landing')->assertOk()->assertJsonPath('stats', [
            'doctors_count' => 2,
            'nurses_count' => 1,
            'hospitals_count' => 1,
            'users_count' => 2,
        ]);
    }

    public function test_counts_follow_approval_suspension_and_deletion(): void
    {
        $this->seedAll();
        $this->getJson('/api/landing')->assertJsonPath('stats.doctors_count', 2); // cached now

        $pending = Account::where('is_approved', 'pending')->first();
        $pending->update(['is_approved' => 'approved']);
        $this->getJson('/api/landing')->assertJsonPath('stats.doctors_count', 3);

        $pending->update(['is_suspended' => true]);
        $this->getJson('/api/landing')->assertJsonPath('stats.doctors_count', 2);

        Doctor::withoutGlobalScopes()->first()->delete();
        $this->getJson('/api/landing')->assertJsonPath('stats.doctors_count', 1);

        Doctor::withoutGlobalScopes()->onlyTrashed()->first()->restore();
        $this->getJson('/api/landing')->assertJsonPath('stats.doctors_count', 2);
    }

    public function test_landing_counts_cost_no_queries_once_cached(): void
    {
        $this->seedAll();
        $this->getJson('/api/landing');

        DB::enableQueryLog();
        $this->getJson('/api/landing')->assertOk();
        $this->assertCount(0, DB::getQueryLog());
    }
}
