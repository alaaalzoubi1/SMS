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
        DB::table('hospital_services')->whereNull('description')->update([
            'description' => DB::raw("(SELECT service_name FROM services WHERE services.id = hospital_services.service_id)"),
        ]);

        Schema::table('hospital_services', function (Blueprint $table) {
            $table->text('description')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hospital_services', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });
    }
};