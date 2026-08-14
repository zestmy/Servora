<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quizzes and their questions.
 *
 * A quiz belongs to a course but is not the course: the same material is worth
 * asking about more than once — a quick five-question check at induction and a
 * longer recertification set — and a live session hosts ONE quiz, so the quiz
 * has to be the addressable thing.
 *
 * WHY OPTIONS AND ANSWERS ARE JSON. A question has two to six options depending
 * on its type, and a multi-select question has more than one correct index. The
 * alternative is a training_question_options table, which buys nothing here:
 * options are never queried across questions, never reordered independently,
 * and never referenced by anything except the answer that picked one. The
 * answer stores the INDEX, not the text, so fixing a typo in an option does not
 * silently invalidate every attempt that chose it.
 *
 * Points and per-question seconds live on the question with a quiz-level
 * default, because the difficult question at the end of a food-safety quiz
 * deserves more time and more weight than the warm-up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_course_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // 'draft' | 'published' | 'archived'
            $table->string('status', 20)->default('draft');

            $table->unsignedSmallInteger('pass_mark')->default(70);          // percent
            $table->unsignedSmallInteger('default_seconds')->default(20);     // per question
            $table->unsignedSmallInteger('default_points')->default(1000);

            /*
             * Kahoot's two mechanics, both optional because they are wrong for
             * some uses. Speed bonus turns a compliance quiz into a race, which
             * is the opposite of what you want when somebody is being asked
             * whether they understood the allergen policy.
             */
            $table->boolean('speed_bonus')->default(true);
            $table->boolean('streak_bonus')->default(true);
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('shuffle_options')->default(true);

            // Self-paced attempts only; a live session is always one shot.
            $table->unsignedTinyInteger('max_attempts')->default(0); // 0 = unlimited
            $table->boolean('issues_certificate')->default(false);

            $table->boolean('generated_by_ai')->default(false);
            $table->string('ai_model')->nullable();
            $table->timestamp('generated_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
        });

        Schema::create('training_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_quiz_id')->constrained()->cascadeOnDelete();

            // 'mcq' | 'true_false' | 'multi'
            $table->string('type', 20)->default('mcq');
            $table->text('prompt');

            // ["Option A", "Option B", …] — index-addressed, see the class note.
            $table->json('options');
            // [0] for single-answer types, [0,2] for 'multi'.
            $table->json('correct');

            $table->text('explanation')->nullable();
            $table->string('difficulty', 20)->default('medium'); // easy | medium | hard
            // Free-text tag the report card groups weak areas by ("Allergens").
            $table->string('topic', 120)->nullable();

            $table->unsignedSmallInteger('points')->nullable();   // falls back to the quiz default
            $table->unsignedSmallInteger('seconds')->nullable();  // falls back to the quiz default
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['training_quiz_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_questions');
        Schema::dropIfExists('training_quizzes');
    }
};
