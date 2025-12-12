<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nurse_reservations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users');

            $table->foreignId('nurse_id')
                ->constrained('nurses');

            $table->foreignId('nurse_service_id')
                ->constrained('nurse_services');

            $table->enum('reservation_type', ['direct', 'manual']);

            $table->geography('location', subtype: 'point');

            $table->enum('status', ['pending', 'accepted','cancelled', 'rejected', 'completed'])->default('pending');
            $table->text('note')->nullable();

            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->index('user_id');
            $table->index('nurse_id');
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
        Schema::dropIfExists('nurse_reservations');
    }
};
