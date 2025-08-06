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
            $table->string('full_name');
            $table->string('address')->nullable();
            $table->enum('graduation_type',['معهد', 'مدرسة', 'جامعة', 'ماجستير' ,'دكتوراه'])->index();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            DB::statement('ALTER TABLE nurses ADD location POINT SRID 4326 NULL');

            DB::statement('CREATE SPATIAL INDEX idx_location ON nurses(location)');
            $table->integer('age');
            $table->enum('gender', ['male', 'female'])->index();
            $table->string('profile_description')->nullable();
            $table->string('license_image_path');
            $table->softDeletes();
            $table->timestamps();
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
