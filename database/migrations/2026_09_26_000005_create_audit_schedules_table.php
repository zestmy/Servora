<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When each outlet is next due which audit.
 *
 * One row per (form, outlet) that recurs: "ROSE at IOI City Mall, quarterly,
 * next due 1 Dec". Starting an audit FROM the schedule stamps the schedule on
 * the audit and rolls `next_due_on` forward by the frequency — so the plan
 * keeps itself, and "overdue" is a date comparison rather than a report.
 *
 * Nothing is sent: there is no user-facing notification channel in the
 * product today. The Audits list carries a due strip instead, which is where
 * the auditor already looks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('frequency', 12)->default('quarterly'); // weekly | monthly | quarterly | half_yearly | yearly
            $table->date('next_due_on');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_audit_id')->nullable()->constrained('audits')->nullOnDelete();
            $table->date('last_started_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'next_due_on']);
            $table->index(['company_id', 'outlet_id']);
        });

        Schema::table('audits', function (Blueprint $table) {
            $table->foreignId('audit_schedule_id')->nullable()->after('audit_template_id')
                ->constrained('audit_schedules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('audit_schedule_id');
        });

        Schema::dropIfExists('audit_schedules');
    }
};
