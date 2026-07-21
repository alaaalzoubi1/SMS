<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Turns the plain key/value table into a proper landing-page section
     * builder:
     *  - type       : "section" (rendered on the landing page) or
     *                 "setting" (site-wide config, e.g. theme colors) —
     *                 see App\Enums\SiteContentType.
     *  - images     : json array of storage paths, used by sections like
     *                 "hero" (background) or "screenshots" (gallery).
     *  - sort_order : controls the order sections are rendered in.
     *  - is_active  : lets the admin hide a section without deleting it.
     *
     * `value` keeps holding the bilingual payload, conventionally shaped
     * as {"en": {...}, "ar": {...}} for sections, or a flat object for
     * settings (e.g. the "theme" key). See App\Models\SiteContent.
     */
    public function up(): void
    {
        Schema::table('site_contents', function (Blueprint $table) {
            $table->string('type')->default('section')->after('key');
            $table->json('images')->nullable()->after('value');
            $table->unsignedInteger('sort_order')->default(0)->after('images');
            $table->boolean('is_active')->default(true)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('site_contents', function (Blueprint $table) {
            $table->dropColumn(['type', 'images', 'sort_order', 'is_active']);
        });
    }
};
