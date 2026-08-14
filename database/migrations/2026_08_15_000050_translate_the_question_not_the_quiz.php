<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One quiz, several languages.
 *
 * THE PREVIOUS ANSWER WAS A SECOND QUIZ, and it was wrong in a way that only
 * shows up downstream. A Malay paper written as its own quiz is its own row
 * everywhere it counts: its own attempts, its own leaderboard line, its own
 * assignment to clear, its own certificate. A floor where half the staff read
 * Malay would produce two of every number, and "who has done the allergen
 * quiz" would have to be asked twice and added up by hand. It also asks the
 * merchant to keep two papers honest with each other forever — the second
 * anybody edits a question in one language, the two are different quizzes.
 *
 * The thing that actually differs between languages is the WORDS. The
 * questions are the same questions, in the same order, with the same correct
 * options — which is why the translation hangs off the question and addresses
 * options by the same index the answer key uses. Nothing about scoring,
 * shuffling or reporting has to know a translation exists.
 *
 * A LANGUAGE IS ONLY OFFERED WHEN EVERY QUESTION HAS IT. Half a paper in Malay
 * is worse than none: somebody who chose Malay because they read Malay is left
 * reading English from question five, at speed, for points. See
 * TrainingQuiz::availableLanguages().
 *
 * The attempt records what was read. Not for scoring — the answer key is the
 * same either way — but because "everybody who took this in English passed and
 * everybody who took it in Malay failed" is a fact about the TRANSLATION, and
 * it is invisible unless it was written down at the time.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_question_translations')) {
            Schema::create('training_question_translations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('training_question_id')->constrained()->cascadeOnDelete();

                // 'ms', 'id' — the same keys as TrainingQuiz::LANGUAGES.
                $table->string('language', 5);

                $table->text('prompt');

                /*
                 * The options IN ORDER, and the order is load-bearing: the
                 * answer key on the question is a list of INDEXES, so a
                 * translation that reorders or drops one silently marks the
                 * wrong option correct for everybody reading that language.
                 * The writer enforces an identical count for the same reason.
                 */
                $table->json('options');

                $table->text('explanation')->nullable();

                $table->boolean('machine_translated')->default(true);
                $table->timestamps();

                $table->unique(['training_question_id', 'language'], 'question_language_unique');
            });
        }

        Schema::table('training_attempts', function (Blueprint $table) {
            if (! Schema::hasColumn('training_attempts', 'language')) {
                // Null means the quiz's own language, which is what every
                // attempt taken before this existed was read in.
                $table->string('language', 5)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_question_translations');

        Schema::table('training_attempts', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
};
