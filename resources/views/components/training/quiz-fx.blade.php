{{--
    The noise and the colour a quiz makes.

    EVERYTHING THIS RENDERS IS FIXED OR TELEPORTED, and that is structural
    rather than stylistic. The component is mounted as the first child of the
    screen it belongs to, in the same position on the start screen and on the
    question screen, so Livewire's morph carries it from one to the other
    instead of tearing it down — and a torn-down player is a track that stops
    the moment the quiz begins. Nothing here occupies space in the layout, so
    "first child" costs the page nothing.

    SOUND IS SYNTHESISED, NOT A FILE. Three reasons, and the first is enough:
    the staff app is a PWA used on a kitchen floor with patchy wifi, and an mp3
    that has not finished downloading is a correct answer with no reward — the
    one moment the feedback had to land. Web Audio has no such failure mode, it
    is instant, and there is nothing to cache. The other two: no licensing
    question over a sound effect shipped to merchants, and no 200 KB of audio in
    a bundle for a two-note chime.

    ON AN IPHONE, THE RINGER SWITCH SILENCES WEB AUDIO. Media elements ignore
    it; an AudioContext does not. So the chime honestly cannot be made to play
    on a handset in silent mode, and the flash, the shake and the vibration are
    not decoration around it — for a phone on silent they ARE the feedback.

    MUSIC GOES THROUGH THE YOUTUBE PLAYER API, and starts on the Start tap.
    Setting autoplay=1 on an iframe src is ignored outright by Safari on iOS —
    reported as "my music doesn't sound in my iphone", and it never could have
    — because playback must begin inside a user gesture. The start screen exists
    partly to provide one. The player itself is parked off-screen with a
    floating button to stop and restart it, which is the control somebody
    actually wants: not "which video is this" but "make it stop".

    Props:
      music  a YouTube embed URL, or null for a quiz with no backing track
--}}
@props(['music' => null])

@php
    // The API takes an id and a playlist, not an embed URL — so the pieces are
    // pulled back out of the URL the model built. The model keeps the parsing
    // that decides what is SAFE; this is only the shape the API wants.
    $musicId = null;
    $musicList = null;

    if ($music) {
        if (preg_match('~/embed/([A-Za-z0-9_-]{11})~', $music, $m)) {
            $musicId = $m[1];
        }

        if (preg_match('~[?&]list=([A-Za-z0-9_-]{12,})~', $music, $m)) {
            $musicList = $m[1];
        }
    }

    $hasMusic = $musicId || $musicList;
@endphp

<div x-data="quizFx(@js($musicId), @js($musicList))"
     @answer-scored.window="react($event.detail)"
     @start-music.window="autostart()"
     class="contents">

    {{-- ── The verdict ──

         A wash of colour across the whole screen and a ribbon sweeping through
         it carrying the word. This is the moment the format lives or dies on:
         a quiz that answers a tap with a small line of text at the bottom of a
         card feels like a form, and one that takes the whole screen for a
         second and a half feels like a game. That difference is the entire
         reason a floor plays it twice.

         Teleported, because layouts.clock-staff wraps its content in a
         transformed element and a transform makes its ancestor the containing
         block for position:fixed — the same trap the training modals hit.

         aria-hidden throughout: the verdict is already on the feedback card
         below in real text, and a screen reader announcing it twice is worse
         than not animating at all. Everything here collapses under
         prefers-reduced-motion, where the card is the whole story. --}}
    <template x-teleport="body">
        <div x-show="flash" style="display:none" aria-hidden="true"
             class="pointer-events-none fixed inset-0 z-[60] overflow-hidden">

            {{-- The wash. Kept low so the question stays legible underneath —
                 this is a tint over the room, not a curtain across it. --}}
            <div class="absolute inset-0 opacity-30 motion-safe:animate-flash-out"
                 :class="flash === 'right' ? 'bg-success-400' : 'bg-danger-400'"></div>

            {{-- The ribbon. Wider than the screen and skewed, so the ends never
                 appear and it reads as a band passing through rather than a box
                 that arrived. --}}
            <div class="absolute inset-x-[-10%] top-[38%] motion-safe:animate-ribbon-sweep"
                 :class="flash === 'right' ? 'bg-success-600' : 'bg-danger-600'">
                <p class="py-5 text-center text-3xl font-extrabold uppercase tracking-wide text-white
                          motion-safe:animate-verdict-pop">
                    <span x-text="label"></span>
                    <span x-show="points !== 0" class="block text-lg font-bold tabular-nums opacity-90"
                          x-text="(points > 0 ? '+' : '−') + Math.abs(points).toLocaleString()"></span>
                </p>
            </div>
        </div>
    </template>

    {{-- The controls, floating bottom-right. 44px each, because they are
         pressed with the same hands as everything else in this app, and clear
         of the full-width primary button that lives at the bottom of every
         quiz screen. --}}
    <template x-teleport="body">
        <div class="fixed bottom-4 right-4 z-50 flex flex-col gap-2">
            @if ($hasMusic)
                <button type="button" @click="toggleMusic()"
                        class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-900 text-white shadow-e3
                               active:scale-95 transition"
                        :aria-label="musicOn ? 'Stop the music' : 'Play music'"
                        :aria-pressed="musicOn.toString()">
                    <span x-show="musicOn" class="text-base leading-none" aria-hidden="true">&#9646;&#9646;</span>
                    <span x-show="! musicOn" class="text-base leading-none" aria-hidden="true">&#9654;</span>
                </button>
            @endif

            <button type="button" @click="toggleSound()"
                    class="flex h-11 w-11 items-center justify-center rounded-full bg-white text-gray-700 shadow-e2
                           border border-gray-200 active:scale-95 transition"
                    :aria-label="sound ? 'Turn sounds off' : 'Turn sounds on'"
                    :aria-pressed="sound.toString()">
                <span x-show="sound" class="text-base leading-none" aria-hidden="true">&#128266;</span>
                <span x-show="! sound" class="text-base leading-none opacity-50" aria-hidden="true">&#128263;</span>
            </button>
        </div>
    </template>

    @if ($hasMusic)
        {{-- Parked off-screen rather than display:none. A frame that has been
             hidden outright is exactly what iOS refuses to play media in; one
             that is merely somewhere else is not. It is never removed once
             created, so pausing and resuming keeps the track's position. --}}
        <template x-teleport="body">
            <div class="pointer-events-none fixed bottom-0 left-0 h-24 w-40 -translate-x-[200vw] overflow-hidden opacity-0"
                 aria-hidden="true">
                <div id="quiz-music-player" class="h-full w-full"></div>
            </div>
        </template>
    @endif
</div>

@once
    <script>
        /*
         * BOTH ENTRY POINTS, because this screen is reached two ways. On a cold
         * load Alpine has not booted yet and alpine:init is the hook; arriving
         * by wire:navigate, Alpine booted on the previous page and that event
         * has already fired — this script tag runs, and without the direct call
         * `quizFx` would be undefined for the one visit that matters. The flag
         * keeps a third visit from redefining it.
         */
        (() => {
            const register = () => {
                if (window.Alpine.__quizFxRegistered) {
                    return;
                }

                window.Alpine.__quizFxRegistered = true;

                window.Alpine.data('quizFx', (musicId, musicList) => ({
                    sound: localStorage.getItem('quizSound') !== 'off',
                    // Never restored from storage. Music that starts itself
                    // because of a choice made on a different shift is the
                    // thing a phone gets put face down for.
                    musicOn: false,
                    musicId: musicId,
                    musicList: musicList,
                    flash: null,
                    // What the ribbon says, and what it is worth. Both come
                    // from the SERVER's answer row — the word follows the
                    // language the paper is being read in.
                    label: '',
                    points: 0,
                    ctx: null,

                    /*
                     * One AudioContext for the page, built lazily on a gesture.
                     * Building it up front gets it created 'suspended' by every
                     * browser's autoplay policy, and a suspended context that
                     * nobody resumes is silence that looks like working code.
                     */
                    audio() {
                        if (! this.ctx) {
                            const Ctx = window.AudioContext || window.webkitAudioContext;

                            if (! Ctx) {
                                return null;
                            }

                            this.ctx = new Ctx();
                        }

                        if (this.ctx.state === 'suspended') {
                            this.ctx.resume();
                        }

                        return this.ctx;
                    },

                    /** One note. Gain ramps to zero so it stops without a click. */
                    note(freq, startAt, length, type = 'sine', volume = 0.16) {
                        const ctx = this.audio();

                        if (! ctx) {
                            return;
                        }

                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        const at = ctx.currentTime + startAt;

                        osc.type = type;
                        osc.frequency.setValueAtTime(freq, at);

                        gain.gain.setValueAtTime(0.0001, at);
                        gain.gain.exponentialRampToValueAtTime(volume, at + 0.012);
                        gain.gain.exponentialRampToValueAtTime(0.0001, at + length);

                        osc.connect(gain).connect(ctx.destination);
                        osc.start(at);
                        osc.stop(at + length + 0.02);
                    },

                    // C6 then E6 — up, and over in a third of a second.
                    ding() {
                        this.note(1046.5, 0, 0.14, 'sine', 0.18);
                        this.note(1318.5, 0.09, 0.22, 'sine', 0.18);
                    },

                    // Down a step, and rough enough to be unmistakable without
                    // being loud — this plays in a room where other people can
                    // hear it, and humiliation is not the feedback we are after.
                    buzz() {
                        this.note(196, 0, 0.16, 'sawtooth', 0.10);
                        this.note(146.8, 0.1, 0.24, 'sawtooth', 0.10);
                    },

                    react(detail) {
                        const right = !! (detail && detail.correct);

                        this.label = (detail && detail.label) || (right ? 'Correct!' : 'Not quite');
                        this.points = (detail && detail.points) || 0;

                        this.flash = right ? 'right' : 'wrong';

                        // Matches the ribbon's own 1.5s sweep. Held in one place
                        // rather than two: a timeout shorter than the animation
                        // cuts the band off mid-screen.
                        clearTimeout(this._clear);
                        this._clear = setTimeout(() => { this.flash = null; }, 1500);

                        // Music ducks under the verdict rather than being
                        // talked over by it, then comes back up.
                        this.duck();

                        if (! this.sound) {
                            return;
                        }

                        right ? this.ding() : this.buzz();

                        /*
                         * The haptic is not a nicety. On iOS the ringer switch
                         * silences Web Audio while leaving media alone, so on a
                         * phone in silent mode this and the flash ARE the
                         * feedback — see the note at the top.
                         */
                        if (navigator.vibrate) {
                            navigator.vibrate(right ? 30 : [40, 60, 40]);
                        }
                    },

                    toggleSound() {
                        this.sound = ! this.sound;
                        localStorage.setItem('quizSound', this.sound ? 'on' : 'off');

                        // Play the confirmation THROUGH the gesture that turned
                        // it on, which is also what unlocks the AudioContext.
                        if (this.sound) {
                            this.note(880, 0, 0.12);
                        }
                    },

                    // ── Music ─────────────────────────────────────────────

                    /**
                     * Load YouTube's API once, and resolve when it is ready.
                     *
                     * onYouTubeIframeAPIReady is a single global YouTube calls
                     * exactly once, so it is wired to a promise rather than
                     * being overwritten by whichever screen asked last.
                     */
                    youtube() {
                        if (window.__ytReady) {
                            return window.__ytReady;
                        }

                        window.__ytReady = new Promise((resolve) => {
                            if (window.YT && window.YT.Player) {
                                resolve(window.YT);

                                return;
                            }

                            window.onYouTubeIframeAPIReady = () => resolve(window.YT);

                            const tag = document.createElement('script');
                            tag.src = 'https://www.youtube.com/iframe_api';
                            document.head.appendChild(tag);
                        });

                        return window.__ytReady;
                    },

                    /** The Start tap. Music begins if the quiz has any. */
                    autostart() {
                        if (! this.musicId && ! this.musicList) {
                            return;
                        }

                        if (! this.musicOn) {
                            this.toggleMusic();
                        }
                    },

                    async toggleMusic() {
                        this.musicOn = ! this.musicOn;

                        if (! this.musicOn) {
                            try { window.__quizPlayer?.pauseVideo?.(); } catch (e) {}

                            return;
                        }

                        /*
                         * playVideo() has to be reachable from THIS gesture on
                         * iOS. An existing player is therefore started before
                         * anything is awaited — an await ends the gesture and
                         * the play is refused in silence, which is the whole of
                         * the original iPhone bug.
                         *
                         * "Existing" means its iframe is still in the document.
                         * The staff app navigates with wire:navigate, so the
                         * global outlives the page that made it, and calling
                         * playVideo() on a player whose iframe was swapped out
                         * is a button that does nothing at all.
                         */
                        if (! window.__quizPlayer?.getIframe?.()?.isConnected) {
                            try { window.__quizPlayer?.destroy?.(); } catch (e) {}
                            window.__quizPlayer = null;
                        }

                        if (window.__quizPlayer?.playVideo) {
                            window.__quizPlayer.playVideo();

                            return;
                        }

                        const YT = await this.youtube();
                        const mount = document.getElementById('quiz-music-player');

                        if (! mount) {
                            return;
                        }

                        window.__quizPlayer = new YT.Player(mount, {
                            videoId: this.musicId || undefined,
                            playerVars: {
                                // playsinline keeps iOS from throwing the video
                                // fullscreen over the question being answered.
                                playsinline: 1,
                                controls: 0,
                                modestbranding: 1,
                                rel: 0,
                                loop: 1,
                                ...(this.musicList
                                    ? { list: this.musicList, listType: 'playlist' }
                                    : { playlist: this.musicId }),
                            },
                            events: {
                                onReady: (e) => {
                                    // Backing music, not the main event: loud
                                    // enough to hear under a chime, quiet
                                    // enough to talk over.
                                    e.target.setVolume(35);
                                    e.target.playVideo();
                                },
                            },
                        });
                    },

                    /** Drop the music while a verdict is being heard. */
                    duck() {
                        const player = window.__quizPlayer;

                        if (! this.musicOn || ! player?.setVolume) {
                            return;
                        }

                        // Swallowed on purpose: a player torn down by a
                        // navigation mid-question must never take the answer
                        // feedback down with it. Ducking is the least important
                        // thing happening at this moment.
                        try {
                            player.setVolume(10);
                            setTimeout(() => { try { player.setVolume(35); } catch (e) {} }, 900);
                        } catch (e) {}
                    },
                }));
            };

            window.Alpine
                ? register()
                : document.addEventListener('alpine:init', register);
        })();
    </script>
@endonce
