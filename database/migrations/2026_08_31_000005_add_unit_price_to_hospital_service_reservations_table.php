<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hospital_service_reservations', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->nullable()->after('hospital_service_id');
        });

        DB::table('hospital_service_reservations')->whereNull('unit_price')->update([
            'unit_price' => DB::raw('(SELECT hs.price FROM hospital_services hs WHERE hs.id = hospital_service_reservations.hospital_service_id)'),
        ]);

        Schema::table('hospital_service_reservations', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hospital_service_reservations', function (Blueprint $table) {
            $table->dropColumn('unit_price');
        });
    }
};