<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the hospital accepts a request, the patient has
 * `hospitals.reservation_confirmation_deadline` hours to go to the hospital
 * and confirm (تثبيت) the reservation. The countdown needs the moment of
 * acceptance, which was never stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hospital_service_reservations', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('hospital_service_reservations', function (Blueprint $table) {
            $table->dropColumn('accepted_at');
        });
    }
};
