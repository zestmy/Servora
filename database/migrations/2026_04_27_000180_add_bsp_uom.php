<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // tsp (5 ml) and tbsp (15 ml) were added in migration 000065.
        // Adding bsp (big/serving spoon, ~20 ml) as requested.
        if (DB::table('units_of_measure')->where('abbreviation', 'bsp')->doesntExist()) {
            DB::table('units_of_measure')->insert([
                'name'             => 'Big Spoon',
                'abbreviation'     => 'bsp',
                'type'             => 'volume',
                'is_base_unit'     => false,
                'base_unit_factor' => 0.02,   // 1 bsp = 20 ml = 0.02 L
                'is_system'        => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('units_of_measure')->where('abbreviation', 'bsp')->delete();
    }
};
