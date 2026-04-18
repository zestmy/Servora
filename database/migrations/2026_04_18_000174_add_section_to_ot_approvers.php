<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let OT approvers be scoped to a (outlet, section) pair rather than just an
 * outlet. Null on either axis means "any" — so existing rows stay valid as
 * "approve all sections at this outlet".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_claim_approvers', function (Blueprint $table) {
            // Replace the old (company, user, outlet) uniqueness with one that
            // also includes section.
            try { $table->dropUnique(['company_id', 'user_id', 'outlet_id']); } catch (\Throwable $e) {}

            if (! Schema::hasColumn('overtime_claim_approvers', 'section_id')) {
                $table->foreignId('section_id')->nullable()->after('outlet_id')
                      ->constrained()->nullOnDelete();
            }

            $table->unique(['company_id', 'user_id', 'outlet_id', 'section_id'], 'ot_approvers_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::table('overtime_claim_approvers', function (Blueprint $table) {
            try { $table->dropUnique('ot_approvers_scope_unique'); } catch (\Throwable $e) {}

            if (Schema::hasColumn('overtime_claim_approvers', 'section_id')) {
                $table->dropConstrainedForeignId('section_id');
            }

            $table->unique(['company_id', 'user_id', 'outlet_id']);
        });
    }
};
