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
        Schema::create('nurse_subservices_reservations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subservice_id');
            $table->foreignId('nurse_reservation_id');

            $table->foreign('subservice_id', 'fk_ns_ns_subservice')->references('id')->on('nurse_subservices');
            $table->foreign('nurse_reservation_id', 'fk_ns_ns_reservation')->references('id')->on('nurse_reservations');

            $table->index('subservice_id');
            $table->index('nurse_reservation_id');

            $table->softDeletes();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nurse_subservices_nurse_reservations');
    }
};
