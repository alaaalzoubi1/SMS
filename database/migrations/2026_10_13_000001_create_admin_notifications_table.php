<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-dashboard notifications for the admins.
 *
 * Push notifications alone are not reliable for the admin panel (a browser
 * must have registered an FCM token, and a queue worker must be running).
 * Every admin alert — e.g. a patient's new hospital request, or a request
 * that could not be placed because the hospital has no free beds — is now
 * also stored here so the dashboard can list it and the admin can call the
 * patient back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64)->index();
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
