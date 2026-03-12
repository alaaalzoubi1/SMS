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
        Schema::create('nurse_service_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('nurse_id')->constrained('nurses');
            $table->foreignId('service_id')->constrained('services');
            $table->decimal('price', 8, 2);
            $table->string('certificate_path')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending')
                ->index();
            $table->text('admin_note')->nullable();
            $table->unique(['nurse_id','service_id','status']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nurse_service_requests');
    }
};
