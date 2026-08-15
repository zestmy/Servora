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

    ── THE MUSIC IS AN <audio> ELEMENT, AND ONLY THAT ──

    This is the second time YouTube has been removed from this component, and
    this time the verdict is complete, both halves tested on a real iPhone:

    APPLE BLOCKS THE START. A user gesture belongs to the frame it happened in,
    so no button on this page can start an embed — proven five ways, ending
    with the gesture-born iframe carrying autoplay in its own URL. The one tap
    iOS honours is inside the player, and that was built: a card in the page's
    flow whose play button started the music and the quiz together. It worked.

    YOUTUBE STOPS THE CONTINUATION. The moment that playing player was parked
    off-screen for the first question, the track stopped — on the same phone,
    same session. Whether WebKit's off-screen media policy or the player's own
    visibility check pulled the trigger is academic: an embed will play only
    while it is LOOKED AT, and background listening is precisely the feature
    YouTube sells as Premium. A quiz's backing track is the exact thing the
    embed is built to refuse to be.

    A native <audio> element has neither problem. Same document as the button,
    so the tap authorises it on every platform; no visibility policing, so it
    plays parked, backgrounded and ignored — which is how the wedding-card
    sites this was diagnosed against do it, every one of them with a file.

    The builder keeps its link field because a DIRECT AUDIO URL (.mp3, .m4a,
    .ogg, .wav) is treated as a file and works identically. A YouTube URL saved
    there is kept on the record but plays nothing on a staff phone, and the
    field says so.

    Props:
      musicFile  an uploaded audio URL, or null for a quiz with no track
--}}
@props(['musicFile' => null])

<div x-data="quizFx(@js($musicFile))"
     @answer-scored.window="react($event.detail)"
     @start-music.window="autostart()"
     {{-- The marker the navigation cleanup looks for: a page without it is a
          page the music must not follow anybody onto. --}}
     @if ($musicFile) data-quiz-music @endif
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
            @if ($musicFile)
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

{{-- The component's JavaScript is registered from resources/js/quiz-fx.js,
     bundled into app.js — NOT inline here. An inline script does not reliably
     run before Alpine initialises a wire:navigate-d page, so the first quiz
     visit of a session that arrived by navigation (which in the installed PWA
     is every visit) mounted this component against an undefined quizFx and it
     was born dead. See the note at the top of that file. --}}
