<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A shelf life set on the PRINT SET LINE itself.
 *
 * Freeform lines have no linked item, so no rule can ever resolve for them
 * and every print demanded a hand-typed use-by. Storing the life on the line
 * closes that gap; on a linked line it acts as the most specific override in
 * the resolution chain (line > item > prep recipe > category).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_set_lines', function (Blueprint $table) {
            $table->decimal('shelf_life_value', 8, 2)->nullable()->after('storage_state');
            $table->string('shelf_life_unit', 10)->nullable()->after('shelf_life_value');
        });
    }

    public function down(): void
    {
        Schema::table('label_set_lines', function (Blueprint $table) {
            $table->dropColumn(['shelf_life_value', 'shelf_life_unit']);
        });
    }
};
