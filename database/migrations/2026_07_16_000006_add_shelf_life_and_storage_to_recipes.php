<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prep items can declare a shelf life (value + unit: minutes/hours/days/
 * weeks/months) and a storing instruction (chill/frozen/ambient), shown to
 * employees in the LMS SOP page and the SOP PDF exports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->decimal('shelf_life_value', 8, 2)->nullable()->after('batch_multipliers');
            $table->string('shelf_life_unit', 10)->nullable()->after('shelf_life_value');
            $table->string('storage_instruction', 10)->nullable()->after('shelf_life_unit');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn(['shelf_life_value', 'shelf_life_unit', 'storage_instruction']);
        });
    }
};
