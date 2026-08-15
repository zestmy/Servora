<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-advance: the answer screen can turn its own page.
 *
 * Seconds the feedback stays up before the next question arrives by itself.
 * ZERO — the default, and what every existing quiz keeps — means what it has
 * always meant: the trainee taps Next when they have read the explanation.
 *
 * A setting rather than a behaviour, because the two uses of this module pull
 * in opposite directions. A quick menu-knowledge round plays best with the
 * Kahoot rhythm — verdict, beat, next — and a tap between every question is
 * drag. A compliance paper is the opposite: the explanation under a wrong
 * answer IS the training, and a page that turns itself is a page half-read.
 * The author knows which one they are writing; the product does not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_quizzes', function (Blueprint $table) {
            if (! Schema::hasColumn('training_quizzes', 'auto_advance_seconds')) {
                $table->unsignedTinyInteger('auto_advance_seconds')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('training_quizzes', function (Blueprint $table) {
            $table->dropColumn('auto_advance_seconds');
        });
    }
};
