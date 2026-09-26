<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A conditional pass is a promise to come back. This makes it one the
 * system keeps track of.
 *
 *   reaudit_due_on   set at submit when the outcome is conditional: the audit
 *                    date plus the form's `reaudit_days`; null otherwise
 *   reaudit_of_id    on the FOLLOW-UP audit, the conditional audit it answers
 *
 * A re-audit is satisfied when a follow-up that names it is submitted. The
 * follow-up is usually started from the "Start re-audit" button, which sets
 * the link up front; an audit of the same form at the same outlet submitted
 * afterwards without the button is linked at submit time as well, so a
 * re-audit conducted the ordinary way still counts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->date('reaudit_due_on')->nullable()->after('outcome_reasons');
            $table->foreignId('reaudit_of_id')->nullable()->after('reaudit_due_on')
                ->constrained('audits')->nullOnDelete();

            $table->index(['company_id', 'reaudit_due_on']);
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'reaudit_due_on']);
            $table->dropConstrainedForeignId('reaudit_of_id');
            $table->dropColumn('reaudit_due_on');
        });
    }
};
