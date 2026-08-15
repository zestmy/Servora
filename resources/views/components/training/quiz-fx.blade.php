{{--
    The noise and the colour a quiz makes.

    EVERYTHING THIS RENDERS IS FIXED OR TELEPORTED, and that is structural
    rather than stylistic. The component is mounted as the first child of the
    screen it belongs to, in the same position on the start screen and on the
    question screen, so Livewire's morph carries it from one to the other
    instead of tearing it down — and a torn-down player is a track that stops
    the moment the quiz begins. Nothing here occupies space in the layout, so
    "first child" costs the page nothing.

    SOUND EFFECTS ARE SYNTHESISED, NOT FILES. Three reasons, and the first is
    enough: the staff app is a PWA used on a kitchen floor with patchy wifi, and
    an mp3 that has not finished downloading is a correct answer with no reward
    — the one moment the feedback had to land. Web Audio has no such failure
    mode, it is instant, and there is nothing to cache. The other two: no
    licensing question over a sound effect shipped to merchants, and no 200 KB
    of audio in a bundle for a two-note chime.

    ON AN IPHONE, THE RINGER SWITCH SILENCES WEB AUDIO. Media elements ignore
    it; an AudioContext does not. So the chime honestly cannot be made to play
    on a handset in silent mode, and the flash, the shake and the vibration are
    not decoration around it — for a phone on silent they ARE the feedback.

    ── A FILE FIRST, A YOUTUBE LINK SECOND ──

    AN UPLOADED FILE IS A NATIVE <audio> ELEMENT, in the same document as the
    button, so the tap authorises it directly on every platform — which is
    exactly how the wedding-invitation sites that do this well work, including
    the one this was diagnosed against. One element, one play(), a floating
    button to stop it, nothing on screen.

    A YOUTUBE LINK IS AN EMBED, and the audited account of what an embed can do
    on an iPhone is narrower than the categorical "cannot play" this comment
    used to state. What is true: a user gesture belongs to the FRAME it
    happened in, so playVideo() sent by postMessage into a cross-origin iframe
    carries no activation — every earlier version here relied on exactly that
    call (the last one built the player in the tap with autoplay:0 and played
    from onReady, which fires in a LATER TASK, outside the gesture) and could
    not have worked.

    What that does NOT rule out, and what this version does: create the IFRAME
    ITSELF synchronously inside the tap, with autoplay=1 carried in its URL and
    allow="autoplay" on the element. Then no postMessage is needed for the
    first play at all — user activation propagates to a browsing context
    created during the gesture, and the allow attribute delegates autoplay to
    the cross-origin frame where Permissions Policy is honoured. The API is
    attached to the already-playing iframe afterwards, purely for pause, resume
    and ducking, where the await can no longer break anything.

    TESTED ON A REAL IPHONE (Aug 2026): REFUSED. The gesture-born frame with
    autoplay in its URL is the fifth and strongest technique tried, and it is
    the last one there is — what remains after it is a tap INSIDE a visible
    player, which is a product decision, not an engineering one. The code stays
    because it is the correct behaviour for Android and desktop, where it
    plays; on iOS it fails silently, and the builder's copy now says so
    plainly. The uploaded FILE (or a direct audio link) is the iPhone path.

    The frame stays parked off-screen — never display:none, which is the one
    hiding method media is refused in, and never on screen, because a window
    over the quiz was the other half of this feature's history.

    Props:
      music      a YouTube embed URL, or null
      musicFile  an uploaded audio URL, or null. Used in preference to the link.
--}}
@props(['music' => null, 'musicFile' => null])

@php
    // The API wants an id and a playlist, not an embed URL. The model keeps the
    // parsing that decides what is SAFE; this is only the shape the API takes.
    $musicId = null;
    $musicList = null;

    if ($music && ! $musicFile) {
        if (preg_match('~/embed/([A-Za-z0-9_-]{11})~', $music, $m)) {
            $musicId = $m[1];
        }

        if (preg_match('~[?&]list=([A-Za-z0-9_-]{12,})~', $music, $m)) {
            $musicList = $m[1];
        }
    }

    $hasMusic = $musicFile || $musicId || $musicList;
@endphp

<div x-data="quizFx(@js($musicFile), @js($musicId), @js($musicList))"
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

    {{-- The controls, floating bottom-right and clear of the tab bar. 44px
         each, because they are pressed with the same hands as everything else
         in this app. Nothing else is drawn: the track has no window, which is
         the whole point of using an audio element. --}}
    <template x-teleport="body">
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

                window.Alpine.data('quizFx', (musicFile, musicId, musicList) => ({
                    sound: localStorage.getItem('quizSound') !== 'off',
                    // Never restored from storage. Music that starts itself
                    // because of a choice made on a different shift is the
                    // thing a phone gets put face down for.
                    musicOn: false,
                    musicFile: musicFile,
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
                     * The element is built at page load, which is free: an
                     * <audio> element makes no sound until play() is called,
                     * and having it in the document early lets it buffer while
                     * somebody reads the start screen.
                     */
                    init() {
                        if (this.musicFile) {
                            this.mountAudio();

                            return;
                        }

                        if (this.musicId || this.musicList) {
                            // Warm the API script. Loading a script needs no
                            // gesture; only playing does — and having YT ready
                            // means the tap can wrap the iframe immediately.
                            this.youtube();
                        }
                    },

                    /** The embed URL, with autoplay CARRIED IN IT. */
                    embedSrc() {
                        const params = new URLSearchParams({
                            autoplay: '1',
                            playsinline: '1',
                            controls: '0',
                            rel: '0',
                            loop: '1',
                            enablejsapi: '1',
                            origin: window.location.origin,
                        });

                        if (this.musicList) {
                            params.set('list', this.musicList);
                            params.set('listType', 'playlist');

                            return 'https://www.youtube-nocookie.com/embed/'
                                + (this.musicId || 'videoseries') + '?' + params.toString();
                        }

                        // A single video only loops when it also names itself
                        // as the playlist — YouTube's own quirk.
                        params.set('playlist', this.musicId);

                        return 'https://www.youtube-nocookie.com/embed/'
                            + this.musicId + '?' + params.toString();
                    },

                    /**
                     * The iframe, born INSIDE the tap. See the note at the top:
                     * with autoplay=1 in the URL and allow="autoplay" on the
                     * element, the child document's first play needs no
                     * postMessage — which is the only kind of play an iPhone
                     * was ever going to refuse.
                     *
                     * SYNCHRONOUS to the appendChild. The API wrap that follows
                     * is async and only exists for pause, resume and ducking —
                     * by then the frame is already playing or already refused,
                     * and nothing the wrap does can change which.
                     */
                    mountEmbedSync() {
                        if (window.__quizPlayerMount?.isConnected) {
                            return;
                        }

                        const container = document.createElement('div');
                        container.id = 'quiz-music-player';
                        container.setAttribute('aria-hidden', 'true');
                        // Off-screen, never display:none — the one hiding
                        // method media is refused in. Real size, so the frame
                        // is rendered.
                        container.style.cssText = 'position:fixed;bottom:0;left:-9999px;'
                            + 'width:320px;height:180px;overflow:hidden;pointer-events:none;';

                        const frame = document.createElement('iframe');
                        frame.src = this.embedSrc();
                        frame.title = 'Background music';
                        frame.allow = 'autoplay; encrypted-media';
                        frame.style.cssText = 'width:100%;height:100%;border:0;';

                        container.appendChild(frame);
                        document.body.appendChild(container);
                        window.__quizPlayerMount = container;

                        this.youtube().then((YT) => {
                            try {
                                window.__quizPlayer = new YT.Player(frame, {
                                    events: {
                                        onReady: (e) => {
                                            // Backing music, not the main
                                            // event. iOS ignores programmatic
                                            // volume; elsewhere this tucks it
                                            // under the chime.
                                            try { e.target.setVolume(35); } catch (err) {}
                                        },
                                        onStateChange: (e) => {
                                            // The playlist trick covers the
                                            // loop; this covers the platforms
                                            // that refuse the trick, because a
                                            // quiz that falls silent half way
                                            // through is worse than one with no
                                            // music at all.
                                            if (e.data === YT.PlayerState.ENDED && this.musicOn) {
                                                e.target.seekTo(0);
                                                e.target.playVideo();
                                            }
                                        },
                                    },
                                });
                            } catch (e) {}
                        });
                    },

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
                     * The track, on <body>, built in JavaScript.
                     *
                     * Not in the template: a node this component owns would be
                     * destroyed by the morph between two questions and the
                     * music with it. Built here, nothing Livewire morphs can
                     * reach it.
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

                    /** The Start tap. */
                    autostart() {
                        if ((this.musicFile || this.musicId || this.musicList) && ! this.musicOn) {
                            this.toggleMusic();
                        }
                    },

                    /**
                     * SYNCHRONOUS, and that is the whole trick.
                     *
                     * Same document, same frame, no await between the tap and
                     * play() — so the gesture authorises it on every platform,
                     * iPhone included. The catch is for a file that 404s or a
                     * codec the phone will not take, neither of which should
                     * stop the quiz.
                     */
                    toggleMusic() {
                        if (! this.musicFile && ! this.musicId && ! this.musicList) {
                            return;
                        }

                        this.musicOn = ! this.musicOn;

                        if (this.musicFile) {
                            if (! window.__quizAudio?.isConnected) {
                                this.mountAudio();
                            }

                            try {
                                this.musicOn
                                    ? window.__quizAudio?.play()?.catch?.(() => {})
                                    : window.__quizAudio?.pause();
                            } catch (e) {}

                            return;
                        }

                        // A player whose iframe was swapped out from under it —
                        // wire:navigate outlives the document that built it — is
                        // dropped rather than talked to.
                        if (! window.__quizPlayerMount?.isConnected) {
                            try { window.__quizPlayer?.destroy?.(); } catch (e) {}
                            window.__quizPlayer = null;
                        }

                        if (this.musicOn && ! window.__quizPlayerMount?.isConnected) {
                            /*
                             * FIRST PLAY. The iframe is created here, inside
                             * the gesture, with autoplay in its URL — no
                             * postMessage involved. This is the call that has
                             * to stay synchronous.
                             */
                            this.mountEmbedSync();

                            return;
                        }

                        /*
                         * Later toggles go through the API. A media element
                         * that has played once in its document may be resumed
                         * programmatically, so the postMessage restriction that
                         * decided the first play no longer applies.
                         */
                        try {
                            this.musicOn
                                ? window.__quizPlayer?.playVideo?.()
                                : window.__quizPlayer?.pauseVideo?.();
                        } catch (e) {}
                    },

                    /** Drop the music while a verdict is being heard. */
                    duck() {
                        if (! this.musicOn) {
                            return;
                        }

                        if (! this.musicFile) {
                            try {
                                window.__quizPlayer?.setVolume?.(10);
                                clearTimeout(this._duck);
                                this._duck = setTimeout(() => {
                                    try { window.__quizPlayer?.setVolume?.(35); } catch (e) {}
                                }, 900);
                            } catch (e) {}

                            return;
                        }

                        const audio = window.__quizAudio;

                        if (! audio) {
                            return;
                        }

                        // Swallowed on purpose: ducking is the least important
                        // thing happening at this moment.
                        try {
                            audio.volume = 0.12;
                            clearTimeout(this._duck);
                            this._duck = setTimeout(() => {
                                try { audio.volume = 0.35; } catch (e) {}
                            }, 900);
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
