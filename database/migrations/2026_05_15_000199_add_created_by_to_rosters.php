<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rosters', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('company_id')
                  ->constrained('users')->nullOnDelete();
            $table->foreignId('last_edited_by')->nullable()->after('created_by')
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('last_edited_at')->nullable()->after('last_edited_by');
        });
    }

    public function down(): void
    {
        Schema::table('rosters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('last_edited_by');
            $table->dropColumn('last_edited_at');
        });
    }
};
