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

    MUSIC GOES THROUGH THE YOUTUBE PLAYER API, and two things about it are
    load-bearing rather than incidental.

    THE PLAYER IS BUILT AT PAGE LOAD; ONLY PLAYBACK WAITS FOR THE TAP.
    Constructing a player needs no user gesture — playing does. The first
    version did both on the Start tap with an `await` on the API script in
    between, and an await ends the gesture, so playVideo() arrived unauthorised
    and was refused in silence. That is "the music does not autoplay". Every
    path from the tap to playVideo() is now synchronous.

    THE PLAYER LIVES ON <body>, BUILT IN JAVASCRIPT, not in this template. A
    teleported node's lifetime is tied to the <template> that declared it, and
    this component re-renders on every question — so the iframe was being
    destroyed mid-quiz. That is "the music stops when I move to the next
    question". Nothing Livewire morphs can reach it now.

    THE PLAYER IS VISIBLE WHILE IT PLAYS, and that is not decoration — it is
    the difference between sound and silence on an iPhone. This worked once and
    was broken by hiding it: the player was moved off-screen behind
    `-translate-x-[200vw]` and `opacity: 0` on the reasoning that a small
    YouTube card was clutter. WebKit does not play media in a frame it does not
    consider visible, so from that commit there was no sound on iOS at all.

    Two properties, then, and both are load-bearing:

      1. The player is CREATED INSIDE the tap, not at page load. WebKit is far
         more willing to start media in a frame that was built during a gesture.
      2. The player is ON SCREEN whenever it is playing. Small, bottom-left,
         clear of the primary button and the tab bar — but rendered.

    Its own controls are on, so if a platform still refuses there is something
    to press. That is a fallback rather than the design.

    AN UPLOADED TRACK SIDESTEPS ALL OF IT. A native <audio> element lives in
    the same document as the Start button, so the tap authorises it directly —
    which is how every wedding-invitation site on the Malaysian internet plays a
    song on an iPhone. When a quiz has a file it is used and YouTube is not
    touched at all; the iframe path remains for quizzes that only have a link.

    Props:
      music       a YouTube embed URL, or null
      musicFile   an uploaded audio URL, or null. Preferred over the link.
--}}
@props(['music' => null, 'musicFile' => null])

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

    // The file wins. A quiz carrying both plays the one that works everywhere.
    $hasMusic = $musicFile || $musicId || $musicList;
@endphp

<div x-data="quizFx(@js($musicId), @js($musicList), @js($musicFile))"
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
        {{-- Above the tab bar, not on top of it. The staff layout ends in a
             fixed row of tabs about 4rem tall, and these were landing on
             "Board" — a floating control that covers navigation is a control
             that gets pressed by accident. --}}
        <div class="fixed bottom-[5.5rem] right-4 z-50 flex flex-col gap-2">
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

    {{-- NO "TAP PLAY" NOTICE. There was one, and it was right about the
         mechanism and wrong about the screen: a black label floating over the
         options of a timed question, on every question, is a thing to get past
         rather than a thing to read. The player itself appearing is the whole
         message — it is a play button, and people know what those are.

         The honest fix for a merchant is upstream anyway: upload a track and
         no iPhone ever has to be asked for anything. --}}

    {{-- THE PLAYER IS NOT IN THIS TEMPLATE, and that is the fix for "the music
         stops when I move to the next question". It was a teleported node, and
         a teleport's lifetime is tied to the <template> that declared it: every
         Livewire re-render — a question, a feedback panel, the next question —
         risked that template being replaced, which destroys the teleported
         iframe and the track with it.

         So the container is built in JavaScript and appended to <body> once,
         outside anything Blade owns. Nothing Livewire morphs can reach it. See
         mountPlayer() below. --}}
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

                window.Alpine.data('quizFx', (musicId, musicList, musicFile) => ({
                    sound: localStorage.getItem('quizSound') !== 'off',
                    // Never restored from storage. Music that starts itself
                    // because of a choice made on a different shift is the
                    // thing a phone gets put face down for.
                    musicOn: false,
                    musicId: musicId,
                    musicList: musicList,
                    musicFile: musicFile,
                    flash: null,
                    // What the ribbon says, and what it is worth. Both come
                    // from the SERVER's answer row — the word follows the
                    // language the paper is being read in.
                    label: '',
                    points: 0,
                    ctx: null,

                    /*
                     * THE PLAYER IS BUILT AT PAGE LOAD, NOT ON THE TAP.
                     *
                     * Reported as "music is not auto-play". Constructing a
                     * YouTube player needs no user gesture; only PLAYBACK does
                     * — and the old code did both on the Start tap, with an
                     * `await` on the API script in between. That await ends the
                     * gesture, so by the time playVideo() was reachable the
                     * browser had stopped counting it as user-initiated and
                     * refused, silently, on exactly the platform that matters
                     * (iOS). Building the player while the start screen is
                     * being read leaves the tap with one synchronous call to
                     * make.
                     */
                    /*
                     * The <audio> element is built up front; the YouTube player
                     * is NOT.
                     *
                     * An audio element is same-frame and inert until play() is
                     * called, so building it early only helps it buffer. A
                     * YouTube player built at page load is an iframe created
                     * outside any gesture, in a container that is not yet on
                     * screen — and WebKit decides very early whether a frame
                     * may produce sound. The version of this that demonstrably
                     * worked on an iPhone built the player INSIDE the tap, and
                     * that is restored: see mountPlayer's caller.
                     */
                    init() {
                        if (this.musicFile) {
                            this.mountAudio();
                        }
                    },

                    /**
                     * The uploaded track, as a plain <audio> element.
                     *
                     * On <body> and built in JavaScript for the same reason the
                     * YouTube player is: a node this component owns would be
                     * destroyed by the morph between two questions, and the
                     * music with it.
                     *
                     * There is nothing clever here, and that is the point. It
                     * is in the same document as the button, so play() called
                     * from the tap is authorised on every platform including
                     * iOS — which is the whole reason a file beats an embed.
                     */
                    mountAudio() {
                        if (window.__quizAudio?.isConnected) {
                            return;
                        }

                        const audio = document.createElement('audio');
                        audio.src = this.musicFile;
                        audio.loop = true;
                        audio.preload = 'auto';
                        // Backing music, not the main event: audible under a
                        // chime, quiet enough to talk over.
                        audio.volume = 0.35;
                        audio.setAttribute('playsinline', '');

                        document.body.appendChild(audio);
                        window.__quizAudio = audio;
                    },

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

                    /**
                     * Build the off-screen player, once per document.
                     *
                     * The container is created HERE rather than in the Blade,
                     * because a teleported node dies with the template that
                     * declared it and this one has to outlive every Livewire
                     * re-render between question one and the result. Parked far
                     * off-screen rather than display:none — a frame that has
                     * been hidden outright is what iOS refuses to play media
                     * in; one that is merely somewhere else is not.
                     */
                    async mountPlayer() {
                        if (window.__quizPlayerMount?.isConnected) {
                            return;
                        }

                        /*
                         * A CONTAINER, with the player's target INSIDE it.
                         *
                         * new YT.Player(el) REPLACES the element it is given
                         * with the iframe. Passing the tracked node therefore
                         * detached it the instant the player was ready — so
                         * `isConnected` was permanently false (rebuilding the
                         * player on every press) and revealPlayer() restyled a
                         * node that was no longer in the document, which is why
                         * the "tap play" prompt appeared with no player under
                         * it. The container survives; only the inner div is
                         * consumed.
                         */
                        const container = document.createElement('div');
                        container.id = 'quiz-music-player';

                        const target = document.createElement('div');
                        container.appendChild(target);

                        document.body.appendChild(container);
                        window.__quizPlayerMount = container;

                        // On screen from the moment it exists. A frame created
                        // off-screen may never be allowed to make a sound.
                        this.revealPlayer();

                        const YT = await this.youtube();

                        window.__quizPlayer = new YT.Player(target, {
                            videoId: this.musicId || undefined,
                            playerVars: {
                                // playsinline keeps iOS from throwing the video
                                // fullscreen over the question being answered.
                                playsinline: 1,
                                /*
                                 * CONTROLS ON, though the player spends almost
                                 * all its life off-screen. When iOS refuses the
                                 * API call this frame is the only thing that
                                 * can start the music, and it can only do that
                                 * if there is something in it to tap. Creating
                                 * a second player at that point would mean
                                 * loading the track twice.
                                 */
                                controls: 1,
                                modestbranding: 1,
                                rel: 0,
                                loop: 1,
                                // NOT autoplay: nothing may make noise before
                                // somebody has asked for it.
                                autoplay: 0,
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

                                    /*
                                     * The player is built from the tap, so this
                                     * fires a moment after it with the
                                     * document's activation still behind it —
                                     * which is exactly the sequence that played
                                     * on an iPhone before any of this was
                                     * "improved".
                                     */
                                    if (this.musicOn) {
                                        e.target.playVideo();
                                    }
                                },
                                /*
                                 * A single video ends rather than looping when
                                 * the `playlist` trick is refused, and a quiz
                                 * that falls silent half way through is worse
                                 * than one with no music at all.
                                 */
                                onStateChange: (e) => {
                                    // Playing at last — whether from the API or
                                    // from a tap on the frame. Put it away.
                                    if (e.data === YT.PlayerState.PLAYING) {
                                        this.musicOn = true;
                                        this.revealPlayer();
                                    }

                                    if (e.data === YT.PlayerState.ENDED && this.musicOn) {
                                        e.target.seekTo(0);
                                        e.target.playVideo();
                                    }
                                },
                            },
                        });
                    },

    /** Out of sight, and only when nothing is playing. */
                    parkPlayer() {
                        const mount = window.__quizPlayerMount;

                        if (mount) {
                            mount.setAttribute('aria-hidden', 'true');
                            mount.style.cssText = 'position:fixed;bottom:0;left:-9999px;'
                                + 'width:160px;height:90px;opacity:0;pointer-events:none;';
                            this.fillContainer(mount);
                        }
                    },

                    /**
                     * On screen, small, bottom-LEFT.
                     *
                     * Shown whenever the track is playing rather than only when
                     * something has gone wrong. WebKit will not play media in a
                     * frame it does not consider visible — hiding this is
                     * exactly what silenced the music on iPhones — and the
                     * left-hand corner keeps it off the primary button and off
                     * the thumb that rests on the right.
                     */
                    revealPlayer() {
                        const mount = window.__quizPlayerMount;

                        if (! mount) {
                            return;
                        }

                        mount.removeAttribute('aria-hidden');
                        mount.style.cssText = 'position:fixed;bottom:5.5rem;left:1rem;z-index:40;'
                            + 'width:150px;height:84px;border-radius:12px;overflow:hidden;'
                            + 'box-shadow:0 8px 24px rgba(15,23,42,.28);';

                        this.fillContainer(mount);
                    },

                    /** The iframe fills whatever the container currently is. */
                    fillContainer(container) {
                        const frame = container.querySelector('iframe');

                        if (frame) {
                            frame.style.cssText = 'width:100%;height:100%;border:0;display:block;';
                        }
                    },

                    /** The Start tap. Music begins if the quiz has any. */
                    autostart() {
                        if (! this.musicFile && ! this.musicId && ! this.musicList) {
                            return;
                        }

                        if (! this.musicOn) {
                            this.toggleMusic();
                        }
                    },

                    /*
                     * SYNCHRONOUS, DELIBERATELY. Every path from the tap to
                     * playVideo() has to be free of `await`: an await ends the
                     * user gesture, and playback outside a gesture is refused
                     * without an error. Where there is no player yet, one is
                     * BUILT here rather than played — creating the frame inside
                     * the tap is what the version that worked on an iPhone did,
                     * and onReady starts it a moment later with the document's
                     * activation still behind it.
                     */
                    toggleMusic() {
                        this.musicOn = ! this.musicOn;

                        /*
                         * A FILE NEEDS NO FALLBACK. Same document, same frame,
                         * so the tap authorises it — there is nothing to check
                         * afterwards and nothing to reveal. The catch is for a
                         * file that 404s or a codec the phone will not take,
                         * neither of which should stop the quiz.
                         */
                        if (this.musicFile) {
                            const audio = window.__quizAudio;

                            if (! audio?.isConnected) {
                                this.mountAudio();
                            }

                            try {
                                this.musicOn
                                    ? window.__quizAudio?.play()?.catch?.(() => {})
                                    : window.__quizAudio?.pause();
                            } catch (e) {}

                            return;
                        }

                        /*
                         * A player whose iframe has been swapped out from under
                         * it — wire:navigate outlives the document that built
                         * it — is dropped rather than talked to.
                         */
                        if (! window.__quizPlayer?.getIframe?.()?.isConnected) {
                            try { window.__quizPlayer?.destroy?.(); } catch (e) {}
                            window.__quizPlayer = null;
                        }

                        if (this.musicOn && ! window.__quizPlayer) {
                            // Built INSIDE this gesture, which is what the
                            // version that worked on an iPhone did. onReady
                            // starts it.
                            this.mountPlayer();

                            return;
                        }

                        try {
                            if (this.musicOn) {
                                window.__quizPlayer?.playVideo?.();
                            } else {
                                window.__quizPlayer?.pauseVideo?.();
                            }
                        } catch (e) {}

                        // Visible while it plays — see revealPlayer(). Hiding
                        // it is what silenced iPhones.
                        this.musicOn ? this.revealPlayer() : this.parkPlayer();

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
