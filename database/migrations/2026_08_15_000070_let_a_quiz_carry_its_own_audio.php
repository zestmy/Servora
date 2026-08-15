<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An audio FILE for the backing track, beside the YouTube link.
 *
 * The YouTube embed cannot be made to autoplay on an iPhone, and that is not a
 * bug to keep chasing — it is how WebKit works. A user gesture belongs to the
 * frame it happened in, playVideo() reaches the player by postMessage into a
 * cross-origin iframe, and the activation does not cross that boundary. Three
 * rounds of fixes on our side of the wall were all correct and none of them
 * could have been sufficient.
 *
 * A NATIVE <audio> ELEMENT HAS NO SUCH PROBLEM. It lives in the same document
 * as the button, so the tap on Start authorises it directly — which is exactly
 * how every wedding-invitation site on the Malaysian internet plays a song on
 * an iPhone, including the one this was diagnosed against.
 *
 * So a quiz may carry either, and the player prefers the file:
 *   - `music_path`  an uploaded track. Works everywhere, iPhone included.
 *   - `music_url`   a YouTube link. Works on Android and desktop; on iOS it
 *                   asks for one tap on the player itself.
 *
 * Stored on the PUBLIC disk. It is background music chosen by the merchant to
 * be played out loud on a shop floor — there is nothing to protect, and putting
 * it behind a streaming controller would mean an authenticated request per
 * range header on a file the browser wants to seek through.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_quizzes', function (Blueprint $table) {
            if (! Schema::hasColumn('training_quizzes', 'music_path')) {
                $table->string('music_path', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('training_quizzes', function (Blueprint $table) {
            $table->dropColumn('music_path');
        });
    }
};
