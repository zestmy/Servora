<?php

use App\Models\UnitOfMeasure;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        UnitOfMeasure::firstOrCreate(['abbreviation' => 'roll'], [
            'name' => 'Roll', 'abbreviation' => 'roll', 'type' => 'count',
            'is_base_unit' => false, 'base_unit_factor' => 1.0, 'is_system' => true,
        ]);

        UnitOfMeasure::firstOrCreate(['abbreviation' => 'ream'], [
            'name' => 'Ream', 'abbreviation' => 'ream', 'type' => 'count',
            'is_base_unit' => false, 'base_unit_factor' => 1.0, 'is_system' => true,
        ]);
    }

    public function down(): void
    {
        UnitOfMeasure::whereIn('abbreviation', ['roll', 'ream'])->delete();
    }
};
