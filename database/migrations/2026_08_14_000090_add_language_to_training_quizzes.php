<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What language the questions are asked in.
 *
 * ON THE QUIZ, NOT THE COURSE, and the two are deliberately allowed to differ.
 * The common case in a Malaysian kitchen is an SOP written in English and a
 * floor that would rather be asked in Malay — so the generator reads whatever
 * the material is in and writes the questions in whatever this says. Putting
 * the language on the course instead would force somebody to keep two copies of
 * the same method to get a second set of questions.
 *
 * `ms` AND `id` ARE SEPARATE ENTRIES, not one "Malay-ish" option. They are close
 * enough that one prompt would produce something that reads as neither: the
 * everyday vocabulary diverges (`kereta`/`mobil`, `wang`/`uang`), and the
 * loanwords in a kitchen diverge harder still — Malaysian kitchen Malay carries
 * English terms that Indonesian has its own words for. Staff notice immediately
 * when a quiz is written in the wrong one of the two.
 *
 * Two-letter ISO 639-1 in a fixed-width column: these are language tags, not
 * locales, and nothing here varies by country beyond the two already named.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_quizzes', function (Blueprint $table) {
            $table->string('language', 5)->default('en')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('training_quizzes', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
};
