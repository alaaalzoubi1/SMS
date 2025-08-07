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
        Schema::create('nurses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts');
            $table->boolean('is_active')->default(true)->index();
            $table->string('full_name');
            $table->string('address')->nullable();
            $table->enum('graduation_type',['معهد', 'مدرسة', 'جامعة', 'ماجستير' ,'دكتوراه'])->index();
            $table->geography('location', subtype: 'point')->nullable();
            $table->integer('age');
            $table->enum('gender', ['male', 'female'])->index();
            $table->string('profile_description')->nullable();
            $table->string('license_image_path');
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::table('nurses', function (Blueprint $table) {
            DB::statement("UPDATE `nurses` SET `location` = ST_GeomFromText('POINT(0 0)', 4326);");
            DB::statement("ALTER TABLE `nurses` CHANGE `location` `location` POINT NOT NULL;");
            $table->spatialIndex('location');
        });
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nurses');
    }
};
