<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A link you can put on a noticeboard.
 *
 * The staff app already lets somebody find a quiz in three taps once they are
 * signed in. What it could not do is be POINTED AT — a QR beside the pass, a
 * link in a WhatsApp group, a line at the bottom of a shift roster. That is how
 * training actually reaches a floor: somebody is already holding their phone
 * and the quiz is one scan away, rather than being a thing to remember to go
 * and do later.
 *
 * A TOKEN RATHER THAN THE ID, for two reasons that are both small and both
 * real. `/q/47` invites `/q/48`, which is a list of every quiz a company has
 * ever written, titles included, to anyone who can count. And a token can be
 * rotated when a link ends up somewhere it should not, which an id can never be.
 *
 * IT IS NOT A CREDENTIAL. The link opens a page that says what the quiz is and
 * asks for a PIN; the quiz itself stays behind the same staff session as the
 * rest of the portal. Anyone finding the URL learns a title, which is exactly
 * what the poster it was printed on already said.
 *
 * 16 characters of Str::random — about 95 bits, which is more than a
 * noticeboard needs and cheap enough not to think about again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('training_quizzes', 'share_token')) {
            Schema::table('training_quizzes', function (Blueprint $table) {
                $table->string('share_token', 32)->nullable()->unique();
            });
        }

        // Backfilled rather than generated on demand, so the share panel has
        // something to show the first time it is opened rather than needing a
        // write on a GET.
        DB::table('training_quizzes')->whereNull('share_token')->orderBy('id')
            ->select('id')->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('training_quizzes')
                        ->where('id', $row->id)
                        ->update(['share_token' => Str::random(16)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('training_quizzes', function (Blueprint $table) {
            $table->dropColumn('share_token');
        });
    }
};
