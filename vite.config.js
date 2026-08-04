import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // clock.js is its own entry, loaded only by the staff clock-in
            // shell. It pulls in the face recognition library, which is far
            // too large to sit in the bundle every other screen downloads.
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/clock.js'],
            refresh: true,
        }),
    ],
});
