{{--
    THE DELETE CONFIRMATION GATE.

    Rendered ONCE per layout. Every destructive control in the product routes
    through this one dialog by carrying `data-confirm-delete="<what it is>"`,
    so there is a single place that decides what a delete looks like rather
    than a `wire:confirm` string written afresh at each of the ninety-odd call
    sites.

    WHY A TYPED WORD RATHER THAN AN OK BUTTON. The native confirm() this
    replaces put "OK" under the reader's thumb on a phone — a second tap in
    the same spot as the first. On the staff list that is a 36px icon button
    followed by a dialog whose default action sits where the finger already
    is, and a member of staff was destroyed exactly that way. Muscle memory
    beats any wording you put above an OK button, which is why a FIXED word
    ("type DELETE") would not have helped either: it becomes something the
    hands learn. The word is generated fresh per dialog, so it cannot be
    typed ahead of reading, and copy/paste is blocked for the same reason.

    FAIL CLOSED. The interceptor is installed by the inline script below,
    which runs at parse time — before Alpine, before Livewire. If anything
    downstream fails to boot, the click stays cancelled and the delete does
    not happen. A guard that falls open under failure is not a guard.
--}}

@php
    /*
     * Short, common, unambiguous words. Constraints, in order of how much
     * they matter:
     *
     *   - No pair differs by one letter (no "cart"/"card"), or a typo lands
     *     on a different valid word and the reader is told they got it wrong
     *     with no idea why.
     *   - Nothing a phone keyboard autocorrects on the way in.
     *   - Four to six letters: long enough to be deliberate, short enough to
     *     type one-handed on a phone in a kitchen, which is where this
     *     product is actually used.
     *   - Ordinary English. This is read by people whose first language
     *     mostly is not English, so no jargon and nothing rare.
     */
    $confirmWords = [
        'anchor', 'basket', 'bridge', 'candle', 'copper', 'dragon',
        'engine', 'forest', 'garden', 'hammer', 'island', 'jacket',
        'kettle', 'ladder', 'magnet', 'needle', 'orbit', 'pencil',
        'rocket', 'silver', 'temple', 'violet', 'window', 'yellow',
    ];
@endphp

<script data-confirm-delete-interceptor>
(function () {
    // Guard against double-install — the component is rendered once per
    // layout, but a layout can be nested during a Livewire full-page swap.
    if (window.__servoraConfirmDelete) return;

    var ATTR = 'data-confirm-delete';

    var api = window.__servoraConfirmDelete = {
        // Set by the Alpine dialog once it is listening. Until then the
        // fallback below answers instead.
        ready: false,
        request: null,
        words: @js($confirmWords),
    };

    function randomWord() {
        return api.words[Math.floor(Math.random() * api.words.length)];
    }

    /*
     * WHAT HAPPENS IF ALPINE NEVER BOOTS — a blocked bundle, an old phone
     * that chokes on the JS, a Livewire error earlier on the page.
     *
     * Refusing outright would be safe but would present as the delete button
     * being broken, with nothing on screen saying why; somebody would then
     * reach for whatever workaround they could find. So the guarantee is kept
     * rather than the dialog: a plain prompt(), which is browser built-in and
     * cannot fail to load, asking for the same freshly-generated word. Uglier
     * than the panel, identical in what it demands.
     *
     * This is why the word list is defined HERE and shared with the dialog
     * rather than living inside the Alpine component — one list, so the two
     * paths cannot drift.
     */
    function fallback(subject) {
        var word = randomWord();
        var answer = window.prompt(
            (subject ? subject + '\n\n' : 'This cannot be undone.\n\n')
            + 'Type ' + word + ' to confirm.'
        );

        return answer !== null && answer.trim().toLowerCase() === word;
    }

    /*
     * A control that has just been confirmed. It is re-clicked
     * programmatically, and this is how the interceptor knows to stand
     * aside for that one synthetic event rather than looping forever.
     *
     * A WeakRef is not needed — it is cleared on the very next event.
     */
    var passing = null;

    function subjectOf(el) {
        return (el.getAttribute(ATTR) || '').trim();
    }

    function intercept(event) {
        // Let the re-dispatched click through, once.
        if (passing && (event.target === passing || passing.contains(event.target))) {
            passing = null;
            return;
        }

        var el = event.target.closest('[' + ATTR + ']');
        if (!el) return;

        // Modified clicks mean "open elsewhere" and never delete anything.
        if (event.type === 'click' && (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0)) return;

        // Cancel FIRST, unconditionally. Everything after this point can only
        // decide whether to let it happen again.
        event.preventDefault();
        event.stopPropagation();
        if (event.stopImmediatePropagation) event.stopImmediatePropagation();

        var proceed = function () {
            passing = el;
            if (el.tagName === 'FORM') {
                // requestSubmit so the button's formaction and the form's
                // validation still apply; submit() would skip both.
                el.requestSubmit ? el.requestSubmit() : el.submit();
            } else {
                el.click();
            }
            // Belt and braces: the interceptor clears this when it sees the
            // re-dispatched event, but if that event never arrives (the
            // element was swapped out mid-flight) a stale value would wave
            // the NEXT click through unguarded.
            passing = null;
        };

        if (api.ready && typeof api.request === 'function') {
            api.request(subjectOf(el), proceed);
        } else if (fallback(subjectOf(el))) {
            proceed();
        }
    }

    // Capture phase on both, so this runs BEFORE Livewire's own click
    // handler and before any inline onsubmit.
    document.addEventListener('click', intercept, true);
    document.addEventListener('submit', intercept, true);
})();
</script>

{{--
    TWO THINGS ABOUT THIS ELEMENT THAT LOOK LIKE STYLE AND ARE NOT.

    1. THE HANDLER IS REGISTERED IN init(), NOT IN x-init. Alpine evaluates an
       attribute expression and then INVOKES the result if it turns out to be
       a function. An x-init that assigns an arrow function evaluates TO that
       arrow function, so Alpine called it immediately — on every page load,
       with no arguments — and this dialog opened over the staff list before
       anybody had touched anything. Inside a method body an assignment is
       just an assignment.

    2. NO DOUBLE QUOTES ANYWHERE INSIDE THE x-data ATTRIBUTE, comments
       included. The attribute is delimited by them, so one in a comment ends
       the attribute early and the rest of the object is parsed as stray HTML
       attributes. That failure is silent in the markup and shows up as the
       component simply not working.
--}}
<div
    x-data="{
        open: false,
        subject: '',
        word: '',
        typed: '',
        proceed: null,
        // The same list the interceptor's prompt() fallback draws from, so
        // the two paths cannot drift apart.
        words: window.__servoraConfirmDelete.words,

        // Registration happens here, not in x-init. See the note above the
        // element — it is a correctness requirement, not a preference.
        init() {
            window.__servoraConfirmDelete.request = (subject, proceed) => this.start(subject, proceed);
            window.__servoraConfirmDelete.ready   = true;
        },

        get matched() {
            // Case- and space-insensitive: a phone keyboard capitalises the
            // first letter by itself, and refusing that would be punishing
            // somebody for their keyboard rather than checking they meant it.
            return this.typed.trim().toLowerCase() === this.word;
        },

        start(subject, proceed) {
            this.subject = subject;
            this.proceed = proceed;
            this.word    = this.words[Math.floor(Math.random() * this.words.length)];
            this.typed   = '';
            this.open    = true;

            document.body.classList.add('overflow-y-hidden');
            this.$nextTick(() => this.$refs.field && this.$refs.field.focus());
        },

        cancel() {
            this.open    = false;
            this.proceed = null;
            this.typed   = '';
            document.body.classList.remove('overflow-y-hidden');
        },

        confirm() {
            if (! this.matched) return;

            const go = this.proceed;
            this.open    = false;
            this.proceed = null;
            this.typed   = '';
            document.body.classList.remove('overflow-y-hidden');

            if (go) go();
        },
    }"
    x-on:keydown.escape.window="open && cancel()"
    x-cloak
>
    <div x-show="open"
         class="fixed inset-0 z-[300] flex items-end justify-center p-0 sm:items-center sm:p-4"
         role="dialog" aria-modal="true" aria-labelledby="confirm-delete-title">

        {{-- Scrim. Deliberately NOT click-to-dismiss: on a phone this panel
             fills the screen and a stray tap at its edge would be the same
             accident in the other direction. Cancel is a button. --}}
        <div x-show="open"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="absolute inset-0 bg-gray-900/70"></div>

        <div x-show="open"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="relative w-full max-w-md rounded-t-panel bg-white shadow-e3 sm:rounded-panel"
             x-on:keydown.enter.prevent="confirm()">

            <div class="flex items-start gap-3 p-5 sm:p-6">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-danger-50">
                    <x-icon name="warning" size="h-5 w-5" stroke="2" class="text-danger-600" />
                </div>
                {{-- The heading is fixed and the MESSAGE is per site: the copy
                     each screen already carried on its wire:confirm was written
                     for that screen and says what this particular delete costs,
                     which no generic sentence here could. --}}
                <div class="min-w-0 flex-1">
                    <h2 id="confirm-delete-title" class="text-base font-semibold text-gray-900">
                        Confirm deletion
                    </h2>
                    <p class="mt-1 break-words text-sm text-gray-600"
                       x-text="subject || 'This cannot be undone.'"></p>
                </div>
            </div>

            <div class="border-t border-gray-200/80 px-5 pb-5 pt-4 sm:px-6 sm:pb-6">
                <label for="confirm-delete-field" class="label">
                    Type <span class="select-none font-mono font-bold tracking-widest text-danger-700"
                               x-text="word"></span> to confirm
                </label>

                {{-- autocapitalize/autocorrect/spellcheck off: a phone keyboard
                     would otherwise "fix" the word into something that no
                     longer matches, and the reader is left retyping a word
                     that looks right on screen.

                     Paste is blocked on purpose. Selecting the word above and
                     pasting it in is the same reflex this dialog exists to
                     interrupt. --}}
                <input id="confirm-delete-field"
                       x-ref="field"
                       type="text"
                       x-model="typed"
                       x-on:paste.prevent
                       autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"
                       class="input mt-2 font-mono tracking-widest"
                       :class="typed.length && ! matched ? 'input-error' : ''"
                       placeholder="Type the word above">

                <p class="error-text mt-1.5" x-show="typed.length && ! matched" x-cloak>
                    That does not match yet.
                </p>

                {{-- flex-col-reverse on a phone puts CANCEL at the bottom of
                     the sheet — nearest the thumb — and Delete above it. That
                     is the opposite of the usual bottom-sheet convention, and
                     it is the whole point: this dialog exists because the
                     destructive answer sat exactly where the finger already
                     was. On a wide screen the row reads Cancel then Delete in
                     the ordinary way. --}}
                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="btn-secondary" x-on:click="cancel()">
                        Cancel
                    </button>
                    {{-- Disabled until the word matches, so the destructive
                         button is literally unreachable by a second tap in
                         the same place as the first. --}}
                    <button type="button" class="btn-danger" x-on:click="confirm()"
                            :disabled="! matched">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
