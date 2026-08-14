<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live sessions — the Kahoot-style part.
 *
 * A host opens a session, a PIN appears on the screen everyone is looking at,
 * staff join from their phones, and the host drives the room question by
 * question. The session row IS the shared clock: `current_index` and
 * `question_started_at` are what every player's screen reads to work out what
 * to show and how much time is left, so nothing is stored per-player that could
 * disagree about which question is live.
 *
 * WHY POLLING AND NOT WEBSOCKETS. There is no broadcast driver in this
 * application — no Reverb, no Pusher, and the production box runs five php-fpm
 * workers on 1.97 GB. A room of twenty phones polling once a second is twenty
 * requests a second against a two-column read, which this stack handles; adding
 * a websocket server to the box for one feature is a much larger operational
 * change than the feature. `question_started_at` being server-side is what
 * makes that honest: a slow poll costs a player nothing, because the elapsed
 * time is measured from the server's stamp, not from when their screen updated.
 *
 * PINs are unique among LIVE sessions only. They are a six-digit room number
 * shouted across a kitchen, not a credential — recycling them once a session
 * has ended is the whole point, and the partial index is what allows it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('host_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('pin', 8);
            $table->string('name')->nullable();

            // 'lobby' | 'question' | 'reveal' | 'ended'
            $table->string('status', 20)->default('lobby');

            $table->unsignedSmallInteger('current_index')->default(0);
            $table->timestamp('question_started_at')->nullable();

            // The question order this room is playing, resolved once at start
            // so a late joiner sees the same sequence as everybody else.
            $table->json('question_order')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['pin', 'status']);
        });

        Schema::create('training_session_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')->constrained()->cascadeOnDelete();

            /*
             * Nullable on purpose. Half the value of a live session is running
             * one at a shift briefing where the casuals have no portal account
             * yet — they type a nickname and play. A linked trainee gets their
             * score onto their report card; a nickname-only player still gets
             * the leaderboard, which is what they came for.
             */
            $table->foreignId('lms_user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nickname', 60);

            $table->unsignedInteger('score')->default(0);
            $table->unsignedSmallInteger('streak')->default(0);
            $table->unsignedSmallInteger('best_streak')->default(0);
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedSmallInteger('answered_count')->default(0);

            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['training_session_id', 'nickname'], 'training_session_nickname_unique');
            $table->index(['training_session_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_session_players');
        Schema::dropIfExists('training_sessions');
    }
};
