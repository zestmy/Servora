<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pass / conditional pass / fail.
 *
 * A percentage on its own misreads a ROSE audit: the fourteen critical
 * food-safety and halal items cost 10 points each against a 707-point pool,
 * so an outlet can fail five of them and still score 93%. The outcome is
 * therefore a judgement over THREE things — the total, the number of major
 * non-conformances, and the weakest area section — and the thresholds are
 * rules on the FORM (`audit_templates.outcome_rules`), copied onto each audit
 * at start (`audits.outcome_rules`) so that tightening the rules next
 * quarter never re-grades an audit already signed for.
 *
 *   outcome          pass | conditional | fail   (null while a draft)
 *   major_count      NC lines in penalty sections
 *   outcome_reasons  the rule(s) that decided it, in words, for the screen and PDF
 *
 * See AuditScoreService::DEFAULT_RULES for the defaults and evaluate() for
 * the arithmetic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_templates', function (Blueprint $table) {
            $table->json('outcome_rules')->nullable()->after('header_fields');
        });

        Schema::table('audits', function (Blueprint $table) {
            $table->string('outcome', 12)->nullable()->after('finding_count');
            $table->unsignedInteger('major_count')->default(0)->after('outcome');
            $table->json('outcome_rules')->nullable()->after('major_count');
            $table->json('outcome_reasons')->nullable()->after('outcome_rules');

            $table->index(['company_id', 'outcome']);
        });

        // Audits already submitted get an outcome under the default rules, so
        // the list does not show a blank for everything conducted before today.
        $service = app(\App\Services\Audits\AuditScoreService::class);

        \App\Models\Audit::withoutGlobalScopes()
            ->where('status', '!=', 'draft')
            ->orderBy('id')
            ->each(fn ($audit) => $service->recalculate($audit));
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'outcome']);
            $table->dropColumn(['outcome', 'major_count', 'outcome_rules', 'outcome_reasons']);
        });

        Schema::table('audit_templates', function (Blueprint $table) {
            $table->dropColumn('outcome_rules');
        });
    }
};
