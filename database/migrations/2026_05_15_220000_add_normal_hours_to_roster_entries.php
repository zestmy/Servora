<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roster_entries', function (Blueprint $table) {
            // Custom normal hours per entry (overrides outlet setting)
            // null = use outlet default
            $table->decimal('normal_hours', 5, 2)->nullable()->after('rest_duration');
        });
    }

    public function down(): void
    {
        Schema::table('roster_entries', function (Blueprint $table) {
            $table->dropColumn('normal_hours');
        });
    }
};
