<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What an audit turned up, and what the outlet is doing about it.
 *
 * A FINDING IS A NON-CONFORMANCE: one per line the auditor marked NC. It is
 * written the moment the line is marked, while the audit is still a draft, so
 * the photograph taken at the chiller attaches to something — and it is
 * removed again if the auditor changes their mind and taps OK. Once the audit
 * is submitted the findings are the record.
 *
 * Findings carry `company_id` and `outlet_id` of their own, denormalised from
 * the audit, because the screen this module exists for is "every open
 * non-conformance across my outlets, grouped by who owns the fix" — and that
 * screen must not join through three hundred audit lines to answer.
 *
 * A CORRECTIVE ACTION belongs to a finding and to a PERSON — an Employee of
 * the outlet, whose designation (Manager, Chef, Shift Officer) is what the
 * summary groups by. One finding may need more than one action (fix the
 * thing, and retrain the person), so it is a table rather than columns.
 *
 *   open → in_progress → done → verified
 *
 * `done` is the outlet's claim; `verified` is the auditor's. The audit closes
 * when every action on it is verified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_line_id')->constrained()->cascadeOnDelete();
            $table->string('severity', 10)->default('minor');  // major (penalty section) | minor
            $table->string('section_name');                    // denormalised for lists
            $table->string('item_label', 500);                 // denormalised for lists
            $table->unsignedSmallInteger('points_lost')->default(0);
            $table->text('description')->nullable();           // the auditor's note
            $table->string('status', 12)->default('open');     // open | resolved  (derived from actions)
            $table->timestamps();

            $table->unique('audit_line_id');
            $table->index(['company_id', 'outlet_id', 'status']);
        });

        Schema::create('audit_finding_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_finding_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');                       // public disk
            $table->string('caption')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('corrective_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_finding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('description');
            $table->date('due_date')->nullable();
            $table->string('status', 12)->default('open');     // open | in_progress | done | verified
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_note')->nullable();
            $table->string('evidence_path')->nullable();       // public disk
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'outlet_id', 'status']);
            $table->index(['company_id', 'owner_employee_id']);
            $table->index(['company_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corrective_actions');
        Schema::dropIfExists('audit_finding_photos');
        Schema::dropIfExists('audit_findings');
    }
};
