<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hospital types (e.g. مشفى عام، مشفى خاص، مشفى تخصصي، مركز طبي…), managed by
 * the admin. Every hospital belongs to one type; the user app filters and
 * groups hospitals by it.
 *
 * `hospitals.hospital_type_id` is nullable at the database level only so
 * that hospitals created before this change keep working; the API requires
 * it when a hospital completes its registration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospital_types', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('hospitals', function (Blueprint $table) {
            $table->foreignId('hospital_type_id')
                ->nullable()
                ->after('province_id')
                ->constrained('hospital_types')
                ->nullOnDelete(); // the FK constraint already indexes the column
        });
    }

    public function down(): void
    {
        Schema::table('hospitals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hospital_type_id');
        });

        Schema::dropIfExists('hospital_types');
    }
};
