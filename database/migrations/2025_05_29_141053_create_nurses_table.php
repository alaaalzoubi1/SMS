<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            $table->enum('graduation_type',['معهد', 'مدرسة', 'جامعة', 'ماجستير' ,'دكتوراه']);
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->integer('age');
            $table->enum('gender', ['male', 'female']);
            $table->string('profile_description')->nullable();
            $table->string('license_image_path')->nullable();
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
