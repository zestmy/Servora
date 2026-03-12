<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('units_of_measure')->updateOrInsert(
            ['abbreviation' => 'block'],
            ['name' => 'Block', 'abbreviation' => 'block', 'type' => 'count', 'base_unit_factor' => 1, 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('units_of_measure')->where('abbreviation', 'block')->delete();
    }
};
