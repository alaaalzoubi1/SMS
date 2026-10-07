<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The doctor_reservations.status enum was created without 'rejected', so the
 * rejection flow could never be stored even though the controllers and the
 * doctor UI expose it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('doctor_reservations')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite cannot ALTER a column type; rebuild the table instead.
            $this->rebuildSqliteTable();
            return;
        }

        DB::statement("ALTER TABLE doctor_reservations MODIFY `status` ENUM('pending','approved','rejected','cancelled','completed') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (!Schema::hasTable('doctor_reservations')) {
            return;
        }

        // Any rejected row must go before the value is removed again.
        DB::table('doctor_reservations')->where('status', 'rejected')->delete();

        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable(['pending', 'approved', 'cancelled', 'completed']);
            return;
        }

        DB::statement("ALTER TABLE doctor_reservations MODIFY `status` ENUM('pending','approved','cancelled','completed') NOT NULL DEFAULT 'pending'");
    }

    /**
     * @param  array<int, string>|null  $statuses
     */
    private function rebuildSqliteTable(?array $statuses = null): void
    {
        $statuses ??= ['pending', 'approved', 'rejected', 'cancelled', 'completed'];
        $quoted = collect($statuses)->map(fn ($s) => "'{$s}'")->implode(', ');

        $foreignKeyConstraintsEnabled = (bool) DB::selectOne('PRAGMA foreign_keys')->foreign_keys;

        Schema::disableForeignKeyConstraints();

        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement(<<<SQL
            CREATE TABLE doctor_reservations_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                doctor_service_id INTEGER NOT NULL,
                doctor_id INTEGER NOT NULL,
                date TEXT NOT NULL,
                start_time TEXT NULL,
                end_time TEXT NULL,
                status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ({$quoted})),
                reserved_by_admin INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )
        SQL);

        DB::statement(<<<'SQL'
            INSERT INTO doctor_reservations_new (
                id, user_id, doctor_service_id, doctor_id, date, start_time,
                end_time, status, reserved_by_admin, created_at, updated_at, deleted_at
            )
            SELECT id, user_id, doctor_service_id, doctor_id, date, start_time,
                end_time, status, reserved_by_admin, created_at, updated_at, deleted_at
            FROM doctor_reservations
        SQL);

        DB::statement('DROP TABLE doctor_reservations');
        DB::statement('ALTER TABLE doctor_reservations_new RENAME TO doctor_reservations');

        DB::statement(<<<'SQL'
            CREATE INDEX doctor_reservations_date_index ON doctor_reservations (date)
        SQL);
        DB::statement('CREATE INDEX doctor_reservations_reserved_by_admin_index ON doctor_reservations (reserved_by_admin)');
        DB::statement('CREATE INDEX doctor_reservations_doctor_id_index ON doctor_reservations (doctor_id)');
        DB::statement('CREATE INDEX doctor_reservations_user_id_index ON doctor_reservations (user_id)');
        DB::statement('CREATE INDEX doctor_reservations_status_index ON doctor_reservations (status)');
        DB::statement('CREATE INDEX doctor_reservations_doctor_id_date_status_index ON doctor_reservations (doctor_id, date, status)');
        DB::statement('CREATE INDEX doctor_reservations_deleted_at_index ON doctor_reservations (deleted_at)');

        if ($foreignKeyConstraintsEnabled) {
            DB::statement('PRAGMA foreign_keys = ON');

            Schema::enableForeignKeyConstraints();
        }
    }
};