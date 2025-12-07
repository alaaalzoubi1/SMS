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
        Schema::create('hospitals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('province_id')->constrained('provinces');
            $table->string('full_name');
            $table->uuid('unique_code')->unique();
            $table->string('address');
            $table->float('avg_rating')->default(0);
            $table->unsignedInteger('ratings_count')->default(0);
            $table->geography('location', subtype: 'point');
            $table->string('profile_image_path')->nullable();
            $table->spatialIndex('location');
            $table->softDeletes();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hospitals');
    }
};
