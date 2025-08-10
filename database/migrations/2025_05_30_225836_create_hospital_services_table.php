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
        Schema::create('hospital_services', function (Blueprint $table) {
            $table->id();

            $table->foreignId('hospital_id')
                ->constrained();

            $table->foreignId('service_id')
                ->constrained();

            $table->decimal('price', 10, 2);
            $table->unsignedInteger('capacity');

            $table->index('hospital_id');
            $table->index('service_id');

            $table->softDeletes();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hospital_services');
    }
};
