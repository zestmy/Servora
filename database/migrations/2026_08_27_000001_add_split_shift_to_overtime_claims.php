<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The deliberate exception to one-claim-per-employee-per-date.
 *
 * A cook who works OT over lunch and again at dinner has two separate blocks
 * of overtime on one date, and the duplicate gate refuses the second one. The
 * gate is right to: two claims for ONE shift are two lots of pay, and nothing
 * downstream reconciles that. But the same shape means two legitimate things,
 * and only the person entering it knows which.
 *
 * So this is an acknowledgement, not a switch. `is_split_shift` records that
 * somebody was shown the clash and said the hours are genuinely separate, and
 * the two audit columns record who said it and when — an override on a gate
 * that guards pay is worth nothing if you cannot find out afterwards who used
 * it. Same shape as approved_by/approved_at and marked_paid_by alongside it.
 *
 * It also has to persist, not merely permit. The duplicate notice on the
 * claims screen counts UNACKNOWLEDGED claims per employee per date, so a
 * split shift stops being reported the moment it is acknowledged. Without
 * that, every legitimate split shift would sit in the warning bar for good and
 * train people to scroll past a notice that exists to stop double payments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_claims', function (Blueprint $table) {
            $table->boolean('is_split_shift')->default(false)->after('reason');
            $table->foreignId('split_shift_ack_by')->nullable()->after('is_split_shift')
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('split_shift_ack_at')->nullable()->after('split_shift_ack_by');
        });
    }

    public function down(): void
    {
        Schema::table('overtime_claims', function (Blueprint $table) {
            $table->dropConstrainedForeignId('split_shift_ack_by');
            $table->dropColumn(['is_split_shift', 'split_shift_ack_at']);
        });
    }
};
