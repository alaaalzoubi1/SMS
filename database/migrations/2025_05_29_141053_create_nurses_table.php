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
            $table->string('profile_image_path')->nullable();
            $table->string('address')->nullable();
            $table->enum('graduation_type',['معهد', 'مدرسة', 'جامعة', 'ماجستير' ,'دكتوراه'])->index();
            $table->geography('location', subtype: 'point');
            $table->integer('age');
            $table->enum('gender', ['male', 'female'])->index();
            $table->string('profile_description')->nullable();
            $table->string('license_image_path');
            $table->softDeletes();
            $table->spatialIndex('location');
            $table->float('avg_rating')->default(0);
            $table->unsignedInteger('ratings_count')->default(0);
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
