<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A quiz can be for more than one section, and for named outlets.
 *
 * `section_id` was a single nullable column, which forces every real audience
 * into one of two shapes: exactly one section, or everybody. A menu knowledge
 * paper is for FOH and Bar but not the kitchen; an allergen paper is for the
 * kitchen and FOH but not the office. Neither is expressible, so authors write
 * the quiz twice — which is the same trap the second-language quiz fell into,
 * and it ends the same way: two papers that drift, two sets of scores, and a
 * leaderboard that has to be added up by hand.
 *
 * OUTLETS ARRIVE AT THE SAME TIME because they are the same question asked
 * about a different column. A course already carries a pivot of outlets; a quiz
 * carried none at all, so a branch-specific paper had to hide inside a
 * branch-specific course. The two pivots narrow each OTHER — "the kitchen at
 * Bangsar" is the commonest real audience and neither expresses it alone —
 * which is the rule TrainingAssignment already uses for the same pair.
 *
 * EMPTY MEANS EVERYBODY, on both sides. It is the forgiving reading, it is what
 * every existing quiz means today, and the alternative — an empty pivot meaning
 * nobody — would silently retire every quiz in the product on deploy.
 *
 * The old column goes rather than staying as a shadow. Two places that answer
 * "which section is this for" will disagree the first time somebody edits one
 * of them, and the loser is a trainee who cannot see their own paper.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_quiz_sections')) {
            Schema::create('training_quiz_sections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('training_quiz_id')->constrained()->cascadeOnDelete();
                $table->foreignId('section_id')->constrained()->cascadeOnDelete();

                $table->unique(['training_quiz_id', 'section_id'], 'quiz_section_unique');
            });
        }

        if (! Schema::hasTable('training_quiz_outlets')) {
            Schema::create('training_quiz_outlets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('training_quiz_id')->constrained()->cascadeOnDelete();
                $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();

                $table->unique(['training_quiz_id', 'outlet_id'], 'quiz_outlet_unique');
            });
        }

        // Carry the single section across before the column goes.
        if (Schema::hasColumn('training_quizzes', 'section_id')) {
            $rows = DB::table('training_quizzes')
                ->whereNotNull('section_id')
                ->get(['id', 'section_id']);

            foreach ($rows as $row) {
                DB::table('training_quiz_sections')->insertOrIgnore([
                    'training_quiz_id' => $row->id,
                    'section_id'       => $row->section_id,
                ]);
            }

            /*
             * The FK-then-index-then-column dance, driver-branched.
             *
             * MySQL refuses to drop a column while a foreign key names it, and
             * refuses to drop the key's index separately; SQLite has neither
             * object to drop and rebuilds the table wholesale from
             * dropConstrainedForeignId. Doing it one way breaks the other, and
             * the failure is asymmetric — SQLite fails the test suite, MySQL
             * fails the deploy. See the learner migration for the first time
             * this cost an afternoon.
             */
            if (DB::connection()->getDriverName() === 'sqlite') {
                Schema::table('training_quizzes', function (Blueprint $table) {
                    $table->dropConstrainedForeignId('section_id');
                });
            } else {
                foreach (Schema::getForeignKeys('training_quizzes') as $key) {
                    if (in_array('section_id', $key['columns'] ?? [], true)) {
                        Schema::table('training_quizzes', function (Blueprint $table) use ($key) {
                            $table->dropForeign($key['name']);
                        });
                    }
                }

                foreach (Schema::getIndexes('training_quizzes') as $index) {
                    if (($index['columns'] ?? []) === ['section_id']) {
                        Schema::table('training_quizzes', function (Blueprint $table) use ($index) {
                            $table->dropIndex($index['name']);
                        });
                    }
                }

                Schema::table('training_quizzes', function (Blueprint $table) {
                    $table->dropColumn('section_id');
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('training_quizzes', 'section_id')) {
            Schema::table('training_quizzes', function (Blueprint $table) {
                $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            });

            // Only the first section survives going back, which is the honest
            // shape of a column that can hold one.
            foreach (DB::table('training_quiz_sections')->orderBy('id')->get() as $row) {
                DB::table('training_quizzes')
                    ->where('id', $row->training_quiz_id)
                    ->whereNull('section_id')
                    ->update(['section_id' => $row->section_id]);
            }
        }

        Schema::dropIfExists('training_quiz_sections');
        Schema::dropIfExists('training_quiz_outlets');
    }
};
