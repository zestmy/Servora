import './bootstrap';
import './quiz-fx';

/**
 * Scroll reveal.
 *
 * IntersectionObserver, never a scroll listener — a scroll handler fires on
 * every frame and janks on the mid-range Android tablets this app runs on in
 * kitchens.
 *
 * Progressive enhancement, in this order:
 *
 *   1. `.reveal` is VISIBLE in CSS by default.
 *   2. This module sets `js-reveal` on <html> during evaluation, before first
 *      paint, which is what actually arms the hidden state.
 *   3. The observer removes it again per element as each scrolls into view.
 *
 * So a failed, blocked or slow bundle leaves the page fully readable rather
 * than stranding content at opacity 0.
 */
const REDUCED = window.matchMedia('(prefers-reduced-motion: reduce)');

// Arm the hidden state only when we can guarantee we can also un-hide it.
if ('IntersectionObserver' in window && !REDUCED.matches) {
    document.documentElement.classList.add('js-reveal');
}

/** Reveal everything and stop hiding anything further. Used as the escape hatch. */
function revealAll() {
    document.documentElement.classList.remove('js-reveal');
    document.querySelectorAll('.reveal').forEach((el) => el.classList.add('is-in'));
}

let observer = null;

function initReveal() {
    if (!document.documentElement.classList.contains('js-reveal')) return;

    if (!observer) {
        observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    // isIntersecting alone misses elements skipped during a
                    // fast flick or an anchor jump, where the callback fires
                    // once the element is already above the viewport.
                    const r = entry.boundingClientRect;
                    const passed = r.bottom < 0;
                    if (!entry.isIntersecting && !passed) return;

                    // Stagger siblings so a grid arrives as a sequence rather
                    // than a single flash. Skipped for anything scrolled past,
                    // which should just be there already.
                    if (!passed) {
                        const i = Number(entry.target.dataset.revealIndex || 0);
                        entry.target.style.transitionDelay = `${Math.min(i, 6) * 60}ms`;
                    }

                    entry.target.classList.add('is-in');
                    observer.unobserve(entry.target);
                });
            },
            { rootMargin: '200px 0px 0px 0px', threshold: 0 }
        );
    }

    document.querySelectorAll('.reveal:not(.is-in)').forEach((el) => observer.observe(el));
}

// If the OS setting flips mid-session, drop the animation immediately.
REDUCED.addEventListener('change', (e) => e.matches && revealAll());

// Safety net: nothing should still be hidden several seconds in. Covers any
// case the observer misses (restored scroll position, print, odd browsers).
window.setTimeout(revealAll, 4000);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReveal, { once: true });
} else {
    initReveal();
}

// Livewire swaps DOM without a page load, so new content needs re-observing.
document.addEventListener('livewire:navigated', initReveal);

/* ── Back arrows go back ──────────────────────────────────────────────────
 *
 * A page's "<" used to be an ordinary link to whatever that page's parent
 * happened to be, so arriving at HR → Compensation → an employee from
 * somewhere else and pressing it landed you on a screen you had never asked
 * for. It now returns to the page you actually came from.
 *
 * The href stays a real URL and is the FALLBACK, which is what runs when:
 *   - the page was opened directly (bookmark, pasted link, first page of the
 *     session), where "back" has no meaning;
 *   - the previous page was another site;
 *   - JavaScript has not booted, or the link is middle-clicked into a new tab.
 *
 * The referrer is the test, not history.length: a fresh tab reports 1 in some
 * browsers and 2 in others, and it counts forward entries too. Delegated from
 * document so it keeps working after Livewire swaps the DOM.
 */
document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-back]');
    if (! link || event.defaultPrevented) return;

    // Leave modified clicks alone — they mean "open elsewhere", and the href
    // is the right destination for that.
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;

    const referrer = document.referrer;
    if (! referrer) return;

    let previous;
    try {
        previous = new URL(referrer);
    } catch (e) {
        return;
    }

    // Same site, and not this very page — going "back" to where you already
    // are looks like the button is broken.
    if (previous.origin !== window.location.origin) return;
    if (previous.href === window.location.href) return;

    // The href wins when it is the SAME PAGE you came from carrying state that
    // page could not put in its own URL.
    //
    // The employee list keeps its outlet, section, employment and status in
    // Livewire properties, so its address stays a bare /hr/employees however
    // it is filtered. Going back to that address reverts to the default
    // filter, which reads as the button having lost your place. The href on
    // these links is built from the filters deliberately, so it is the same
    // destination, only truthful — follow it.
    //
    // Only when the paths match: a different page means you genuinely came
    // from somewhere else, and returning there is the whole point of this.
    const target = new URL(link.href, window.location.origin);
    if (target.pathname === previous.pathname && target.search && ! previous.search) return;

    event.preventDefault();
    window.history.back();
});
