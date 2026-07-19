<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();

            // e.g. "privacy_policy", "terms_and_conditions" — see App\Enums\LegalDocumentType
            $table->string('type')->unique();

            // {"en": "...", "ar": "..."} — JSON keeps this extensible to more
            // languages later without another migration/column.
            $table->json('content');

            $table->string('version')->default('1.0');

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
