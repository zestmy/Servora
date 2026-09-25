<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Audits module — the DEFINITION side.
 *
 * NOT the activity trail. `audit_logs`, `audit.view` and App\Livewire\Audit are
 * the record of who changed what; this is the module a QA department uses to
 * walk an outlet with a scored checklist (a ROSE audit, a halal audit, a
 * pre-opening inspection) and write up what they found. Everything here is
 * prefixed `audits`/`audit_template` so the two never share a name.
 *
 * A template is a TREE OF SCORED ITEMS, because that is what every real audit
 * form turns out to be once you read one closely. The sample ROSE form has
 * numbered items ("1. Equipment in good and clean condition") that split into
 * lettered sub-items ("a. Bunn Coffee Maker — 2 pts") — the points live on the
 * leaves and the parent is just a heading with a subtotal. Sections group the
 * items ("Bar area", "Kitchen area") and carry their own score card.
 *
 * `scoring_mode` on a section is what makes the total configurable without a
 * formula editor. `area` sections pool into the total; a `penalty` section's
 * lost points are subtracted from that total ONLY — its points are never in
 * the pool. That is exactly how "Main food safety / halal non-compliances"
 * works on the ROSE form: 14 critical items whose deductions come off the
 * whole score after the areas have been added up.
 *
 * Labels carry a second language beside the first (`label_alt`) rather than a
 * translation table, because the printed form shows both on the same line and
 * an auditor reads them together. The template names what the second language
 * is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20)->nullable();          // short tag, e.g. ROSE
            $table->text('description')->nullable();
            $table->string('alt_language', 40)->nullable();  // what label_alt is, e.g. "Bahasa Malaysia"
            $table->boolean('is_active')->default(true);
            // Bumped every time the structure is saved. An audit stamps the
            // version it was started from, so two audits of the same template
            // can be told apart when the form has changed between them.
            $table->unsignedInteger('version')->default(1);
            // [{key, label, type: text|number|time|textarea|employee, required}]
            $table->json('header_fields')->nullable();
            $table->boolean('requires_acknowledgement')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'is_active']);
        });

        Schema::create('audit_template_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_template_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_alt')->nullable();
            $table->string('scoring_mode', 10)->default('area'); // area | penalty
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('audit_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_template_section_id')->constrained()->cascadeOnDelete();
            // Self-reference. Declared without a constraint so a section can
            // be deleted in one statement and SQLite (the test database) does
            // not have to order the cascade.
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('number', 10)->nullable();   // "1", "a" — display only
            $table->string('label', 500);
            $table->string('label_alt', 500)->nullable();
            $table->string('hint')->nullable();          // "Setting: 0°C – 4°C"
            // check   — pass / non-conformance / not applicable, worth `points`
            // product — a slot the auditor names on the day ("Kaya toast"),
            //           scored through its child criteria
            // info    — an unscored note or number the auditor fills in
            $table->string('type', 10)->default('check');
            $table->string('info_type', 10)->nullable();  // text | number | time | textarea
            $table->unsignedSmallInteger('points')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_template_items');
        Schema::dropIfExists('audit_template_sections');
        Schema::dropIfExists('audit_templates');
    }
};
