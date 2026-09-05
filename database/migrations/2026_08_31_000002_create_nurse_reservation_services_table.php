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
        Schema::create('nurse_reservation_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nurse_reservation_id')
                ->constrained('nurse_reservations')
                ->cascadeOnDelete();
            $table->foreignId('nurse_service_id')
                ->constrained('nurse_services');
            $table->decimal('price', 10, 2);
            $table->timestamps();

            $table->index('nurse_reservation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nurse_reservation_services');
    }
};