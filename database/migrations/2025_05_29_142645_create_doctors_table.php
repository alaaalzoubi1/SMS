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
            $table->string('full_name');
            $table->string('profile_description')->nullable();
            $table->string('address');
            $table->integer('age');
            $table->enum('gender', ['male', 'female']);
            $table->unsignedTinyInteger('specialization_type')->nullable()->index();
            $table->string('license_image_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('doctors');
    }
}
