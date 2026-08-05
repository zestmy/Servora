<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A company-managed catalogue of certifications and training courses, and the
 * per-employee records against it.
 *
 * The three built-in documents (typhoid, food handler, halal) stay as columns
 * on `employees` — they are near-universal in F&B, every screen, export and
 * CSV already speaks them, and moving live data for the sake of symmetry would
 * be a migration with nothing to show for it. This is the "everything else"
 * half: barista certification, chemical handling, fire safety, whatever a
 * given company runs, added without a code change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certification_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            // FALSE for a one-off course that never lapses — without this, an
            // attendance record with no expiry would be reported forever as
            // "expiry date missing" when there is nothing to record.
            $table->boolean('has_expiry')->default(true);
            // TRUE means every employee is expected to hold it, so NOT having
            // it is reportable. Optional courses stay silent when absent —
            // otherwise every specialist course flags the whole payroll.
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'is_active', 'sort_order']);
        });

        Schema::create('employee_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certification_type_id')->constrained()->cascadeOnDelete();
            $table->string('reference_no', 100)->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->timestamps();

            // One current record per course per employee: a renewal updates the
            // dates rather than stacking a second row, so "when does this
            // employee's fire safety lapse" has exactly one answer.
            $table->unique(['employee_id', 'certification_type_id']);
            $table->index(['company_id', 'expires_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_certifications');
        Schema::dropIfExists('certification_types');
    }
};
