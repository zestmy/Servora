/**
 * "Last seen" for the Staff Portal — see App\Services\Hr\PresenceHeartbeat.
 *
 * THIS IS THE MOST A WEB APP CAN DO, and it is worth being precise about the
 * ceiling because the feature's name invites people to expect more. There is
 * no background geolocation in a browser: the Geolocation API is not exposed
 * to service workers anywhere, iOS suspends an installed PWA the moment it is
 * backgrounded or the screen locks, and Chrome's periodic background sync is
 * throttled to hours and could not reach geolocation even if it fired. So
 * every ping below happens while somebody is LOOKING AT THE APP. Pocket the
 * phone and the pings stop. Nothing here can change that, and anything that
 * claimed to would be lying.
 *
 * IT NEVER PROMPTS. The single most important line in this file is the
 * permission check in `mayLocate()`. getCurrentPosition() shows the browser's
 * permission dialog when the state is 'prompt', and firing that on the home
 * screen — with no visible reason, seconds after somebody opened the app to
 * look at their payslip — is how a workforce learns to tap Deny once and mean
 * it forever. The ask belongs at a punch, where the screen has already
 * explained why location is wanted. This rides along afterwards or not at all.
 */

/** Never ping more often than this, per device. */
const MIN_INTERVAL_MS = 60_000;

/** While somebody sits on the app with it open. */
const REFRESH_MS = 5 * 60_000;

/** Where the last ping's timestamp lives, so a page reload does not reset it. */
const THROTTLE_KEY = 'servora.heartbeat.at';

/**
 * Set by the punch flow the first time a fix actually comes back — see
 * currentPosition() in clock.js.
 *
 * The fallback for browsers whose Permissions API cannot be asked about
 * geolocation. Safari only learned that answer recently and a good share of
 * the staff phones here are older iPhones; without this they would contribute
 * a timestamp and never a location. Knowing that a fix succeeded on this
 * device before is weaker evidence than the permission state, but it is
 * evidence of the same thing, and it is only ever used to decide whether
 * asking is safe — never to claim a location we do not have.
 */
export const GEO_GRANTED_KEY = 'servora.geo.granted';

const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content || '';

let timer = null;

function store(key, value) {
    // Private mode and locked-down webviews throw on write. A device that
    // cannot remember its throttle still gets the server's, so failing
    // silently here is correct.
    try {
        window.localStorage.setItem(key, value);
    } catch { /* nothing to do */ }
}

function read(key) {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function throttled() {
    const last = Number.parseInt(read(THROTTLE_KEY) || '0', 10);

    // A clock moved backwards (or a value from another device restored out of
    // a backup) would otherwise park the throttle in the future forever.
    if (! Number.isFinite(last) || last > Date.now()) return false;

    return Date.now() - last < MIN_INTERVAL_MS;
}

/**
 * Whether a fix may be asked for WITHOUT putting a dialog on screen.
 *
 * Three ways to be sure, in descending order of how much they prove, and a
 * default of "no" — the cost of a wrong yes is a permission prompt at the
 * worst moment, and the cost of a wrong no is a row that says the time but
 * not the place.
 */
async function mayLocate() {
    if (! navigator.geolocation) return false;

    if (navigator.permissions?.query) {
        try {
            const status = await navigator.permissions.query({ name: 'geolocation' });

            // 'prompt' is a NO. That is the whole point of this function.
            return status.state === 'granted';
        } catch {
            // Fall through: some browsers reject the geolocation name outright.
        }
    }

    return read(GEO_GRANTED_KEY) === '1';
}

/**
 * A cheap fix, or null. Never rejects.
 *
 * enableHighAccuracy is OFF and a cached fix up to two minutes old is
 * accepted, both deliberately opposite to the punch. A punch is measured
 * against a geofence and is worth spinning up the GPS for; this is a line on
 * a list, it fires several times a day, and warming the GPS for it would show
 * up on a battery somebody needs to last a double shift. A coarse fix that
 * turns out too coarse is dropped server-side rather than shown.
 */
function coarsePosition({ timeout = 8000 } = {}) {
    return new Promise((resolve) => {
        navigator.geolocation.getCurrentPosition(
            (position) => resolve({
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy: position.coords.accuracy,
            }),
            () => resolve(null),
            { enableHighAccuracy: false, timeout, maximumAge: 120_000 },
        );
    });
}

async function ping() {
    const url = meta('staff-heartbeat-url');

    if (! url || document.visibilityState !== 'visible' || throttled()) return;

    // Claim the window BEFORE the await. Two listeners can fire in the same
    // tick — a visibilitychange and a livewire:navigated arrive together when
    // an app is reopened onto a new screen — and a fix takes seconds, which is
    // long enough for both to sail past a check placed after it.
    store(THROTTLE_KEY, String(Date.now()));

    let body = {};

    if (meta('staff-heartbeat-location') === '1' && await mayLocate()) {
        body = await coarsePosition() || {};
    }

    try {
        await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': meta('csrf-token'),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
            // The answer is 204 and there is nothing to read. keepalive lets
            // the request outlive a page that is being navigated away from.
            keepalive: true,
        });
    } catch {
        /*
         * EVERY failure is ignored, including a 419 from a CSRF token that
         * expired under a backgrounded app. A missed heartbeat means one row
         * says "20 minutes ago" instead of "just now"; a retry loop on a phone
         * with no signal at the back of a cold room means a flat battery. The
         * first is the better failure by a distance.
         */
    }
}

export function startHeartbeat() {
    if (timer) return;

    ping();

    // Reopening the app is the single most valuable moment to ping: it is the
    // one that turns "last seen 6 hours ago" into something current.
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') ping();
    });

    // Livewire swaps the DOM without a page load, so moving between tabs in
    // the app fires nothing else here.
    document.addEventListener('livewire:navigated', ping);

    // For a phone left open on the roster. Throttled like everything else, and
    // ping() refuses outright while the page is hidden, so a backgrounded app
    // costs a no-op every five minutes rather than a request.
    timer = setInterval(ping, REFRESH_MS);
}
