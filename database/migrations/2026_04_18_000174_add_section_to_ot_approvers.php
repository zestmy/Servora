<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Let OT approvers be scoped to a (outlet, section) pair rather than just an
 * outlet. Null on either axis means "any" — so existing rows stay valid as
 * "approve all sections at this outlet".
 *
 * Dropping the old (company, user, outlet) unique index was blocked by MySQL
 * because the outlet_id foreign key was using it, so we:
 *   1. drop the outlet_id FK,
 *   2. swap the unique indexes,
 *   3. re-create the outlet_id FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add section_id FK (independent change, safe to do first).
        if (! Schema::hasColumn('overtime_claim_approvers', 'section_id')) {
            Schema::table('overtime_claim_approvers', function (Blueprint $table) {
                $table->foreignId('section_id')->nullable()->after('outlet_id')
                      ->constrained()->nullOnDelete();
            });
        }

        // Release the outlet_id FK so we can drop the unique index it was
        // piggy-backing on for enforcement.
        $outletFk = DB::select("
            SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'overtime_claim_approvers'
              AND CONSTRAINT_NAME = 'overtime_claim_approvers_outlet_id_foreign'
            LIMIT 1
        ");
        if (! empty($outletFk)) {
            Schema::table('overtime_claim_approvers', function (Blueprint $table) {
                $table->dropForeign(['outlet_id']);
            });
        }

        // Swap the old (company, user, outlet) unique for one that also
        // distinguishes by section.
        try {
            Schema::table('overtime_claim_approvers', function (Blueprint $table) {
                $table->dropUnique(['company_id', 'user_id', 'outlet_id']);
            });
        } catch (\Throwable $e) {}

        Schema::table('overtime_claim_approvers', function (Blueprint $table) {
            $table->unique(
                ['company_id', 'user_id', 'outlet_id', 'section_id'],
                'ot_approvers_scope_unique'
            );
        });

        // Re-create the outlet_id FK.
        Schema::table('overtime_claim_approvers', function (Blueprint $table) {
            $table->foreign('outlet_id')->references('id')->on('outlets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Reverse in the same order: drop outlet_id FK, swap unique back,
        // drop section_id FK+column, re-add outlet_id FK.
        try {
            Schema::table('overtime_claim_approvers', function (Blueprint $table) {
                $table->dropForeign(['outlet_id']);
            });
        } catch (\Throwable $e) {}

        try {
            Schema::table('overtime_claim_approvers', function (Blueprint $table) {
                $table->dropUnique('ot_approvers_scope_unique');
            });
        } catch (\Throwable $e) {}

        Schema::table('overtime_claim_approvers', function (Blueprint $table) {
            $table->unique(['company_id', 'user_id', 'outlet_id']);
        });

        if (Schema::hasColumn('overtime_claim_approvers', 'section_id')) {
            Schema::table('overtime_claim_approvers', function (Blueprint $table) {
                $table->dropConstrainedForeignId('section_id');
            });
        }

        Schema::table('overtime_claim_approvers', function (Blueprint $table) {
            $table->foreign('outlet_id')->references('id')->on('outlets')->nullOnDelete();
        });
    }
};
