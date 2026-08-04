<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a company admin remove a clock-in — without the record leaving.
 *
 * A punch is an attendance record and, when somebody was late, a payroll
 * one: it carries the ringgit taken off that person's service charge. Rows
 * like that should not vanish on a click. Deleting one is occasionally
 * necessary — a test punch, a double tap at the door, somebody clocking in
 * on a colleague's phone — but "necessary" and "gone forever" are different
 * requests, and only the first one was made.
 *
 * Soft deletion also happens to be the whole implementation of the money
 * side. LatePenalties::forPeriod() computes deductions live from this table
 * and drops only the CompanyScope, so Laravel's SoftDeletingScope stays on
 * and a deleted punch stops counting everywhere at once — the review queue,
 * the attendance export, and the service charge — with no call site changed
 * and none able to be forgotten.
 *
 * deleted_by is separate from reviewed_by on purpose. Rejecting a punch and
 * deleting it are different acts by potentially different people, and
 * collapsing them would lose which one happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clock_events', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clock_events', function (Blueprint $table) {
            // dropConstrainedForeignId, not dropConstrainedForeignKey — the
            // latter does not exist on Blueprint and throws. It drops the
            // foreign key and then the column, in that order, which is the
            // order both MySQL and sqlite require.
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropSoftDeletes();
        });
    }
};
