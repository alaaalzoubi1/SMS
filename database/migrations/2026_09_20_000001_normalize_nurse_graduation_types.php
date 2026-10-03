<?php

use App\Enums\GraduationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repairs rows written before App\Enums\GraduationType existed.
 *
 * The `graduation_type` column is a native MySQL enum limited to the five
 * canonical values, so any row holding a short alias can only exist if it
 * was inserted while the column definition was different (or on a database
 * where the enum was widened manually). This migration rewrites those rows
 * to the canonical value; rows already canonical are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $aliases = GraduationType::aliases();

        foreach ($aliases as $alias => $canonical) {
            DB::table('nurses')
                ->where('graduation_type', $alias)
                ->update(['graduation_type' => $canonical]);
        }
    }

    public function down(): void
    {
        // Irreversible by nature: we cannot tell which short value a
        // canonical row originally had. Nothing to undo.
    }
};
