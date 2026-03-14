<?php

use App\Models\UnitOfMeasure;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        UnitOfMeasure::firstOrCreate(['abbreviation' => 'pair'], [
            'name' => 'Pair', 'abbreviation' => 'pair', 'type' => 'count',
            'is_base_unit' => false, 'base_unit_factor' => 1.0, 'is_system' => true,
        ]);

        UnitOfMeasure::firstOrCreate(['abbreviation' => 'sheet'], [
            'name' => 'Sheet', 'abbreviation' => 'sheet', 'type' => 'count',
            'is_base_unit' => false, 'base_unit_factor' => 1.0, 'is_system' => true,
        ]);

        UnitOfMeasure::firstOrCreate(['abbreviation' => 'set'], [
            'name' => 'Set', 'abbreviation' => 'set', 'type' => 'count',
            'is_base_unit' => false, 'base_unit_factor' => 1.0, 'is_system' => true,
        ]);
    }

    public function down(): void
    {
        UnitOfMeasure::whereIn('abbreviation', ['pair', 'sheet', 'set'])->delete();
    }
};
