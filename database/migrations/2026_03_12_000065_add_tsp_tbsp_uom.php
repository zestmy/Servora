<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('units_of_measure')->insert([
            [
                'name'             => 'Teaspoon',
                'abbreviation'     => 'tsp',
                'type'             => 'volume',
                'is_base_unit'     => false,
                'base_unit_factor' => 0.005,    // 1 tsp = 5 ml = 0.005 L
                'is_system'        => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [
                'name'             => 'Tablespoon',
                'abbreviation'     => 'tbsp',
                'type'             => 'volume',
                'is_base_unit'     => false,
                'base_unit_factor' => 0.015,    // 1 tbsp = 15 ml = 0.015 L
                'is_system'        => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('units_of_measure')->whereIn('abbreviation', ['tsp', 'tbsp'])->delete();
    }
};
