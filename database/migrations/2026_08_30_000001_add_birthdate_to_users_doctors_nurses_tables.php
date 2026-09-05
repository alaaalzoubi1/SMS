<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a real birthdate column (instead of the static age) to users,
     * doctors and nurses. The existing `age` column is kept and backfilled
     * from birthdate so admin filters that still query the age column keep
     * working. Reading `->age` on the models now always recomputes from
     * birthdate, so it no longer goes stale after a year.
     */
    public function up(): void
    {
        foreach (['users', 'doctors', 'nurses'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->date('birthdate')->nullable()->after('age');
            });
        }

        if (Schema::hasTable('users')) {
            $this->backfillAge('users');
        }
        if (Schema::hasTable('doctors')) {
            $this->backfillAge('doctors');
        }
        if (Schema::hasTable('nurses')) {
            $this->backfillAge('nurses');
        }
    }

    public function down(): void
    {
        foreach (['users', 'doctors', 'nurses'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('birthdate');
            });
        }
    }

    private function backfillAge(string $table): void
    {
        $rows = DB::table($table)->whereNotNull('age')->get(['id', 'age']);

        foreach ($rows as $row) {
            DB::table($table)->where('id', $row->id)->update([
                'birthdate' => now()->subYears((int) $row->age)->format('Y-m-d'),
            ]);
        }
    }
};