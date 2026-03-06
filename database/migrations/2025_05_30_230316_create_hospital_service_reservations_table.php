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

            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->boolean('reserved_by_admin')->default(false)->index();

            $table->enum('status', ['pending', 'confirmed', 'accepted' , 'cancelled','finished'])->default('pending');

            $table->index('status');
            $table->index('start_date');
            $table->index('end_date');


            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_service_reservations');
    }
}
