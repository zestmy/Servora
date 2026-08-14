<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Courses — the training material a quiz is built from.
 *
 * A course is one body of teaching: a recipe's SOP, an uploaded policy PDF, or
 * text somebody pasted in. All three land in the same `content` column as plain
 * text, because everything downstream — the AI question writer, the reading
 * view, the search box — wants prose, not a file format. `source_type` and
 * `source_recipe_id` record where the prose came from so an SOP-backed course
 * can be re-synced when the recipe changes, and so nobody has to guess whether
 * editing the course text will be overwritten.
 *
 * WHY OUTLET ACCESS IS A PIVOT AND NOT A COLUMN. The training portal already
 * scopes SOPs per trainee through `lms_user_outlets`, and a course routinely
 * applies to several branches but not all of them ("Klang Valley barista
 * training"). A nullable outlet_id could express one branch or all branches and
 * nothing in between, which is the case that actually comes up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('category', 120)->nullable();

            // 'sop' — generated from a recipe SOP; 'upload' — extracted from a
            // document; 'text' — typed or pasted in. Kept as a string rather
            // than an enum so a fourth source does not need a table rebuild on
            // MySQL.
            $table->string('source_type', 20)->default('text');
            $table->foreignId('source_recipe_id')->nullable()->constrained('recipes')->nullOnDelete();
            $table->string('source_filename')->nullable();
            $table->string('source_path')->nullable();

            $table->longText('content')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('video_url')->nullable();

            // Bite-sized by design — the reading time drives the "5 minutes"
            // badge trainees decide by, and the roster-friendly claim only
            // holds if somebody has to put a number on it.
            $table->unsignedSmallInteger('estimated_minutes')->default(5);

            // 'draft' | 'published' | 'archived'
            $table->string('status', 20)->default('draft');

            // Compliance courses are the ones a certificate and a due date
            // actually mean something for, and they are reported separately.
            $table->boolean('is_compliance')->default(false);
            $table->unsignedSmallInteger('recertify_months')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'category']);
        });

        Schema::create('training_course_outlets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['training_course_id', 'outlet_id'], 'training_course_outlet_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_course_outlets');
        Schema::dropIfExists('training_courses');
    }
};
