<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assign a QUIZ, and narrow an outlet down to a SECTION.
 *
 * WHY A QUIZ IS NOW A TARGET. Assignments could name a course or a path, on the
 * reasoning that people are given material to learn rather than a test to sit.
 * That is true right up against the case this product is actually for: a course
 * now carries several questionnaires — a kitchen one, a floor one, an English
 * set and a Malay set — and "do the lamb rack course" no longer says which of
 * them. Naming the quiz is the only way to say "sit THIS paper", which is what
 * a manager chasing a compliance deadline means.
 *
 * WHY SECTION IS A SECOND COLUMN AND NOT A THIRD AUDIENCE. Outlet and section
 * are not alternatives, they COMBINE: the real request is "the kitchen at
 * Bangsar", which needs both. So outlet_id and section_id narrow each other
 * rather than competing —
 *
 *   outlet, no section  → everybody at that branch
 *   outlet + section    → that section at that branch
 *   section, no outlet  → that section everywhere
 *   employee            → one named person
 *
 * Modelling section as a third mutually-exclusive audience would have made the
 * commonest request the one thing the screen could not express.
 *
 * nullOnDelete on both: retiring a section or closing an outlet must not delete
 * the training requirement. It widens — "the kitchen at Bangsar" becomes
 * "everybody at Bangsar" — which is the safe direction for something somebody
 * is being held to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_assignments', function (Blueprint $table) {
            $table->foreignId('training_quiz_id')->nullable()->after('training_path_id')
                ->constrained()->cascadeOnDelete();

            $table->foreignId('section_id')->nullable()->after('outlet_id')
                ->constrained()->nullOnDelete();
        });

        Schema::table('training_assignments', function (Blueprint $table) {
            // Every "what is outstanding for this person" query filters on the
            // pair, so the pair is what gets indexed.
            $table->index(['outlet_id', 'section_id'], 'training_assignment_audience_idx');
        });
    }

    public function down(): void
    {
        Schema::table('training_assignments', function (Blueprint $table) {
            $table->dropIndex('training_assignment_audience_idx');
        });

        Schema::table('training_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('training_quiz_id');
            $table->dropConstrainedForeignId('section_id');
        });
    }
};
