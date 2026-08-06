import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // clock.js is its own entry, loaded only by the staff clock-in
            // shell. It pulls in the face recognition library, which is far
            // too large to sit in the bundle every other screen downloads.
            // kiosk.js is a third entry rather than a branch inside clock.js:
            // the kiosk is a different app on different hardware, and loading
            // the staff shell's screen controller on a wall-mounted tablet
            // would have it hunting for elements that are never there.
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/clock.js',
                'resources/js/kiosk.js',
            ],
            refresh: true,
        }),
    ],
});
