<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDoctorsTable extends Migration
{
    public function up()
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('province_id')->constrained('provinces');
            $table->string('full_name');
            $table->string('profile_description')->nullable();
            $table->string('profile_image_path')->nullable();
            $table->string('address');
            $table->integer('age');
            $table->enum('gender', ['male', 'female']);
            $table->foreignId('specialization_id')->constrained('specializations');
            $table->string('license_image_path')->nullable();
            $table->float('avg_rating')->default(0);
            $table->unsignedInteger('ratings_count')->default(0);
            $table->geography('location', subtype: 'point');
            $table->spatialIndex('location');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('doctors');
    }
}
