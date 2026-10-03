<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Distinguishes "the nurse refused the booking" from "the booking was
 * cancelled" inside `nurse_cancellations`.
 *
 * Both outcomes were stored in the same table with no discriminator, so a
 * rejection surfaced to both parties exactly like a cancellation. Existing
 * rows are backfilled from the reservation's current status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nurse_cancellations', function (Blueprint $table) {
            $table->enum('status', ['cancelled', 'rejected'])
                ->default('cancelled')
                ->after('reason')
                ->index();
        });

        DB::table('nurse_cancellations')
            ->join('nurse_reservations', 'nurse_reservations.id', '=', 'nurse_cancellations.reservation_id')
            ->where('nurse_reservations.status', 'rejected')
            ->update(['nurse_cancellations.status' => 'rejected']);
    }

    public function down(): void
    {
        Schema::table('nurse_cancellations', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
