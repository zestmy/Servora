<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE ingredients SET name = UPPER(name) WHERE name IS NOT NULL');
        DB::statement('UPDATE recipes SET name = UPPER(name) WHERE name IS NOT NULL');
    }

    public function down(): void
    {
        // Cannot reverse — original casing is lost
    }
};
