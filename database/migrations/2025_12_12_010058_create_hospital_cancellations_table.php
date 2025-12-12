<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospital_cancellations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reservation_id')
                ->constrained('hospital_service_reservations')
                ->onDelete('cascade');

            $table->text('reason');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_cancellations');
    }
};
