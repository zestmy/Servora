<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-outlet SOP access for LMS (training portal) users. Until now a trainee
 * had a single registration outlet; access can now be granted/revoked per
 * outlet — including central-kitchen outlets — from Settings > Training Portal.
 *
 * Backfill: each existing user's registration outlet becomes their initial
 * access row. Users with no rows fall back to legacy behavior in code
 * (registration outlet, or everything when registered without an outlet).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_user_outlets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lms_user_id')->constrained('lms_users')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['lms_user_id', 'outlet_id']);
        });

        $rows = DB::table('lms_users')
            ->whereNotNull('outlet_id')
            ->whereNull('deleted_at')
            ->get(['id', 'outlet_id']);

        foreach ($rows as $row) {
            DB::table('lms_user_outlets')->insert([
                'lms_user_id' => $row->id,
                'outlet_id'   => $row->outlet_id,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_user_outlets');
    }
};
