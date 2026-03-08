<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('units_of_measure')->where('abbreviation', 'srv')->exists()) {
            DB::table('units_of_measure')->insert([
                'name'             => 'Serving',
                'abbreviation'     => 'srv',
                'type'             => 'count',
                'is_base_unit'     => true,
                'base_unit_factor' => 1,
                'is_system'        => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        if (! DB::table('units_of_measure')->where('abbreviation', 'portion')->exists()) {
            DB::table('units_of_measure')->insert([
                'name'             => 'Portion',
                'abbreviation'     => 'portion',
                'type'             => 'count',
                'is_base_unit'     => true,
                'base_unit_factor' => 1,
                'is_system'        => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('units_of_measure')->whereIn('abbreviation', ['srv', 'portion'])->delete();
    }
};
