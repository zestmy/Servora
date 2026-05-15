<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roster_entries', function (Blueprint $table) {
            // Leave type: off, al, rph, mc, rdo, ch
            $table->string('leave_type', 20)->nullable()->after('is_off_day');
            // Sort order for drag-and-drop reordering
            $table->integer('sort_order')->default(0)->after('leave_type');
        });
    }

    public function down(): void
    {
        Schema::table('roster_entries', function (Blueprint $table) {
            $table->dropColumn(['leave_type', 'sort_order']);
        });
    }
};
