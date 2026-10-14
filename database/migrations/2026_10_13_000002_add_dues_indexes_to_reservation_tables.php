<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the admin dues report (GET api/admin/dues), which filters every
 * reservation table by a created_at range + status and groups by provider.
 * Without them each dues request is a full table scan of all reservations.
 */
return new class extends Migration
{
    private array $tables = [
        'doctor_reservations'          => 'doctor_id',
        'nurse_reservations'           => null, // already has (nurse_id, created_at)
        'hospital_service_reservations'=> 'hospital_id',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $fk) {
            Schema::table($table, function (Blueprint $t) use ($table, $fk) {
                $t->index(['created_at', 'status'], "{$table}_dues_created_status_idx");
                if ($fk !== null) {
                    $t->index([$fk, 'created_at'], "{$table}_dues_provider_created_idx");
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table => $fk) {
            Schema::table($table, function (Blueprint $t) use ($table, $fk) {
                $t->dropIndex("{$table}_dues_created_status_idx");
                if ($fk !== null) {
                    $t->dropIndex("{$table}_dues_provider_created_idx");
                }
            });
        }
    }
};
