<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $uoms = [
            ['name' => 'Gram',  'abbreviation' => 'gm',    'type' => 'weight', 'base_unit_factor' => 0.001, 'is_system' => true],
            ['name' => 'Slice', 'abbreviation' => 'slice',  'type' => 'count',  'base_unit_factor' => null,  'is_system' => true],
            ['name' => 'Bar',   'abbreviation' => 'bar',    'type' => 'count',  'base_unit_factor' => null,  'is_system' => true],
            ['name' => 'Can',   'abbreviation' => 'can',    'type' => 'count',  'base_unit_factor' => null,  'is_system' => true],
        ];

        foreach ($uoms as $uom) {
            DB::table('units_of_measure')->updateOrInsert(
                ['abbreviation' => $uom['abbreviation']],
                array_merge($uom, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    public function down(): void
    {
        DB::table('units_of_measure')->whereIn('abbreviation', ['gm', 'slice', 'bar', 'can'])->delete();
    }
};
