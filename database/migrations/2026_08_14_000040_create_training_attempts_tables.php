<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attempts and answers — the record every score, leaderboard and report card
 * is computed from.
 *
 * One shape for both modes. A live session and a self-paced quiz differ in who
 * controls the clock, not in what happened: somebody was asked a question, took
 * some seconds, chose something, and it was right or it wasn't. Storing them
 * separately would mean two leaderboard queries, two report cards, and the
 * first bug where a staff member's live win never reached their record.
 *
 * WHY THE ANSWER STORES SECONDS AND POINTS RATHER THAN RECOMPUTING THEM. The
 * speed bonus is a function of the time limit that applied AT THE TIME. Editing
 * a question later — raising it from 20 seconds to 30 — must not retroactively
 * change what anybody scored, and re-deriving would do exactly that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lms_user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();

            // Set for live attempts; null for self-paced ones.
            $table->foreignId('training_session_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('training_session_player_id')->nullable()->constrained()->nullOnDelete();

            // 'self' | 'live'
            $table->string('mode', 10)->default('self');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('max_score')->default(0);
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedSmallInteger('question_count')->default(0);
            // Percent CORRECT, not percent of points: the pass mark is about
            // knowing the material, and a slow but perfect run must pass.
            $table->decimal('percent', 5, 2)->default(0);
            $table->boolean('passed')->default(false);

            // The order this attempt saw, when the quiz shuffles.
            $table->json('question_order')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'lms_user_id', 'completed_at'], 'training_attempt_user_idx');
            $table->index(['training_quiz_id', 'completed_at']);
        });

        Schema::create('training_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_question_id')->constrained()->cascadeOnDelete();

            // Chosen option indexes. Empty array = ran out of time.
            $table->json('chosen');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('points_awarded')->default(0);
            $table->decimal('seconds_taken', 6, 2)->default(0);
            // Frozen so a later edit to the question cannot rewrite history.
            $table->unsignedSmallInteger('seconds_allowed')->default(0);

            $table->timestamps();

            $table->unique(['training_attempt_id', 'training_question_id'], 'training_answer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_answers');
        Schema::dropIfExists('training_attempts');
    }
};
