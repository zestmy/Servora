<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closing the loop on a printed label.
 *
 * The expiring-today dashboard reads unresolved labels off label_prints.
 * Without somewhere to record that a chef has dealt with one, the list
 * grows forever and every expired label from last month sits at the top of
 * it — which is exactly how a compliance screen gets ignored.
 *
 * Three resolutions, deliberately distinct:
 *
 *   used      — consumed normally, nothing to cost
 *   wasted    — binned AND costed into a wastage record
 *   discarded — binned but NOT costed, because the label had no linked item
 *               to price (a freeform "chef's special sauce" has no cost)
 *
 * Keeping 'discarded' separate from 'wasted' means the wastage figures stay
 * honest: everything counted as wasted has a real cost behind it, and the
 * uncosted bin events are still visible rather than being quietly folded in
 * at zero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_prints', function (Blueprint $table) {
            $table->timestamp('resolved_at')->nullable()->after('status');
            $table->foreignId('resolved_by')->nullable()->after('resolved_at')
                ->constrained('users')->nullOnDelete();
            $table->string('resolution', 20)->nullable()->after('resolved_by');
            $table->foreignId('wastage_record_id')->nullable()->after('resolution')
                ->constrained('wastage_records')->nullOnDelete();
        });

        Schema::table('label_prints', function (Blueprint $table) {
            // The dashboard's query: unresolved labels for an outlet, by
            // expiry. resolved_at leads because it is the selective one —
            // most rows are resolved once a kitchen is using this.
            $table->index(
                ['company_id', 'outlet_id', 'resolved_at', 'end_at'],
                'label_prints_outlet_unresolved_expiry_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('label_prints', function (Blueprint $table) {
            $table->dropIndex('label_prints_outlet_unresolved_expiry_idx');
            $table->dropForeign(['resolved_by']);
            $table->dropForeign(['wastage_record_id']);
            $table->dropColumn(['resolved_at', 'resolved_by', 'resolution', 'wastage_record_id']);
        });
    }
};
