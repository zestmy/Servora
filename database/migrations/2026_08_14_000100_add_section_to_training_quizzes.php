<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who a questionnaire is for — front of house, back of house, or everybody.
 *
 * ON THE QUIZ, NOT THE COURSE, and that is the whole point of putting it here.
 * One body of material is routinely worth asking two different sets of
 * questions about: the lamb rack SOP should ask the kitchen about oven
 * temperatures and probe thermometers, and ask the floor how to describe the
 * dish and how long to tell the table it will take. Tagging the COURSE would
 * force two copies of the same method to exist so each section could be
 * examined on it.
 *
 * It reuses `sections`, which already means exactly this in the product —
 * FOH/BOH groupings that the duty roster and overtime claims are organised by,
 * and that every employee already carries. Inventing a second, parallel notion
 * of "who this is for" would be a list somebody has to keep in step with the
 * first one.
 *
 * NULL MEANS EVERYBODY, and that is the default on purpose. A compliance quiz
 * about allergens is not a front-of-house quiz; making authors pick a section
 * every time would push them into tagging things narrowly by habit, and the
 * failure mode of that is a food-safety quiz half the staff never see.
 *
 * nullOnDelete: retiring a section must not take the questionnaires with it. A
 * quiz that loses its section becomes one everybody answers, which is the safe
 * direction to fail in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_quizzes', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable()->after('training_course_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('training_quizzes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('section_id');
        });
    }
};
