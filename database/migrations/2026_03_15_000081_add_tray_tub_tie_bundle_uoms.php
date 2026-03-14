<?php

use App\Models\UnitOfMeasure;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        UnitOfMeasure::firstOrCreate(['abbreviation' => 'tub'], [
            'name' => 'Tub', 'abbreviation' => 'tub', 'type' => 'count',
            'is_base_unit' => false, 'base_unit_factor' => 1.0, 'is_system' => true,
        ]);

        UnitOfMeasure::firstOrCreate(['abbreviation' => 'tie'], [
            'name' => 'Tie', 'abbreviation' => 'tie', 'type' => 'count',
            'is_base_unit' => false, 'base_unit_factor' => 1.0, 'is_system' => true,
        ]);

        UnitOfMeasure::firstOrCreate(['abbreviation' => 'bundle'], [
            'name' => 'Bundle', 'abbreviation' => 'bundle', 'type' => 'count',
            'is_base_unit' => false, 'base_unit_factor' => 1.0, 'is_system' => true,
        ]);
    }

    public function down(): void
    {
        UnitOfMeasure::whereIn('abbreviation', ['tub', 'tie', 'bundle'])->delete();
    }
};
