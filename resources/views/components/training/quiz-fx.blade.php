{{--
    The noise and the colour a quiz makes.

    SOUND IS SYNTHESISED, NOT A FILE. Three reasons, and the first is enough:
    the staff app is a PWA used on a kitchen floor with patchy wifi, and an mp3
    that has not finished downloading is a correct answer with no reward — the
    one moment the feedback had to land. Web Audio has no such failure mode, it
    is instant, and it costs nothing to cache because there is nothing to cache.
    The other two: no licensing question over a sound effect shipped to
    merchants, and no 200 KB of audio in a bundle for a two-note chime.

    A ding is a rising major third; a wrong answer is a short low sawtooth, one
    step down. Both are under 400ms, because this plays between somebody's tap
    and their reading the explanation.

    ON AN IPHONE, THE RINGER SWITCH SILENCES WEB AUDIO. Media elements ignore
    it; an AudioContext does not. So the chime honestly cannot be made to play
    on a handset in silent mode, and the flash, the shake and the vibration are
    not decoration around it — they are the same feedback for the third of the
    floor whose phone is on silent all shift. That is why every reaction here
    has a visible half and a haptic half as well as a sound.

    SOUND IS ON BY DEFAULT AND MUSIC IS NOT. A phone that suddenly plays music
    on a service pass is a phone that gets put face down for the rest of the
    shift; a short chime is not. Both choices are remembered in localStorage per
    device, which is the right scope — it is a property of where the phone is,
    not of who is holding it.

    MUSIC GOES THROUGH THE YOUTUBE PLAYER API AND SHOWS ITS PLAYER, and both
    halves of that are iOS. Setting `autoplay=1` on an iframe src is ignored by
    Safari on iOS outright — reported as "my music doesn't sound in my iphone",
    and it never could have — because playback has to begin inside a user
    gesture. The API lets playVideo() be called from the tap itself. And the
    player is VISIBLE rather than a 1x1 pixel, because iOS declines to play
    media in a frame that has been hidden, which is precisely the trick this
    check exists to stop. A small player somebody can see, pause and skip is
    also the more honest thing to put on a screen that is making noise.

    Props:
      music  a YouTube embed URL, or null for a quiz with no backing track
--}}
@props(['music' => null])

@php
    // The API takes an id and a playlist, not an embed URL — so the pieces are
    // pulled back out of the URL the model built, which is still the right
    // place for the parsing: it is the half that decides what is safe.
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
@endphp

<div x-data="quizFx(@js($musicId), @js($musicList))"
     @answer-scored.window="react($event.detail)"
     class="contents">

    {{-- The wash of colour. Fixed and teleported out of the page, because
         layouts.clock-staff wraps its content in a transformed element and a
         transform makes its ancestor the containing block for position:fixed —
         the same trap the training modals hit. --}}
    <template x-teleport="body">
        <div x-show="flash" style="display:none" aria-hidden="true"
             class="pointer-events-none fixed inset-0 z-[60] motion-safe:animate-flash-out"
             :class="flash === 'right' ? 'bg-success-400' : 'bg-danger-400'"></div>
    </template>

    {{-- The controls. Both 44px, because they are pressed with the same hands
         as everything else in this app. --}}
    <div class="flex items-center justify-end gap-1">
        <button type="button" @click="toggleSound()"
                class="icon-btn" :aria-pressed="sound.toString()"
                :aria-label="sound ? 'Turn sounds off' : 'Turn sounds on'">
            <span x-show="sound" class="text-base leading-none" aria-hidden="true">&#128266;</span>
            <span x-show="! sound" class="text-base leading-none opacity-50" aria-hidden="true">&#128263;</span>
        </button>

        @if ($musicId || $musicList)
            <button type="button" @click="toggleMusic()"
                    class="icon-btn" :aria-pressed="musicOn.toString()"
                    :aria-label="musicOn ? 'Stop the music' : 'Play music'">
                <span class="text-base leading-none" :class="musicOn ? '' : 'opacity-50'"
                      aria-hidden="true">&#127925;</span>
            </button>
        @endif
    </div>

    @if ($musicId || $musicList)
        {{-- The player, teleported to the body and docked bottom-left so it
             survives this component being re-rendered between questions — a
             player torn down and rebuilt on every question is a track that
             restarts every twenty seconds.

             Bottom LEFT because the primary button on every quiz screen is
             full-width at the bottom, and the right-hand corner is where a
             thumb rests. --}}
        <template x-teleport="body">
            <div x-show="musicOn" style="display:none"
                 class="fixed bottom-3 left-3 z-40 w-40 overflow-hidden rounded-surface bg-gray-900 shadow-e3">
                <div id="quiz-music-player" class="aspect-video w-full"></div>
                <button type="button" @click="toggleMusic()"
                        class="flex w-full items-center justify-center gap-1 px-2 py-1.5 text-[11px] font-medium text-gray-200 hover:text-white">
                    <span aria-hidden="true">&#9632;</span> Stop the music
                </button>
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

                        this.flash = right ? 'right' : 'wrong';
                        setTimeout(() => { this.flash = null; }, 460);

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

                    async toggleMusic() {
                        this.musicOn = ! this.musicOn;

                        if (! this.musicOn) {
                            window.__quizPlayer?.pauseVideo?.();

                            return;
                        }

                        /*
                         * playVideo() has to be reachable from THIS gesture on
                         * iOS. An existing player is therefore started before
                         * anything is awaited — an await here would end the
                         * gesture and the play would be refused silently, which
                         * is the whole of the original iPhone bug.
                         *
                         * "Existing" means its iframe is still in the document.
                         * The staff app navigates with wire:navigate, so the
                         * global outlives the page that made it, and calling
                         * playVideo() on a player whose iframe was swapped out
                         * from under it is a button that does nothing at all.
                         */
                        if (! window.__quizPlayer?.getIframe?.()?.isConnected) {
                            window.__quizPlayer?.destroy?.();
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
                        // feedback down with it. Ducking is the least
                        // important thing happening at this moment.
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
