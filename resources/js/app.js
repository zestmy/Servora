import './bootstrap';

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
