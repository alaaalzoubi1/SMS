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
        Schema::create('nurse_subservices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')
                ->constrained('nurse_services');
            $table->string('name');
            $table->decimal('price', 8, 2);
            $table->softDeletes();
            $table->index('service_id');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nurse_subsercvices');
    }
};
