<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });

        // Backfill: every user's current company becomes their first membership.
        //
        // The timestamp comes from PHP rather than SQL's NOW(), which SQLite does not
        // have — the raw statement made this migration MySQL-only and so unrunnable on
        // the test suite's connection.
        $now = now();

        DB::table('users')
            ->whereNotNull('company_id')
            ->orderBy('id')
            ->chunkById(500, function ($users) use ($now) {
                DB::table('company_user')->insertOrIgnore(
                    $users->map(fn ($u) => [
                        'company_id' => $u->company_id,
                        'user_id'    => $u->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user');
    }
};
