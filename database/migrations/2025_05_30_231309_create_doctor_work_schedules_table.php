<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDoctorWorkSchedulesTable extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_work_schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('doctor_id')->constrained('doctors');

            $table->enum('day_of_week', [
                'saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'
            ]);

            $table->time('start_time');
            $table->time('end_time');
            $table->index('doctor_id');
            $table->unique(['doctor_id', 'day_of_week','deleted_at']);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_work_schedules');
    }
}
