import globals from 'globals';

/**
 * One rule, and it is here because of a specific bug.
 *
 * The front/back camera switch shipped referencing FACING_USER,
 * FACING_ENVIRONMENT and FACING_KEY — three constants that were used in nine
 * places and declared in none. Every camera start threw ReferenceError before
 * getUserMedia was reached, so the clock-in preview never opened on any
 * device, and it read as a camera problem rather than a typo. It survived
 * four rounds of fixes aimed at the start sequence, because the start
 * sequence was never the part that was broken.
 *
 * Nothing in the pipeline could have caught it. Vite bundles an undeclared
 * identifier happily — it cannot know the page will not provide it as a
 * global — and the failure only appears at runtime, on a phone, with no
 * console attached.
 *
 * So the rule set is deliberately just `no-undef`, over the browser modules
 * only. Every violation it reports is a guaranteed runtime crash rather than
 * a matter of taste, which is what makes it safe to fail the build on: there
 * is no judgement call to argue with and nothing to bikeshed. Style rules
 * would change that, and are left out on purpose.
 */
export default [
    {
        files: ['resources/js/**/*.js'],
        languageOptions: {
            ecmaVersion: 2023,
            sourceType: 'module',
            globals: {
                ...globals.browser,
                // Injected by the app shell rather than imported.
                Livewire: 'readonly',
                Alpine: 'readonly',
                axios: 'readonly',
            },
        },
        rules: {
            'no-undef': 'error',
        },
    },
];
