<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Shown on the public pricing/sales surfaces; hidden plans stay
            // assignable by admins and reachable via direct checkout links.
            $table->boolean('is_public')->default(true)->after('is_active');
        });

        Schema::table('users', function (Blueprint $table) {
            // Activity tracking that works regardless of the session driver
            // (prod sessions moved to Redis — the DB sessions table is stale).
            $table->timestamp('last_active_at')->nullable()->after('suspended_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_active_at');
        });
    }
};
