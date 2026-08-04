{{--
    "Add this to your home screen" for the staff apps.

    Shared by the labels and clock-in shells because the two had a copy each,
    and both copies only ever worked on Android: they waited for
    `beforeinstallprompt`, an event Safari has never fired and says it will
    not. Every iPhone therefore saw nothing at all — on apps that are used by
    tapping a home screen icon at the start of a shift.

    So there are two paths, not one:

      Android / Chrome — the browser offers to install, and the button here
      triggers that offer directly.

      iOS Safari — no API exists. The only way in is Share → Add to Home
      Screen, so the notice says exactly that, with the share glyph drawn
      rather than described, because "the share button" is not a thing most
      people can point to on demand.

    Anything already installed shows nothing: `display-mode: standalone` is
    the reliable signal on both platforms, and nagging somebody to install an
    app they are currently standing inside is how a banner gets ignored
    permanently.

    @param string $appName    e.g. 'Clock In'
    @param string $storageKey e.g. 'clock-install-dismissed'
--}}

<div id="pwa-install" class="hidden shrink-0 px-3 pb-3 safe-x">
    <div class="rounded-xl bg-gray-900 text-white shadow-lg px-4 py-3">
        <div class="flex items-start gap-3">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold">Add {{ $appName }} to your home screen</p>

                {{-- One of these is revealed by the script; never both. --}}
                <p data-pwa-android class="hidden mt-0.5 text-xs text-gray-300">
                    Opens full screen, and you skip signing in through the browser each time.
                </p>

                <p data-pwa-ios class="hidden mt-1 text-xs text-gray-300 leading-relaxed">
                    Tap
                    <span class="inline-flex items-center justify-center align-middle mx-0.5 h-5 w-5 rounded bg-white/15">
                        {{-- The iOS share glyph, drawn so it can be matched
                             against the real one by eye. --}}
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 16V4"/>
                            <path d="M8 8l4-4 4 4"/>
                            <path d="M5 12v7a1 1 0 001 1h12a1 1 0 001-1v-7"/>
                        </svg>
                    </span>
                    in the browser bar, then choose <span class="font-medium text-white">Add to Home Screen</span>.
                </p>

                <p data-pwa-ios-other class="hidden mt-1 text-xs text-gray-300 leading-relaxed">
                    Open this page in <span class="font-medium text-white">Safari</span> first — only Safari can
                    add a page to the home screen on iPhone.
                </p>
            </div>

            <button type="button" data-pwa-dismiss aria-label="Dismiss"
                    class="shrink-0 -mr-1 -mt-1 px-2 text-lg leading-none text-gray-400 active:text-white">&times;</button>
        </div>

        <button type="button" data-pwa-add
                class="hidden mt-2 w-full rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-900 active:bg-gray-200">
            Add to home screen
        </button>
    </div>
</div>

<script>
(() => {
    const KEY  = @js($storageKey);
    const bar  = document.getElementById('pwa-install');

    if (! bar) return;

    let deferred = null;

    const stored = (name) => {
        try { return localStorage.getItem(name); } catch (e) { return null; }
    };

    /**
     * Already installed?
     *
     * display-mode covers Android and modern iOS; navigator.standalone is
     * the older iOS signal and still the only one some versions set.
     */
    const installed = () =>
        window.matchMedia?.('(display-mode: standalone)').matches === true
        || window.navigator.standalone === true;

    const ua    = navigator.userAgent || '';
    // iPadOS 13+ reports itself as a Mac, so touch points are what actually
    // distinguishes an iPad from a desktop Safari.
    const isIos = /iphone|ipad|ipod/i.test(ua)
        || (/macintosh/i.test(ua) && (navigator.maxTouchPoints || 0) > 1);
    // Chrome and Firefox on iOS cannot add to the home screen at all — they
    // borrow Safari's engine but not that menu.
    const isIosSafari = isIos && ! /crios|fxios|edgios/i.test(ua);

    function show(which) {
        bar.querySelector(`[data-pwa-${which}]`)?.classList.remove('hidden');

        if (which === 'android') {
            bar.querySelector('[data-pwa-add]')?.classList.remove('hidden');
        }

        bar.classList.remove('hidden');
    }

    function evaluate() {
        // Standing inside the installed app, or told to go away already.
        if (installed() || stored(KEY) === '1') {
            bar.classList.add('hidden');

            return;
        }

        if (deferred) {
            show('android');
        } else if (isIosSafari) {
            show('ios');
        } else if (isIos) {
            show('ios-other');
        }
        // Everything else — desktop, an Android that has not offered yet —
        // shows nothing rather than instructions it cannot act on.
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferred = event;
        evaluate();
    });

    // Fires after the OS install completes; the banner has no business
    // surviving that.
    window.addEventListener('appinstalled', () => {
        try { localStorage.setItem(KEY, '1'); } catch (e) {}
        bar.classList.add('hidden');
    });

    bar.querySelector('[data-pwa-add]')?.addEventListener('click', async () => {
        bar.classList.add('hidden');

        if (deferred) {
            deferred.prompt();
            deferred = null;
        }
    });

    bar.querySelector('[data-pwa-dismiss]')?.addEventListener('click', () => {
        bar.classList.add('hidden');
        try { localStorage.setItem(KEY, '1'); } catch (e) {}
    });

    evaluate();

    // wire:navigate swaps the page without reloading, and this partial is in
    // the shell rather than the swapped body — so the check is re-run rather
    // than the listeners re-bound.
    document.addEventListener('livewire:navigated', evaluate);
})();
</script>
