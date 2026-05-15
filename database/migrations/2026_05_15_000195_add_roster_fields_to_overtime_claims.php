<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_claims', function (Blueprint $table) {
            $table->string('source', 50)->default('manual')->after('status'); // 'manual' or 'roster'
            $table->foreignId('roster_entry_id')->nullable()->after('source')
                  ->constrained('roster_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('overtime_claims', function (Blueprint $table) {
            $table->dropConstrainedForeignId('roster_entry_id');
            $table->dropColumn('source');
        });
    }
};
