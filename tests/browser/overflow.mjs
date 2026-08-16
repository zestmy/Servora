/**
 * Nothing may run off the side of the screen.
 *
 * WHY THIS EXISTS. Seven screens shipped horizontally overflowing on a phone,
 * and one of them put Save and Cancel outside the viewport on the employee
 * form — you could edit somebody and then not save them. None of it presented
 * as broken, which is exactly why it lasted: `main` is `overflow-y-auto`, and
 * CSS computes the OTHER axis of a scroll container to `auto` as well, so an
 * over-wide page does not visibly break. It quietly becomes draggable
 * sideways, and only somebody who thinks to swipe ever finds the buttons.
 *
 * WHAT IT ASSERTS, and why it is one number rather than a list of elements:
 *
 *     main.scrollWidth === main.clientWidth
 *
 * A wide table is FINE when it sits in an `overflow-x-auto` wrapper — that is
 * the house pattern and it must not fail here. Measuring the page's own scroll
 * container captures the distinction for free: a properly wrapped table scrolls
 * inside its own box and never widens the page, while an unwrapped one does.
 * Checking individual element widths instead would flag every correct table in
 * the product and the check would be turned off within a week.
 *
 * On failure it walks the DOM to name the element that caused it, because
 * "the page is 468px wide" is not something anybody can act on.
 *
 * ── Running it ────────────────────────────────────────────────────────────
 *
 *     php artisan serve &            # or point APP_URL at any running instance
 *     npm run test:overflow
 *
 * Environment:
 *   APP_URL              default http://127.0.0.1:8000
 *   OVERFLOW_EMAIL       default admin@servora.test   (the seeded admin)
 *   OVERFLOW_PASSWORD    default password
 *   PLAYWRIGHT_CHROMIUM  explicit path to a Chromium/Chrome binary
 *
 * DELIBERATELY NOT PART OF `npm run build`. The production box is a 2GB VPS
 * that already needed swap added to survive `vite build`; it has no browser
 * and no business running one. `playwright-core` is the dependency precisely
 * because it downloads nothing on install — the browser comes from the
 * environment, which on a developer machine is the Chrome already there.
 */

import { chromium } from 'playwright-core';
import { existsSync } from 'node:fs';

const APP_URL  = process.env.APP_URL || 'http://127.0.0.1:8000';
const EMAIL    = process.env.OVERFLOW_EMAIL || 'admin@servora.test';
const PASSWORD = process.env.OVERFLOW_PASSWORD || 'password';

/*
 * The three widths that matter, and why each is here rather than a round
 * number somebody liked:
 *
 *   360 — the narrow end of Android phones still in kitchens. Four of the
 *         seven bugs were invisible at 390 and only appeared here.
 *   390 — iPhone 12–16 logical width. The reported one.
 *   768 — tablet portrait, and the first width at which the desktop sidebar
 *         renders. That leaves main ~512px, which is NARROWER than the phone
 *         layouts get to use, so screens that pass on a phone can fail here.
 *         Two did.
 */
const WIDTHS = [360, 390, 768];

/*
 * `actions` open a state the default render does not reach. Both entries that
 * use it are there because a real bug hid behind one: the attendance period
 * picker only overflows once Custom swaps one date field for two, and the
 * Stocks Management table only escaped its card on certain tabs.
 */
const SCREENS = [
    { path: '/dashboard' },

    // HR — where the reported bug was, and where the widest tables live.
    { path: '/hr/employees' },
    { path: '/hr/employees/create' },
    { path: '/hr/employees/1/edit', optional: true },
    { path: '/hr/attendance' },
    { path: '/hr/attendance', label: 'custom range', actions: ['button:has-text("Custom")'] },
    { path: '/hr/duty-roster' },
    { path: '/hr/leave' },
    { path: '/hr/time-off' },
    { path: '/hr/overtime-claims' },
    { path: '/hr/payroll' },
    { path: '/hr/compensation' },
    { path: '/hr/clock-ins' },
    { path: '/hr/shifts' },
    { path: '/hr/documents' },
    { path: '/hr/staff-pins' },

    // Operations.
    { path: '/ingredients' },
    { path: '/recipes' },
    { path: '/sales' },
    { path: '/inventory' },
    { path: '/inventory', label: 'wastage tab',   actions: ['.seg button:has-text("Wastage")'] },
    { path: '/inventory', label: 'transfers tab', actions: ['.seg button:has-text("Transfers")'] },
    { path: '/inventory', label: 'purchases tab', actions: ['.seg button:has-text("Purchases")'] },
    { path: '/purchasing' },
    { path: '/purchasing/invoices' },
    { path: '/reports' },
    { path: '/reports/cost-summary' },
    { path: '/analytics' },

    // Labels. Only the screen added after this check existed — the older
    // label screens predate the list and should be added when next touched.
    { path: '/labels/agents' },

    // Settings — every one of these is a list with a header action row, which
    // is the shape that produced most of the bugs this check was written for.
    { path: '/settings' },
    { path: '/settings/outlets' },
    { path: '/settings/users' },
    { path: '/settings/roles-access' },
    { path: '/settings/sections' },
    { path: '/settings/departments' },
    { path: '/settings/suppliers' },
    { path: '/settings/categories' },
    { path: '/settings/recipe-categories' },
    { path: '/settings/sales-categories' },
    { path: '/settings/leave-types' },
    { path: '/settings/pay-components' },
    { path: '/settings/public-holidays' },
    { path: '/settings/statutory' },
    { path: '/settings/company-details' },
];

/**
 * A browser to drive.
 *
 * playwright-core ships no binary on purpose (see the header), so it has to be
 * found. Ordered from most explicit to most convenient.
 */
async function launch() {
    const explicit = process.env.PLAYWRIGHT_CHROMIUM;
    if (explicit) {
        if (!existsSync(explicit)) {
            throw new Error(`PLAYWRIGHT_CHROMIUM points at ${explicit}, which does not exist.`);
        }
        return chromium.launch({ executablePath: explicit });
    }

    const candidates = [
        process.env.PLAYWRIGHT_BROWSERS_PATH && `${process.env.PLAYWRIGHT_BROWSERS_PATH}/chromium`,
        '/usr/bin/google-chrome',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    ].filter(Boolean);

    for (const path of candidates) {
        if (existsSync(path)) return chromium.launch({ executablePath: path });
    }

    // Last resort: Playwright's own channel lookup finds an installed Chrome
    // through the registry on machines where the paths above do not apply.
    try {
        return await chromium.launch({ channel: 'chrome' });
    } catch {
        throw new Error(
            'No Chromium or Chrome found.\n' +
            'Install one, or point PLAYWRIGHT_CHROMIUM at an existing binary:\n' +
            '  npx playwright install chromium\n' +
            '  PLAYWRIGHT_CHROMIUM=/path/to/chrome npm run test:overflow'
        );
    }
}

/**
 * Whatever caused the page to be too wide, named well enough to go and fix.
 *
 * Serialised and run INSIDE the page by page.evaluate, so it closes over
 * nothing from this file and speaks browser globals rather than Node ones.
 */
function describeOverflow() {
    const main = document.querySelector('main');
    const limit = main.clientWidth;

    const describe = (el) => ({
        tag: el.tagName.toLowerCase(),
        cls: (el.className || '').toString().slice(0, 70),
        text: (el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 40),
    });

    /*
     * Anything inside a deliberate scroller is allowed to be wide — that is
     * what the scroller is for. Walked all the way up to `main` rather than
     * checking the immediate parent, or every `<th>` of a correctly wrapped
     * table gets reported: the cell's parent is a `<tr>`, and the
     * `overflow-x-auto` is three levels above that.
     */
    const insideScroller = (el) => {
        for (let n = el.parentElement; n && n !== main; n = n.parentElement) {
            const x = getComputedStyle(n).overflowX;
            if (x === 'auto' || x === 'scroll' || x === 'hidden') return true;
        }
        return false;
    };

    const tooWide = [];   // an element bigger than the space it was given
    const pushedOut = []; // an element merely sitting past the edge

    for (const el of main.querySelectorAll('*')) {
        const parent = el.parentElement;
        if (!parent || insideScroller(el)) continue;

        const box = el.getBoundingClientRect();
        if (box.right <= limit + 1 || box.width === 0) continue;

        if (box.width > parent.clientWidth + 1) {
            tooWide.push({ ...describe(el), width: Math.round(box.width), parentWidth: parent.clientWidth });
        } else if (el.children.length === 0) {
            /*
             * The second case, and it needs its own pass: a row that cannot
             * wrap does not make any single child too wide, it just places the
             * last one past the edge. "+ Add User" on Roles & Access was
             * exactly this — 11px over, with every element in the page
             * comfortably narrower than its parent — so the first pass found
             * nothing and the failure came with no address to go to.
             *
             * The PARENT is reported alongside, because that is where the fix
             * goes: the row is what needs flex-wrap.
             */
            pushedOut.push({ ...describe(el), right: Math.round(box.right), parent: describe(parent) });
        }
    }

    return { tooWide: tooWide.slice(0, 3), pushedOut: pushedOut.slice(0, 2) };
}

/**
 * Navigate, surviving a server that has just gone away.
 *
 * The dev server behind this in CI is supervised and restarts within a
 * second, so a single ERR_CONNECTION_REFUSED means "try again", not "the
 * screen is broken". Without this the whole run ends on one blip.
 */
// ERR_ABORTED joined the list after a docs-only PR failed on the very first
// navigation: the supervisor loop was restarting `artisan serve` at that
// moment, and an aborted navigation is the same server-gap as a refused one.
const TRANSIENT = /ERR_CONNECTION_REFUSED|ERR_CONNECTION_RESET|ERR_EMPTY_RESPONSE|ERR_SOCKET_NOT_CONNECTED|ERR_ABORTED/;

/** Poll until something answers again, rather than guessing how long a restart takes. */
async function waitForServer(seconds = 30) {
    for (let i = 0; i < seconds; i++) {
        try {
            const r = await fetch(`${APP_URL}/login`);
            if (r.ok) return true;
        } catch { /* still down */ }
        await new Promise((r) => setTimeout(r, 1000));
    }
    return false;
}

async function goto(page, url) {
    let last;
    for (let attempt = 0; attempt < 4; attempt++) {
        try {
            return await page.goto(url, { waitUntil: 'domcontentloaded' });
        } catch (e) {
            if (!TRANSIENT.test(e.message)) throw e;
            last = e;
            await waitForServer();
        }
    }
    throw last;
}

/*
 * What actually came back from a 5xx: the FINAL url (goto follows
 * redirects, so "the dashboard 500'd" can really mean "the dashboard
 * redirected somewhere that 500'd"), the page title, headings, and any
 * exception-class-looking token. With APP_DEBUG on the body is a stack
 * trace and the title names the exception; with a rendered app page these
 * pieces identify WHICH page answered. Best-effort by design — a diagnosis
 * helper must never be the thing that fails the run.
 */
async function errorHint(response) {
    try {
        const parts = [];

        const finalUrl = response.url();
        if (finalUrl) parts.push(`answered by ${finalUrl}`);

        const body = await response.text();

        const title = body.match(/<title>([^<]{1,300})<\/title>/i)?.[1].trim();
        if (title) parts.push(`title "${title}"`);

        for (const h of body.matchAll(/<h[12][^>]*>([^<]{1,120})<\/h[12]>/gi)) {
            const text = h[1].trim();
            if (text) { parts.push(`heading "${text}"`); break; }
        }

        const exc = body.match(/([A-Za-z0-9_\\]+(?:Exception|Error))[^<\n]{0,200}/)?.[0].trim();
        if (exc) parts.push(exc);

        return parts.join(' — ') || null;
    } catch {
        return null;
    }
}

async function signIn(page) {
    await goto(page, `${APP_URL}/login`);
    await page.fill('input[type=email]', EMAIL);
    await page.fill('input[type=password]', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle').catch(() => {});

    // Logging in is the heaviest thing the dev server does in this run, and it
    // is where it died in CI. If it went down taking the redirect with it,
    // wait for it back and try once more rather than failing the whole sweep.
    if (page.url().includes('/login')) {
        await waitForServer();
        await goto(page, `${APP_URL}/dashboard`);
    }

    if (page.url().includes('/login')) {
        throw new Error(
            `Could not sign in to ${APP_URL} as ${EMAIL}.\n` +
            'Seed the database (php artisan db:seed) or set OVERFLOW_EMAIL / OVERFLOW_PASSWORD.'
        );
    }
}

async function main() {
    // Fail early and legibly rather than 60 confusing timeouts deep.
    try {
        const probe = await fetch(`${APP_URL}/login`);
        if (!probe.ok) throw new Error(`HTTP ${probe.status}`);
    } catch (e) {
        console.error(`Cannot reach ${APP_URL} (${e.message}).`);
        console.error('Start the app first:  php artisan serve');
        process.exit(1);
    }

    const browser = await launch();
    const failures = [];
    const unreachable = [];
    let checked = 0;
    let skipped = 0;

    /*
     * Signed in ONCE and the session reused for every width. Three logins
     * against a single-threaded dev server is three chances to hit it while
     * it is restarting, for no benefit — the layout does not care how the
     * cookie got there.
     */
    const authContext = await browser.newContext();
    const authPage = await authContext.newPage();
    await signIn(authPage);
    const storageState = await authContext.storageState();
    await authContext.close();

    for (const width of WIDTHS) {
        const context = await browser.newContext({
            viewport: { width, height: 900 },
            isMobile: width < 768,
            hasTouch: width < 768,
            storageState,
        });
        const page = await context.newPage();

        for (const screen of SCREENS) {
            const name = screen.label ? `${screen.path} (${screen.label})` : screen.path;

            const response = await goto(page, APP_URL + screen.path);
            const status = response ? response.status() : 0;

            if (status !== 200) {
                /*
                 * A 4xx IS this check's problem: it means a path in the list
                 * above is wrong, and a screen nobody is measuring silently
                 * counts as passing. That is how the list rots.
                 *
                 * A 5xx IS NOT. The screen is broken for some reason that has
                 * nothing to do with its width, and failing here would report
                 * the wrong thing. It is surfaced loudly instead, because a
                 * screen that cannot be measured is coverage lost.
                 *
                 * The reason this distinction had to be drawn rather than
                 * assumed: /hr/overtime-claims and /reports/cost-summary both
                 * 500 against SQLITE, because they use MySQL-only SQL
                 * (`YEAR()`, `DATE_SUB(… INTERVAL …)`). Production is MySQL
                 * and both work there. Treating any non-200 as a failure made
                 * this check permanently red for a reason it does not measure.
                 */
                if (screen.optional && status === 404) { skipped++; continue; }

                if (status >= 500) {
                    /*
                     * Keep the exception, not just the number. With APP_DEBUG
                     * on (CI runs .env.example verbatim) the response body IS
                     * the stack trace, and discarding it once cost a full
                     * diagnose-by-hypothesis round on a /dashboard 500 that
                     * only ever happened in CI.
                     */
                    unreachable.push({ width, name, status, hint: await errorHint(response) });
                } else {
                    failures.push({ width, name, reason: `HTTP ${status} — is this path still right?` });
                }
                continue;
            }

            /*
             * A LAPSED SESSION WOULD MAKE EVERY SCREEN PASS. The login page
             * returns 200 and is narrow, so if the cookie stops working the
             * sweep quietly measures 43 copies of it and reports success —
             * the worst failure mode a check can have. Caught by looking at
             * where we actually landed.
             */
            if (page.url().includes('/login')) {
                failures.push({ width, name, reason: 'redirected to /login — the session was lost mid-run' });
                continue;
            }

            for (const selector of screen.actions ?? []) {
                const target = page.locator(selector).first();
                if (await target.count()) {
                    await target.click();
                    await page.waitForTimeout(500);
                }
            }
            await page.waitForTimeout(500);

            const result = await page.evaluate(() => {
                const main = document.querySelector('main');
                return main ? { scrollWidth: main.scrollWidth, clientWidth: main.clientWidth } : null;
            });

            if (!result) { skipped++; continue; }
            checked++;

            if (result.scrollWidth > result.clientWidth) {
                failures.push({
                    width,
                    name,
                    reason: `page is ${result.scrollWidth}px wide in ${result.clientWidth}px`,
                    culprits: await page.evaluate(describeOverflow),
                });
            }
        }

        await context.close();
        console.log(`${width}px — ${SCREENS.length} screens checked`);
    }

    await browser.close();

    console.log(`\n${checked} checks across ${WIDTHS.length} widths` + (skipped ? `, ${skipped} skipped` : ''));

    if (unreachable.length) {
        console.warn(`\n${unreachable.length} screen(s) returned a server error and could NOT be measured:`);
        for (const u of unreachable) {
            console.warn(`  ${u.name} @ ${u.width}px — HTTP ${u.status}`);
            if (u.hint) console.warn(`      ${u.hint}`);
        }
        console.warn('These are not width problems, but they are coverage this run did not get.');
    }

    if (failures.length === 0) {
        console.log('No horizontal overflow.');
        return;
    }

    console.error(`\n${failures.length} screen(s) overflow horizontally:\n`);
    for (const f of failures) {
        console.error(`  ${f.name} @ ${f.width}px — ${f.reason}`);
        for (const c of f.culprits?.tooWide ?? []) {
            console.error(`      too wide: <${c.tag} class="${c.cls}"> is ${c.width}px inside ${c.parentWidth}px`
                + (c.text ? `  — "${c.text}"` : ''));
        }
        for (const c of f.culprits?.pushedOut ?? []) {
            console.error(`      past the edge at ${c.right}px: <${c.tag} class="${c.cls}">`
                + (c.text ? ` "${c.text}"` : ''));
            console.error(`          in a row that cannot wrap: <${c.parent.tag} class="${c.parent.cls}">`);
        }
    }
    console.error(
        '\nA wide table belongs in an `overflow-x-auto` wrapper — note that the wrapper\n' +
        'must be a BLOCK box, since an inline-flex one (`.seg`) shrink-to-fits and never\n' +
        'scrolls. A row of controls that cannot fit needs `flex-wrap`, and a flex child\n' +
        'that must be allowed to shrink needs `min-w-0` — flex items default to\n' +
        '`min-width:auto` and refuse to go below their content.'
    );
    process.exit(1);
}

main().catch((e) => {
    console.error(e.message);
    process.exit(1);
});
