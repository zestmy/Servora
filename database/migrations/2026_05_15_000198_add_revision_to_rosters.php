<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rosters', function (Blueprint $table) {
            $table->integer('revision')->default(1)->after('notes');
            $table->text('revision_notes')->nullable()->after('revision');
        });
    }

    public function down(): void
    {
        Schema::table('rosters', function (Blueprint $table) {
            $table->dropColumn(['revision', 'revision_notes']);
        });
    }
};
