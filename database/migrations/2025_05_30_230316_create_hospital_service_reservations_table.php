<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHospitalServiceReservationsTable extends Migration
{
    public function up(): void
    {
        Schema::create('hospital_service_reservations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('hospital_service_id')->constrained('hospital_services');
            $table->foreignId('hospital_id')->constrained('hospitals');

            $table->date('start_date');
            $table->date('end_date');

            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');

            $table->index('user_id');
            $table->index('hospital_service_id');
            $table->index('hospital_id');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_service_reservations');
    }
}
