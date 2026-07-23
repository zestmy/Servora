<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Capability flags become per-company: the company_user pivot is the truth,
 * the users-table columns remain as a cache of the ACTIVE company's flags
 * (refreshed by User::switchToCompany / refreshCapabilityCache).
 */
return new class extends Migration
{
    private const CAPABILITIES = [
        'can_manage_users', 'can_approve_po', 'can_approve_pr', 'can_delete_records',
        'can_view_all_outlets', 'can_receive_grn', 'can_manage_invoices',
    ];

    public function up(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            foreach (self::CAPABILITIES as $cap) {
                $table->boolean($cap)->default(false);
            }
        });

        // Backfill every membership with the user's current (account-global)
        // flags so effective access is unchanged at deploy time.
        $sets = implode(', ', array_map(fn ($c) => "cu.$c = u.$c", self::CAPABILITIES));
        DB::statement("UPDATE company_user cu JOIN users u ON u.id = cu.user_id SET $sets");
    }

    public function down(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->dropColumn(self::CAPABILITIES);
        });
    }
};
