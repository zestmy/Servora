<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What language each ANSWER was read in.
 *
 * The attempt already records a language, and that was enough while the choice
 * was made once at the start and frozen. It is not enough now: somebody who
 * begins in English and switches to Malay at question five has read one paper
 * in two languages, and a single column on the attempt can only lie about it.
 *
 * SWITCHING MID-QUIZ IS SAFE, which is why it is allowed at all. The questions,
 * the order and the answer key are the same in every language — only the words
 * differ — so nothing about scoring, resuming or reporting changes. What would
 * have been lost is the one thing the language was recorded FOR: "everybody who
 * read this in Malay got question seven wrong" is a fact about the
 * TRANSLATION, and it is only true if it is counted against the language the
 * question was actually read in.
 *
 * The attempt keeps its column and keeps its meaning — the language the run
 * STARTED in. Where the two disagree, the answer row is the truth.
 *
 * Null on every row that predates this, which is correct rather than a gap:
 * before the switcher existed the attempt's language described every answer
 * under it, and backfilling would invent a distinction nobody could have made.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_answers', function (Blueprint $table) {
            if (! Schema::hasColumn('training_answers', 'language')) {
                $table->string('language', 5)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('training_answers', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
};
