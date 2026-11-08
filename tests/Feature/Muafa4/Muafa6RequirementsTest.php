<?php

namespace Tests\Feature\Muafa4;

use App\Jobs\ExpireNurseRequestJob;
use App\Jobs\SendFirebaseNotificationJob;
use App\Mail\AccountApprovedMail;
use App\Models\Account;
use App\Models\DoctorReservation;
use App\Models\NurseReservation;
use App\Support\NurseRequestManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Nov 2026 batch: graduation list, nurse address, Arabic approval mail,
 * notification texts, one-request-per-nurse with ring flag + 8-min expiry,
 * one pending doctor request per specialization.
 */
class Muafa6RequirementsTest extends TestCase
{
    use RefreshDatabase;

    private Account $nurseAcc;
    private Account $patientA;
    private Account $patientB;
    private Account $adminAcc;
    private int $nurseId;
    private int $nurseServiceId;

    protected function setUp(): void
    {
        parent::setUp();

        // sqlite has no spatial functions: make ST_GeomFromText a pass-through
        // so NurseReservation::location can be written.
        DB::connection()->getPdo()->sqliteCreateFunction('ST_GeomFromText', fn ($wkt) => '', -1);

        foreach (['admin', 'doctor', 'user', 'nurse', 'hospital'] as $r) {
            Role::create(['name' => $r, 'guard_name' => 'api']);
        }
        DB::table('provinces')->insert(['id' => 1, 'name_ar' => 'حلب', 'name_en' => 'Aleppo']);
        DB::table('specializations')->insert([
            ['id' => 1, 'name_ar' => 'أسنان', 'name_en' => 'Dental'],
            ['id' => 2, 'name_ar' => 'عيون', 'name_en' => 'Eyes'],
        ]);

        $this->nurseAcc = Account::create(['email' => 'n@x.com', 'password' => bcrypt('secret123'), 'phone_number' => '10', 'is_approved' => 'approved', 'fcm_token' => 'NURSE-TOKEN']);
        $this->nurseAcc->assignRole('nurse');
        $this->nurseId = DB::table('nurses')->insertGetId([
            'account_id' => $this->nurseAcc->id, 'full_name' => 'محمد المحمد', 'address' => 'حلب - الفرقان - شارع 10',
            'graduation_type' => 'كلية التمريض', 'gender' => 'male', 'age' => 30, 'birthdate' => '1995-01-01',
            'province_id' => 1, 'location' => '', 'is_active' => true, 'license_image_path' => 'x.png',
        ]);
        $svc = DB::table('services')->insertGetId(['service_name' => 'حقنة', 'service_type' => 'nurse']);
        $this->nurseServiceId = DB::table('nurse_services')->insertGetId(['nurse_id' => $this->nurseId, 'service_id' => $svc, 'price' => 1000]);

        foreach (['A' => 'a', 'B' => 'b'] as $k => $p) {
            $acc = Account::create(['email' => "{$p}@x.com", 'password' => bcrypt('secret123'), 'phone_number' => "2{$p}", 'is_approved' => 'approved', 'fcm_token' => "PATIENT-{$k}"]);
            $acc->assignRole('user');
            DB::table('users')->insert(['account_id' => $acc->id, 'full_name' => "مريض {$k}", 'gender' => 'male', 'age' => 30]);
            $this->{"patient{$k}"} = $acc;
        }

        $this->adminAcc = Account::create(['email' => 'adm@x.com', 'password' => bcrypt('secret123'), 'phone_number' => '99', 'is_approved' => 'approved']);
        $this->adminAcc->assignRole('admin');

        Carbon::setTestNow('2026-11-08 10:00:00');
        Queue::fake();
    }

    /* ---------- helpers ---------- */

    private function requestNurse(Account $patient, string $type = 'direct')
    {
        return $this->actingAs($patient, 'api')->postJson('/api/user/nurses/reserve', array_filter([
            'nurse_id' => $this->nurseId,
            'nurse_service_ids' => [$this->nurseServiceId],
            'reservation_type' => $type,
            'start_at' => $type === 'manual' ? '2026-11-10 10:00:00' : null,
            'lat' => 36.2, 'lng' => 37.1,
            'confirm' => true,
        ], fn ($v) => $v !== null));
    }

    private function nurseStatus(int $reservationId, string $status, ?string $reason = null)
    {
        return $this->actingAs($this->nurseAcc, 'api')->patchJson("/api/nurse/reservations/{$reservationId}/status",
            array_filter(['status' => $status, 'reason' => $reason]));
    }

    private function currentReservationId(): ?int
    {
        return DB::table('nurses')->where('id', $this->nurseId)->value('current_reservation_id');
    }

    /** Read a queued FCM job's protected fields. */
    private function fcm(SendFirebaseNotificationJob $job): array
    {
        return (fn () => ['token' => $this->token, 'title' => $this->title, 'body' => $this->body, 'data' => $this->data])->call($job);
    }

    /* ---------- 1. graduation types ---------- */

    public function test_graduation_types_new_order(): void
    {
        $values = collect($this->getJson('/api/nurse/graduation-types')->assertOk()->json('data'))->pluck('value')->all();

        $this->assertSame(['كلية التمريض', 'كلية العلوم الصحية', 'مدرسة التمريض والقبالة', 'معهد طبي/صحي'], $values);
    }

    /* ---------- 2. nurse address at registration ---------- */

    public function test_nurse_register_requires_detailed_address(): void
    {
        $base = ['email' => 'new@x.com', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'phone_number' => '0999',
            'full_name' => 'ممرض', 'graduation_type' => 'كلية العلوم الصحية', 'birthdate' => '1990-01-01', 'gender' => 'female'];

        $r = $this->postJson('/api/nurse/register', $base)->assertStatus(422);
        $this->assertSame('يرجى إدخال عنوان الممرض بالتفصيل.', $r->json('errors.address.0'));

        $r = $this->postJson('/api/nurse/register', $base + ['address' => 'حلب'])->assertStatus(422);
        $this->assertSame('يجب أن يكون العنوان مفصلاً ولا يقل عن 15 حرفاً.', $r->json('errors.address.0'));

        // A removed option sent by an old app build is mapped, not rejected.
        $r = $this->postJson('/api/nurse/register', $base + ['address' => 'حلب', 'graduation_type' => 'جامعة']);
        $this->assertArrayNotHasKey('graduation_type', $r->json('errors') ?? []);
    }

    /* ---------- 3. Arabic approval email ---------- */

    public function test_approval_email_is_arabic_rtl(): void
    {
        $html = (new AccountApprovedMail($this->nurseAcc))->render();
        $mail = (new AccountApprovedMail($this->nurseAcc))->build();

        $this->assertSame('تمت الموافقة على حسابك في معافى', $mail->subject);
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('مرحباً محمد المحمد', $html);
        $this->assertStringContainsString('تمت الموافقة على حسابك بنجاح', $html);
    }

    /* ---------- 4/5/6. nurse single request, ring flag, texts, expiry ---------- */

    public function test_new_request_rings_nurse_and_makes_her_busy(): void
    {
        $r = $this->requestNurse($this->patientA)->assertCreated();
        $id = $r->json('data.id');

        $this->assertSame($id, $this->currentReservationId());
        $this->assertSame('2026-11-08T10:08:00+03:00', Carbon::parse($r->json('data.response_deadline_at'))->setTimezone('Asia/Baghdad')->toIso8601String());

        Queue::assertPushed(SendFirebaseNotificationJob::class, function ($job) use ($id) {
            $f = $this->fcm($job);
            return $f['token'] === 'NURSE-TOKEN'
                && $f['data']['type'] === NurseRequestManager::RING_TYPE
                && $f['data']['ring'] === '1'
                && $f['data']['reservation_id'] === (string) $id
                && $f['data']['timeout_seconds'] === '480';
        });
        Queue::assertPushed(ExpireNurseRequestJob::class, fn ($job) => $job->reservationId === $id && $job->delay !== null);
    }

    public function test_second_patient_is_refused_and_busy_nurse_is_hidden(): void
    {
        $this->requestNurse($this->patientA)->assertCreated();

        $this->requestNurse($this->patientB)->assertStatus(409)->assertJson(['reason' => 'nurse_busy']);

        $list = $this->actingAs($this->patientB, 'api')->getJson('/api/user/nurses')->assertOk();
        $this->assertNotContains($this->nurseId, collect($list->json('data'))->pluck('id')->all());

        $this->assertSame(1, NurseReservation::count());
    }

    public function test_reject_frees_nurse(): void
    {
        $id = $this->requestNurse($this->patientA)->json('data.id');
        

        $this->nurseStatus($id, 'rejected', 'مشغول')->assertOk();

        $this->assertNull($this->currentReservationId());
        $this->requestNurse($this->patientB)->assertCreated();
    }

    public function test_patient_cancel_frees_nurse(): void
    {
        $id = $this->requestNurse($this->patientA)->json('data.id');
        

        $this->actingAs($this->patientA, 'api')->patchJson('/api/user/nurses/reservation/cancel', ['reservation_id' => $id, 'reason' => 'تغيير'])->assertOk();

        $this->assertNull($this->currentReservationId());
    }

    public function test_accepted_direct_keeps_nurse_busy_until_completed_with_new_texts(): void
    {
        $id = $this->requestNurse($this->patientA)->json('data.id');
        

        $this->nurseStatus($id, 'accepted')->assertOk();
        $this->assertSame($id, $this->currentReservationId());
        $this->requestNurse($this->patientB)->assertStatus(409);

        $this->nurseStatus($id, 'completed')->assertOk();
        $this->assertNull($this->currentReservationId());

        Queue::assertPushed(SendFirebaseNotificationJob::class, function ($job) {
            $f = $this->fcm($job);
            return $f['token'] === 'PATIENT-A'
                && $f['title'] === 'معافى يتمنى لكم الشفاء'
                && $f['body'] === 'تم تنفيذ الخدمة من قبل محمد المحمد.';
        });
    }

    public function test_accepted_manual_booking_frees_nurse(): void
    {
        $id = $this->requestNurse($this->patientA, 'manual')->json('data.id');
        

        $this->nurseStatus($id, 'accepted')->assertOk();

        $this->assertNull($this->currentReservationId());
    }

    public function test_expiry_job_after_8_minutes(): void
    {
        $id = $this->requestNurse($this->patientA)->json('data.id');
        

        // Too early: nothing happens.
        Carbon::setTestNow('2026-11-08 10:07:00');
        (new ExpireNurseRequestJob($id))->handle();
        $this->assertSame('pending', NurseReservation::find($id)->status);

        Carbon::setTestNow('2026-11-08 10:08:30');
        (new ExpireNurseRequestJob($id))->handle();

        $this->assertSame('expired', NurseReservation::find($id)->status);
        $this->assertNull($this->currentReservationId());

        Queue::assertPushed(SendFirebaseNotificationJob::class, function ($job) {
            $f = $this->fcm($job);
            return $f['token'] === 'PATIENT-A'
                && $f['body'] === 'هذا الممرض غير متاح حالياً، يرجى طلب ممرض آخر أو التواصل مع فريق الدعم.';
        });

        // Patient sees it under the new status.
        $mine = $this->actingAs($this->patientA, 'api')->getJson('/api/user/my-reservations?type=nurse&status=expired')->assertOk();
        $this->assertStringContainsString('"status":"expired"', $mine->getContent());
    }

    public function test_overdue_request_is_expired_lazily_when_next_patient_books(): void
    {
        $first = $this->requestNurse($this->patientA)->json('data.id');
        

        Carbon::setTestNow('2026-11-08 10:09:00'); // job has not run (queue faked)
        $this->requestNurse($this->patientB)->assertCreated();

        $this->assertSame('expired', NurseReservation::find($first)->status);
    }

    public function test_nurse_cannot_accept_overdue_request(): void
    {
        $id = $this->requestNurse($this->patientA)->json('data.id');
        

        Carbon::setTestNow('2026-11-08 10:09:00');
        $this->nurseStatus($id, 'accepted')->assertStatus(409)->assertJson(['reason' => 'request_expired']);

        $this->assertSame('expired', NurseReservation::find($id)->status);
        $this->assertNull($this->currentReservationId());
    }

    public function test_admin_booking_free_nurse_claims_and_rings(): void
    {
        $patientBUserId = DB::table('users')->where('account_id', $this->patientB->id)->value('id');

        $r = $this->actingAs($this->adminAcc, 'api')->postJson('/api/admin/nurse/reserve', [
            'nurse_id' => $this->nurseId, 'nurse_service_ids' => [$this->nurseServiceId], 'reservation_type' => 'direct',
            'lat' => 36.2, 'lng' => 37.1, 'user_id' => $patientBUserId,
        ])->assertOk();

        $this->assertSame($r->json('id'), $this->currentReservationId());
        $this->assertNotNull($r->json('response_deadline_at'));
        Queue::assertPushed(ExpireNurseRequestJob::class);
        Queue::assertPushed(SendFirebaseNotificationJob::class, fn ($job) => ($this->fcm($job)['data']['ring'] ?? null) === '1');
    }

    public function test_admin_booking_busy_nurse_is_refused(): void
    {
        $this->requestNurse($this->patientA)->assertCreated();
        $patientBUserId = DB::table('users')->where('account_id', $this->patientB->id)->value('id');

        $this->actingAs($this->adminAcc, 'api')->postJson('/api/admin/nurse/reserve', [
            'nurse_id' => $this->nurseId, 'nurse_service_ids' => [$this->nurseServiceId], 'reservation_type' => 'direct',
            'lat' => 36.2, 'lng' => 37.1, 'user_id' => $patientBUserId,
        ])->assertStatus(409)->assertJson(['reason' => 'nurse_busy']);
    }

    /* ---------- 7. one pending doctor request per specialization ---------- */

    private function makeDoctor(string $email, int $spec): array
    {
        $acc = Account::create(['email' => $email, 'password' => 'x', 'phone_number' => $email, 'is_approved' => 'approved', 'fcm_token' => null]);
        $acc->assignRole('doctor');
        $id = DB::table('doctors')->insertGetId(['account_id' => $acc->id, 'full_name' => 'د. ' . $email, 'specialization_id' => $spec,
            'province_id' => 1, 'address' => 'aaaaaaaaaaaa', 'gender' => 'male', 'age' => 40, 'birthdate' => '1985-01-01', 'location' => '']);
        $svc = DB::table('doctor_services')->insertGetId(['doctor_id' => $id, 'name' => 'كشف', 'price' => 5000, 'duration_minutes' => 30]);
        DB::table('doctor_work_schedules')->insert(['doctor_id' => $id, 'day_of_week' => 'monday', 'start_time' => '14:00', 'end_time' => '18:00']);
        return [$acc, $id, $svc];
    }

    private function reserveDoctor(int $doctorId, int $svc)
    {
        return $this->actingAs($this->patientA, 'api')->postJson('/api/user/doctors/reserve',
            ['doctor_id' => $doctorId, 'doctor_service_id' => $svc, 'date' => '2026-11-09']);
    }

    public function test_one_pending_request_per_specialization(): void
    {
        [, $d1, $s1] = $this->makeDoctor('d1@x.com', 1);
        [, $d2, $s2] = $this->makeDoctor('d2@x.com', 1); // same specialization
        [, $d3, $s3] = $this->makeDoctor('d3@x.com', 2); // other specialization

        $first = $this->reserveDoctor($d1, $s1)->assertCreated()->json('reservation.id');

        $r = $this->reserveDoctor($d2, $s2)->assertStatus(422)
            ->assertJson(['reason' => 'pending_same_specialization', 'existing_reservation' => ['id' => $first, 'doctor_id' => $d1, 'specialization_name' => 'أسنان']]);
        $this->assertStringContainsString('يرجى إلغاء الطلب الأول أولاً', $r->json('message'));

        $this->reserveDoctor($d1, $s1)->assertStatus(422); // same doctor too
        $this->reserveDoctor($d3, $s3)->assertCreated();   // other specialization OK

        $this->actingAs($this->patientA, 'api')->patchJson('/api/user/doctors/reservation/cancel', ['reservation_id' => $first, 'reason' => 'تغيير'])->assertOk();
        $this->reserveDoctor($d2, $s2)->assertCreated();
    }

    public function test_doctor_completed_notification_texts(): void
    {
        [$docAcc, $d1, $s1] = $this->makeDoctor('d1@x.com', 1);
        $patientUserId = DB::table('users')->where('account_id', $this->patientA->id)->value('id');
        $res = DoctorReservation::create(['user_id' => $patientUserId, 'doctor_id' => $d1, 'doctor_service_id' => $s1,
            'date' => '2026-11-08', 'status' => 'approved', 'start_time' => '09:00:00', 'end_time' => '09:30:00']);

        $this->actingAs($docAcc, 'api')->patchJson("/api/doctor/reservations/{$res->id}/status", ['status' => 'completed'])->assertOk();

        Queue::assertPushed(SendFirebaseNotificationJob::class, function ($job) {
            $f = $this->fcm($job);
            return $f['token'] === 'PATIENT-A'
                && $f['title'] === 'معافى يتمنى لكم الشفاء'
                && $f['body'] === 'تم تنفيذ الخدمة من قبل د. d1@x.com.';
        });
    }
}
