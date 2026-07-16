<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prep items can define extra batch sizes as recipe multiples (1 Recipe =
 * the base yield quantity; e.g. 0.5, 1.5, 2). Ingredient quantities scale
 * per multiple and are shown side by side in the prep form and LMS SOPs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->json('batch_multipliers')->nullable()->after('yield_uom_id');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('batch_multipliers');
        });
    }
};
