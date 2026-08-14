<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assignments (who has to do what, by when) and certificates (proof they did).
 *
 * ASSIGNMENTS ARE POLYMORPHIC ON BOTH SIDES, and both sides are deliberate.
 * The thing assigned is a course or a path, because a manager thinks in
 * "everyone does the induction path" as readily as "everyone does the new
 * allergen course". The party assigned is a trainee OR an outlet: assigning per
 * person does not survive turnover — the casual hired next week is not on
 * anybody's list — whereas assigning to the outlet means the requirement
 * belongs to the ROLE, and new starters inherit it on their first login.
 * Rather than a morphTo pair, both target columns are nullable and exactly one
 * is set; it keeps the "what is outstanding for this trainee" query a plain
 * two-branch where instead of a union across morph types.
 *
 * CERTIFICATES ARE ROWS, NOT FILES. The PDF is rendered on demand from the row,
 * so a company that rebrands does not end up with a filing cabinet of
 * certificates carrying the old logo, and there is nothing to back up. The
 * serial is the durable part — it is what a certificate is CHECKED against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // Exactly one of these two is set.
            $table->foreignId('training_course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('training_path_id')->nullable()->constrained()->cascadeOnDelete();

            // Exactly one of these two is set.
            $table->foreignId('lms_user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->cascadeOnDelete();

            $table->date('due_on')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->text('note')->nullable();

            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'due_on']);
            $table->index(['lms_user_id']);
            $table->index(['outlet_id']);
        });

        Schema::create('training_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lms_user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('training_quiz_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('training_attempt_id')->nullable()->constrained()->nullOnDelete();

            $table->string('serial', 40)->unique();
            $table->string('recipient_name');   // frozen: the name it was issued to
            $table->string('title');            // frozen: the course title at issue
            $table->decimal('percent', 5, 2)->default(0);
            $table->timestamp('issued_at');
            // Compliance courses expire; product knowledge does not.
            $table->date('expires_on')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'lms_user_id']);
            $table->index(['company_id', 'expires_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_certificates');
        Schema::dropIfExists('training_assignments');
    }
};
