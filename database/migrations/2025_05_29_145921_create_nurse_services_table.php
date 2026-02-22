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
        Schema::create('nurse_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nurse_id')->constrained('nurses');
            $table->string('name');
            $table->decimal('price', 8, 2);
            $table->softDeletes();
            $table->timestamps();

            $table->index('nurse_id');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nurse_services');
    }
};
