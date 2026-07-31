<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-company storage temperature ranges.
 *
 * The HACCP figures in ShelfLifeRule::STORAGE_TEMPERATURES are the common
 * ones, but they are not universal — a company may work to 0-5°C chillers,
 * or word the frozen limit differently for its own auditors.
 *
 * Only genuine overrides are stored: a state left at the default is absent
 * from the JSON rather than duplicated into it. That way changing a default
 * still reaches every company that never customised it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_settings', function (Blueprint $table) {
            $table->json('storage_temperatures')->nullable()->after('footer_text');
        });
    }

    public function down(): void
    {
        Schema::table('label_settings', function (Blueprint $table) {
            $table->dropColumn('storage_temperatures');
        });
    }
};
