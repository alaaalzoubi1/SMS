<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The editor of a legal document is an admin *account*, but `updated_by`
 * pointed at `users`, which only holds patient profiles. Every save therefore
 * either failed the FK check or stored an id that resolved to an unrelated
 * patient. Keep the legacy column (nullable, unconstrained) and add a proper
 * reference to `accounts`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->dropForeign(['updated_by']);

            $table->unsignedBigInteger('updated_by')->nullable()->change();

            $table->foreignId('updated_by_account_id')
                ->nullable()
                ->after('updated_by')
                ->constrained('accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->dropForeign(['updated_by_account_id']);
            $table->dropColumn('updated_by_account_id');

            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }
};