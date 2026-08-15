/*
 * The quiz effects component — sound, verdict ribbon, backing music.
 *
 * REGISTERED FROM THE BUNDLE, NOT FROM THE BLADE, and that placement is the
 * fix for a bug that wore five other bugs' clothes. The registration used to
 * live in an inline <script> inside the component's own view. On a cold page
 * load that works — the script parses before Alpine boots. But every link in
 * the staff app navigates with wire:navigate, and on a navigated arrival the
 * incoming page's Alpine tree is initialised without the inline body script
 * reliably running first — so the FIRST quiz visit of a session that arrived
 * by navigation mounted x-data="quizFx(...)" with quizFx undefined, and the
 * component was born dead: no music, no ribbon, no chime. The installed PWA
 * always arrives by navigation (launch → Home → Learn → quiz), which is why
 * an uploaded mp3 that passed every server check played nothing.
 *
 * This file is loaded in <head> by Vite on every full page load, before
 * Livewire starts Alpine, so the component exists before any tree that might
 * want it. The markup stays in
 * resources/views/components/training/quiz-fx.blade.php, which passes the
 * music URL in as an x-data argument — data travels in the page, behaviour
 * travels in the bundle.
 */
/*
         * In the bundle this always takes the alpine:init path — the module
         * evaluates in <head>, before Alpine exists. The direct-call branch
         * and the registration flag are kept because they cost nothing and
         * make the function safe to load twice, whatever the build does.
         */
        (() => {
            const register = () => {
                if (window.Alpine.__quizFxRegistered) {
                    return;
                }

                window.Alpine.__quizFxRegistered = true;

                window.Alpine.data('quizFx', (musicFile) => ({
                    sound: localStorage.getItem('quizSound') !== 'off',
                    // Never restored from storage. Music that starts itself
                    // because of a choice made on a different shift is the
                    // thing a phone gets put face down for.
                    musicOn: false,
                    musicFile: musicFile,
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
                        }
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
                        if (this.musicFile && ! this.musicOn) {
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
                        if (! this.musicFile) {
                            return;
                        }

                        this.musicOn = ! this.musicOn;

                        if (! window.__quizAudio?.isConnected) {
                            this.mountAudio();
                        }

                        try {
                            this.musicOn
                                ? window.__quizAudio?.play()?.catch?.(() => {})
                                : window.__quizAudio?.pause();
                        } catch (e) {}
                    },

                    /** Drop the music while a verdict is being heard. */
                    duck() {
                        const audio = window.__quizAudio;

                        if (! this.musicOn || ! audio) {
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

                /*
                 * LEAVING THE QUIZ STOPS THE MUSIC. The audio element lives on
                 * <body> so the morph between questions cannot kill it — which
                 * also means a wire:navigate to any other page cannot kill it,
                 * and a track that follows somebody to the leaderboard is a
                 * bug, not ambience. Every quiz screen with music marks itself;
                 * a page without the marker is a page the music must not play
                 * on.
                 */
                document.addEventListener('livewire:navigated', () => {
                    if (document.querySelector('[data-quiz-music]')) {
                        return;
                    }

                    try { window.__quizAudio?.pause(); } catch (e) {}
                    try { window.__quizAudio?.remove(); } catch (e) {}
                    window.__quizAudio = null;
                });
            };

            window.Alpine
                ? register()
                : document.addEventListener('alpine:init', register);
        })();
