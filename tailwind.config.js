import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/**
 * Servora design tokens.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * IMPORTANT — why `indigo` is teal
 * ─────────────────────────────────────────────────────────────────────────
 * The accent moved from indigo to a deep teal. Roughly 2,775 hardcoded
 * `*-indigo-*` utilities are spread across 316 blade files, several of them
 * inside Livewire templates where a blind find-and-replace risks touching
 * wire:model / wire:key lines and breaking bindings.
 *
 * So instead of rewriting every call site, `indigo` is REMAPPED onto the
 * brand scale below. `bg-indigo-600` and `bg-brand-600` now render the same
 * colour. That makes the accent a one-file swap: edit `brand` and every
 * surface in the app follows.
 *
 * Write NEW markup with `brand-*`. When you touch an old file, migrate its
 * `indigo-*` to `brand-*` as you go. When the last one is gone, delete the
 * alias.
 * ─────────────────────────────────────────────────────────────────────────
 */

// Deep teal. Chosen because it is the only accent family that does not
// collide with the semantic colours this app leans on heavily: green reads
// "healthy margin", amber "attention", red "over budget". An accent that
// shares a hue with any of those makes chrome look like data.
//
// Contrast, white text on a filled button (WCAG AA needs 4.5:1 for body):
//   brand-600  5.42:1  ✓ default fill
//   brand-700  7.43:1  ✓ hover / active fill
//   brand-500  3.16:1  ✗ large text only, never a body-size filled button
// On the gray-900 sidebar, brand-400 sits at 7.78:1 ✓
const brand = {
    50:  '#eefbf9',
    100: '#d5f5f1',
    200: '#aeeae4',
    300: '#79d8d1',
    400: '#43bdb8',
    500: '#22a19d',
    600: '#0b7677',
    700: '#0d5f61',
    800: '#104d4f',
    900: '#123f42',
    950: '#042628',
};

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                brand,
                // Compatibility alias. See the note at the top of this file.
                indigo: brand,

                // Semantic status. Named so intent is readable at the call
                // site: `text-danger-600` says why, `text-red-600` says what.
                // Values match Tailwind's red / amber / emerald / sky so the
                // existing hardcoded utilities stay visually identical.
                success: {
                    50: '#ecfdf5', 100: '#d1fae5', 200: '#a7f3d0', 300: '#6ee7b7',
                    400: '#34d399', 500: '#10b981', 600: '#059669', 700: '#047857',
                    800: '#065f46', 900: '#064e3b', 950: '#022c22',
                },
                warning: {
                    50: '#fffbeb', 100: '#fef3c7', 200: '#fde68a', 300: '#fcd34d',
                    400: '#fbbf24', 500: '#f59e0b', 600: '#d97706', 700: '#b45309',
                    800: '#92400e', 900: '#78350f', 950: '#451a03',
                },
                danger: {
                    50: '#fef2f2', 100: '#fee2e2', 200: '#fecaca', 300: '#fca5a5',
                    400: '#f87171', 500: '#ef4444', 600: '#dc2626', 700: '#b91c1c',
                    800: '#991b1b', 900: '#7f1d1d', 950: '#450a0a',
                },
                info: {
                    50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd', 300: '#7dd3fc',
                    400: '#38bdf8', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1',
                    800: '#075985', 900: '#0c4a6e', 950: '#082f49',
                },

                /**
                 * Categorical chart series, in fixed order. Assign 1 then 2;
                 * never cycle, never pick by rank — a filter that drops a series
                 * must not repaint the survivors.
                 *
                 * Validated rather than eyeballed. Against a white chart surface:
                 *   lightness band     PASS
                 *   chroma floor       PASS  (brand-600 FAILS this at 0.085 and
                 *                             reads gray as a mark, which is why
                 *                             the teal here is the 500 step)
                 *   CVD separation     PASS  ΔE 21.5 deutan · 16.8 tritan
                 *   normal-vision      PASS  ΔE 30.4
                 *   contrast vs white  PASS  both >= 3:1
                 *
                 * Deliberately NOT the status colours. The trend chart used to
                 * paint Purchases in danger-400, which says "this number is an
                 * error" about a routine operating figure. Status hues stay
                 * reserved for status.
                 */
                chart: {
                    1: '#22a19d',
                    2: '#7c3aed',
                },

                /**
                 * Workspace identity.
                 *
                 * Central Kitchen is a different place to stand than an outlet
                 * — different stock, different orders, different screens — and
                 * the sidebar is the only thing on screen that says which one
                 * you are in. That signal is worth a colour.
                 *
                 * It was `purple-600` written straight into the kitchen
                 * layout's markup, off the palette entirely, with the light
                 * nav theme carrying its own hand-mixed remaps for
                 * purple-900/40, purple-300 and purple-200. A scale, so the
                 * accent can be picked per surface instead of per guess.
                 *
                 * 600 is the fill: white on it is 5.70:1, up from purple-600's
                 * 5.38:1. 300 is the on-dark text step at 9.61:1 on gray-900,
                 * and 700 is the on-light one at 7.10:1 on white.
                 *
                 * Deliberately NOT a status hue and NOT the brand teal: this
                 * says WHERE you are, which is a third kind of meaning from
                 * "what state is this in" and "whose product is this".
                 */
                workspace: {
                    50:  '#f5f3ff', 100: '#ede9fe', 200: '#ddd6fe', 300: '#c4b5fd',
                    400: '#a78bfa', 500: '#8b5cf6', 600: '#7c3aed', 700: '#6d28d9',
                    800: '#5b21b6', 900: '#4c1d95', 950: '#2e1065',
                },
            },

            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                // Same family, used via `.font-display` so display type gets
                // its own tracking without pulling in a second webfont.
                display: ['Figtree', ...defaultTheme.fontFamily.sans],
                mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', ...defaultTheme.fontFamily.mono],
            },

            /**
             * Shape scale. ONE system, applied everywhere:
             *   interactive small (buttons, inputs, badges) → rounded-control
             *   containers (cards, panels, modals)          → rounded-surface
             *   full-bleed / feature panels                 → rounded-panel
             *   avatars, pills, icon buttons                → rounded-full
             * Do not reach for raw rounded-lg / rounded-2xl in new markup.
             */
            borderRadius: {
                control: '0.625rem',
                surface: '0.875rem',
                panel:   '1.25rem',
            },

            /**
             * Shadows are tinted toward the page hue rather than pure black,
             * which is what makes stock Tailwind elevation look grey and dead
             * on a coloured surface.
             */
            boxShadow: {
                'e1': '0 1px 2px 0 rgb(15 42 45 / 0.05)',
                'e2': '0 2px 8px -2px rgb(15 42 45 / 0.08), 0 1px 2px 0 rgb(15 42 45 / 0.04)',
                'e3': '0 8px 24px -6px rgb(15 42 45 / 0.10), 0 2px 6px -2px rgb(15 42 45 / 0.05)',
                'e4': '0 20px 48px -12px rgb(15 42 45 / 0.16), 0 4px 12px -4px rgb(15 42 45 / 0.06)',
                'brand': '0 8px 24px -8px rgb(11 118 119 / 0.45)',
                // Filled controls. box-shadow is one property, so the inner
                // highlight has to ship in the SAME token as the drop shadow —
                // two utilities would just overwrite each other.
                'btn': 'inset 0 1px 0 0 rgb(255 255 255 / 0.14), 0 2px 8px -2px rgb(15 42 45 / 0.18)',
                'btn-hover': 'inset 0 1px 0 0 rgb(255 255 255 / 0.14), 0 4px 14px -3px rgb(15 42 45 / 0.22)',
            },

            /**
             * Documented z-index scale. Anything layered picks from here so
             * we stop guessing between z-10 and z-50.
             */
            zIndex: {
                'sticky':  '20',
                'drawer':  '40',
                'overlay': '50',
                'popover': '60',
                'toast':   '70',
                'grain':   '80',
            },

            maxWidth: {
                prose: '65ch',
            },

            transitionTimingFunction: {
                // Fast out, gentle settle. Used for every entrance in the app.
                'out-expo': 'cubic-bezier(0.16, 1, 0.3, 1)',
            },

            keyframes: {
                'reveal-up': {
                    from: { opacity: '0', transform: 'translateY(12px)' },
                    to:   { opacity: '1', transform: 'translateY(0)' },
                },
                'shimmer': {
                    '100%': { transform: 'translateX(100%)' },
                },
                /* Travels 250% because the band is 40% wide: it has to clear
                   the right edge completely before restarting on the left. */
                'progress-slide': {
                    from: { transform: 'translateX(-100%)' },
                    to:   { transform: 'translateX(250%)' },
                },
                'marquee': {
                    from: { transform: 'translateX(0)' },
                    to:   { transform: 'translateX(-50%)' },
                },
            },

            animation: {
                'reveal-up': 'reveal-up 0.5s cubic-bezier(0.16, 1, 0.3, 1) both',
                'shimmer': 'shimmer 1.8s infinite',
                'progress-slide': 'progress-slide 1.6s cubic-bezier(0.45, 0, 0.55, 1) infinite',
                'marquee': 'marquee 42s linear infinite',
            },
        },
    },

    plugins: [forms, typography],
};
