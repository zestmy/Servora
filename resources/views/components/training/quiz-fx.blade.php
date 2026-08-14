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

    SOUND IS ON BY DEFAULT AND MUSIC IS NOT. A phone that suddenly plays music
    on a service pass is a phone that gets put face down for the rest of the
    shift; a short chime is not. Both choices are remembered in localStorage per
    device, which is the right scope — it is a property of where the phone is,
    not of who is holding it.

    Autoplay policy is handled by construction rather than fought with: the
    AudioContext is created on the first user gesture (every route into this
    screen goes through a tap), and the music iframe is only inserted when
    somebody presses the speaker. Nothing here ever tries to make noise the
    browser has not already been told is wanted.

    Props:
      music  a YouTube embed URL, or null for a quiz with no backing track
--}}
@props(['music' => null])

<div x-data="quizFx(@js($music))"
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

    {{-- The controls. One row, both 44px, because they are pressed with the
         same hands as everything else in this app. --}}
    <div class="flex items-center justify-end gap-1">
        <button type="button" @click="toggleSound()"
                class="icon-btn" :aria-pressed="sound.toString()"
                :aria-label="sound ? 'Turn sounds off' : 'Turn sounds on'">
            <span x-show="sound" class="text-base leading-none" aria-hidden="true">&#128266;</span>
            <span x-show="! sound" class="text-base leading-none opacity-50" aria-hidden="true">&#128263;</span>
        </button>

        @if ($music)
            <button type="button" @click="toggleMusic()"
                    class="icon-btn" :aria-pressed="musicOn.toString()"
                    :aria-label="musicOn ? 'Stop the music' : 'Play music'">
                <span class="text-base leading-none" :class="musicOn ? '' : 'opacity-50'"
                      aria-hidden="true">&#127925;</span>
            </button>
        @endif
    </div>

    @if ($music)
        {{-- 1x1 rather than display:none. A hidden iframe is allowed to be
             dropped from the layout entirely by some engines, and a dropped
             iframe is a silent one. It is inserted only once the speaker has
             been pressed, so a quiz nobody wanted music on never contacts
             YouTube at all — which is also the privacy answer. --}}
        <template x-if="musicOn">
            <div class="fixed bottom-0 right-0 h-px w-px overflow-hidden opacity-0" aria-hidden="true">
                <iframe :src="musicSrc" width="1" height="1" frameborder="0"
                        allow="autoplay; encrypted-media" referrerpolicy="no-referrer"
                        title="Background music"></iframe>
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

                window.Alpine.data('quizFx', (musicSrc) => ({
                    sound: localStorage.getItem('quizSound') !== 'off',
                    musicOn: localStorage.getItem('quizMusic') === 'on' && !! musicSrc,
                    musicSrc: musicSrc,
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

                        if (! this.sound) {
                            return;
                        }

                        right ? this.ding() : this.buzz();

                        // A phone in an apron pocket is heard before it is seen.
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

                    toggleMusic() {
                        this.musicOn = ! this.musicOn;
                        localStorage.setItem('quizMusic', this.musicOn ? 'on' : 'off');
                    },
                }));
            };

            window.Alpine
                ? register()
                : document.addEventListener('alpine:init', register);
        })();
    </script>
@endonce
