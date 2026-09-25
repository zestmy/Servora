<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Audits module — the RECORD side. One row per visit to an outlet.
 *
 * AN AUDIT IS A SNAPSHOT OF ITS TEMPLATE. Sections and lines are COPIED in
 * when the audit is started, labels and points included, and the template is
 * never read again. A QA manager who re-words an item or changes its points
 * next month must not alter the score of an audit already signed off — and a
 * report opened two years later has to show the form as it was on the day.
 * `audit_template_id` is kept for filtering and reporting, not for rendering.
 *
 * Scores are CACHED on the audit and on each section by AuditScoreService,
 * and only that service writes them. The list screen shows a percentage for
 * every row and must not tot up three hundred lines to do it.
 *
 * STATUS
 *   draft         being conducted; every tap saves, nothing is final
 *   submitted     scored and locked; findings are now real
 *   acknowledged  the outlet has signed for it (name, position, signature)
 *   closed        every corrective action verified
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('template_name');                 // snapshot
            $table->string('template_code', 20)->nullable(); // snapshot
            $table->string('alt_language', 40)->nullable();  // snapshot
            $table->unsignedInteger('template_version')->default(1);
            $table->string('reference_number', 40)->nullable();
            $table->string('status', 20)->default('draft');
            $table->date('audit_date');
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->foreignId('auditor_id')->nullable()->constrained('users')->nullOnDelete();
            // Header field definitions are copied too, with the values typed
            // against them: [{key, label, type, value}]
            $table->json('header_values')->nullable();
            $table->text('notes')->nullable();

            // Cached by AuditScoreService. See that class for the arithmetic.
            $table->unsignedInteger('total_points')->default(0);     // area sections, before N/A
            $table->unsignedInteger('na_points')->default(0);
            $table->unsignedInteger('available_points')->default(0); // total - na  ("A")
            $table->unsignedInteger('lost_points')->default(0);      // area deductions
            $table->unsignedInteger('penalty_points')->default(0);   // penalty-section deductions
            $table->integer('score_points')->default(0);             // available - lost - penalty ("C")
            $table->decimal('score_percent', 5, 2)->nullable();
            $table->unsignedInteger('finding_count')->default(0);

            $table->timestamp('submitted_at')->nullable();
            $table->boolean('requires_acknowledgement')->default(true);
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('acknowledged_by_name')->nullable();
            $table->string('acknowledged_by_position')->nullable();
            $table->string('signature_path')->nullable();  // private disk
            $table->timestamp('closed_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'outlet_id', 'audit_date']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('audit_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_alt')->nullable();
            $table->string('scoring_mode', 10)->default('area');
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unsignedInteger('total_points')->default(0);
            $table->unsignedInteger('na_points')->default(0);
            $table->unsignedInteger('available_points')->default(0);
            $table->unsignedInteger('lost_points')->default(0);
            $table->integer('score_points')->default(0);
            $table->decimal('score_percent', 5, 2)->nullable();
            $table->unsignedInteger('answered_count')->default(0);
            $table->unsignedInteger('leaf_count')->default(0);
            $table->timestamps();
        });

        Schema::create('audit_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_section_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->unsignedBigInteger('template_item_id')->nullable();
            $table->string('number', 10)->nullable();
            $table->string('label', 500);
            $table->string('label_alt', 500)->nullable();
            $table->string('hint')->nullable();
            $table->string('type', 10)->default('check');
            $table->string('info_type', 10)->nullable();
            $table->unsignedSmallInteger('points')->default(0);
            $table->boolean('is_leaf')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            // What the auditor found. NULL until they tap something.
            $table->string('result', 5)->nullable();          // ok | nc | na
            $table->unsignedSmallInteger('points_lost')->default(0);
            $table->string('subject')->nullable();            // the product named on a product slot
            $table->text('info_value')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['audit_id', 'audit_section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_lines');
        Schema::dropIfExists('audit_sections');
        Schema::dropIfExists('audits');
    }
};
