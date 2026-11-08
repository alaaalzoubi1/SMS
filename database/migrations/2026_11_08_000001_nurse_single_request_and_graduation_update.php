<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1. nurses.graduation_type: native enum → string. The allowed values now
 *    live only in App\Enums\GraduationType (new list: كلية التمريض،
 *    كلية العلوم الصحية، مدرسة التمريض والقبالة، معهد طبي/صحي). Rows that
 *    hold a removed option (جامعة / ماجستير / دكتوراه) become "كلية التمريض".
 *
 * 2. nurse_reservations.status: native enum → string, so the new
 *    'expired' status (nurse did not answer within 8 minutes) can be stored.
 *
 * 3. nurse_reservations.response_deadline_at: when a pending request
 *    expires (sent to the apps for the countdown).
 *
 * 4. nurses.current_reservation_id: the request the nurse is busy with
 *    (pending, or accepted "direct"). NULL = free. One indexed column instead
 *    of scanning reservations on every listing / booking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nurses', function (Blueprint $table) {
            $table->string('graduation_type', 50)->change();
        });

        DB::table('nurses')
            ->whereIn('graduation_type', ['جامعة', 'ماجستير', 'دكتوراه'])
            ->update(['graduation_type' => 'كلية التمريض']);

        Schema::table('nurse_reservations', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->change();
        });

        Schema::table('nurse_reservations', function (Blueprint $table) {
            $table->timestamp('response_deadline_at')->nullable()->after('status');
        });

        Schema::table('nurses', function (Blueprint $table) {
            $table->unsignedBigInteger('current_reservation_id')->nullable()->index();
        });

        // Existing open requests: mark their nurse busy so the new rule holds
        // from the first minute after deploy.
        $open = DB::table('nurse_reservations')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('status', 'pending')
                    ->orWhere(fn ($q) => $q->where('status', 'accepted')->where('reservation_type', 'direct'));
            })
            ->orderBy('id')
            ->get(['id', 'nurse_id']);

        foreach ($open->groupBy('nurse_id') as $nurseId => $rows) {
            DB::table('nurses')->where('id', $nurseId)->update(['current_reservation_id' => $rows->last()->id]);
        }
    }

    public function down(): void
    {
        Schema::table('nurses', function (Blueprint $table) {
            $table->dropIndex(['current_reservation_id']);
            $table->dropColumn('current_reservation_id');
        });

        Schema::table('nurse_reservations', function (Blueprint $table) {
            $table->dropColumn('response_deadline_at');
        });
        // graduation_type / status stay strings: narrowing them back to an
        // enum could fail on rows using the new values.
    }
};
