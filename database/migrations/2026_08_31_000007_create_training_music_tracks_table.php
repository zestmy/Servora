<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A company's backing tracks, uploaded once and chosen from.
 *
 * Today every quiz uploads its own. The file lands in training/music under a
 * hashed name, the quiz points at it, and the next quiz that wants the same
 * song uploads it again — so a company running eight papers carries eight
 * copies of one track, none of them named, and changing the music means
 * re-uploading it eight times. The commonest real case is one song across every
 * quiz, and that is the case the current shape serves worst.
 *
 * So the file gets a row and a NAME. `music_path` stays exactly where it is on
 * training_quizzes and the player is untouched — this table is the chooser, not
 * a new source of truth. A quiz still points at a path; it just no longer has
 * to be the one that put it there.
 *
 * EXISTING TRACKS ARE ADOPTED, not orphaned. Every distinct music_path already
 * in use becomes a row, so a company opening this screen after the release
 * finds the music it already had rather than an empty list next to a quiz that
 * is audibly still playing something. They are named from the path because that
 * is all there is to name them from — the original filename was never kept —
 * and an author can rename them.
 *
 * PATH IS UNIQUE PER COMPANY. Two rows pointing at one file would let a rename
 * disagree with itself, and deleting one would take the other's audio with it.
 *
 * NO CASCADE FROM THE QUIZ. Deleting a quiz must not delete a track that three
 * other quizzes are playing, which is exactly what a naive foreign key from the
 * other direction would have done.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_music_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // What an author calls it. The only reason this table exists —
            // a hashed filename is not something anybody can choose between.
            $table->string('title', 120);

            // Relative to the public disk, the same value music_path holds.
            $table->string('path', 255);

            // What it was called when it arrived, kept for the rename default
            // and for somebody wondering which file on disk this is.
            $table->string('original_name', 255)->nullable();
            $table->unsignedInteger('size_bytes')->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'path']);
            // The picker: this company's tracks, by name.
            $table->index(['company_id', 'title']);
        });

        // Adopt what is already playing.
        if (! Schema::hasColumn('training_quizzes', 'music_path')) {
            return;
        }

        $existing = DB::table('training_quizzes')
            ->whereNotNull('music_path')
            ->where('music_path', '!=', '')
            ->get(['company_id', 'music_path', 'title']);

        $seen = [];
        $now  = now();

        foreach ($existing as $quiz) {
            $key = $quiz->company_id . '|' . $quiz->music_path;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            DB::table('training_music_tracks')->insertOrIgnore([
                'company_id' => $quiz->company_id,
                /*
                 * Named after the quiz it was uploaded for, trimmed to fit.
                 * Not the hashed filename, which tells nobody anything, and not
                 * "Track 1", which tells them less than the paper it came from.
                 * It is a starting point somebody can rename.
                 */
                'title'      => mb_substr('Music from ' . $quiz->title, 0, 120),
                'path'       => $quiz->music_path,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('training_music_tracks');
    }
};
