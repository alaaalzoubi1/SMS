<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDoctorReservationsTable extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_reservations', function (Blueprint $table) {
            $table->id(); // uniqueId (Primary Key)

            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('doctor_service_id')->constrained('doctor_services');
            $table->foreignId('doctor_id')->constrained('doctors');

            $table->date('date');

            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled', 'completed'])->default('pending');

            $table->index('doctor_id');
            $table->index('user_id');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_reservations');
    }
}

