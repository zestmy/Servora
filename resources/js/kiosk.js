/**
 * The outlet kiosk: a tablet on a counter that recognises whoever walks up.
 *
 * Its own entry point, sharing clock.js's camera rather than copying it. That
 * class carries a long list of hard-won iOS fixes — a getUserMedia that never
 * settles, a play() Safari rejects, a stream orphaned when the DOM is rebuilt
 * — and a second implementation would rediscover every one of them.
 *
 * WHAT THIS FILE IS TRUSTED WITH. It turns camera frames into 128-float
 * descriptors and posts them. It never learns who anybody is until the server
 * says so, it never receives an employee id it could assert, and the token it
 * carries between the two calls is encrypted and means nothing to it. All the
 * page can do is ask "who is this?" and then say "yes, that one" — which is
 * exactly as much authority as a screen on a public counter should have.
 *
 * THE FLOW, and why it is not hands-free.
 *
 *   Fully automatic sounds better and is worse. A screen in a doorway sees
 *   the same person walk past three times an hour, and every sighting after
 *   the first is a clock-OUT nobody asked for. Worse, the person is told
 *   nothing: they find out at payroll.
 *
 *   So: recognise in the background, then show one big button with a name on
 *   it. One tap, under two seconds, and the person has seen what is about to
 *   be recorded about them. At a shift change of twelve people that is a
 *   thirty-second queue — which is the other reason there is no blink-and-turn
 *   ceremony here. That belongs to enrolment, which happens once.
 */

import { ClockCamera, loadModels } from './clock.js';
import { looksLikeFace } from './face-geometry.js';

/* ── Tuning ───────────────────────────────────────────────────────────── */

/** How often the cheap detector runs while waiting for somebody. */
const DETECT_INTERVAL_MS = 300;

/**
 * How long a face must stay in frame before it is worth describing.
 *
 * This is the gate that keeps the expensive pipeline off somebody walking
 * past. Long enough that a passer-by never triggers it, short enough that
 * stopping in front of the screen does not feel like waiting.
 */
const STABLE_MS = 800;

/** A face smaller than this is somebody across the room, not at the counter. */
const MIN_FACE_WIDTH = 0.14;

/** Detector input size — the same trade clock.js makes, for the same reason. */
const DETECTOR_INPUT_SIZE = 320;
const DETECTOR_SCORE = 0.6;

/** After an unrecognised face, wait before trying that again. */
const RETRY_AFTER_MS = 3500;

/** A confirm card nobody taps goes away, so the next person gets a clean screen. */
const CONFIRM_TIMEOUT_MS = 20000;

/** How long the big green confirmation stays up. */
const RESULT_MS = 5000;

/** A notice — "not recognised", "already clocked in" — is briefer. */
const NOTICE_MS = 4000;

/**
 * How long the PIN offer stays on screen after a failed recognition.
 *
 * Longer than a plain notice, because this one has to survive somebody
 * reading it, deciding to take it, and reaching the screen — but it still
 * goes away, so the next person arrives at a clean camera prompt rather than
 * at the last person's staff list.
 */
const OFFER_MS = 12000;

/** Heartbeat. Matches what the server treats as "recently seen". */
const PING_MS = 60000;

/* ── Element plumbing ─────────────────────────────────────────────────── */

const el = (id) => document.getElementById(id);
const root = () => el('kiosk-root');

const state = {
    camera: null,
    mode: 'boot',        // boot | idle | busy | confirm | pin | result
    stableSince: null,
    pausedUntil: 0,
    token: null,         // opaque identify token, server-minted
    selfie: null,
    pinEmployee: null,
    pin: '',
    // Names the last identification was torn between, held so the offer can
    // float them to the top if somebody takes it.
    shortlist: [],
    wakeLock: null,
    detecting: false,
};

function endpoint(name) {
    return root()?.dataset[name] || '';
}

function deviceToken() {
    return root()?.dataset.token || '';
}

/**
 * Whether a PIN is a way in here at all.
 *
 * The server is what actually enforces this — KioskController::fromPin()
 * refuses outright when the company has switched it off, because these
 * endpoints answer to a device token that is readable in this very page. What
 * the flag does here is stop the screen offering a door that is already shut,
 * which is a courtesy to the person standing at it rather than a control.
 */
function pinAllowed() {
    return root()?.dataset.allowPin === '1';
}

/** Every call to the kiosk API. The token goes in a HEADER, never a cookie. */
async function api(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Kiosk-Token': deviceToken(),
        },
        body: JSON.stringify(body || {}),
    });

    const data = await response.json().catch(() => ({}));

    // 401 means the tablet has been unpaired or the company switched kiosks
    // off. Reloading lands on the pairing screen, which says so — far better
    // than a kiosk that stays up looking alive and silently records nothing.
    if (response.status === 401) {
        window.location.reload();

        throw new Error('unpaired');
    }

    return { ok: response.ok, data };
}

/* ── Screens ──────────────────────────────────────────────────────────── */

const PANELS = ['idle', 'confirm', 'pin', 'result'];

function show(panel) {
    state.mode = panel;

    PANELS.forEach((name) => {
        el(`kiosk-state-${name}`)?.classList.toggle('hidden', name !== panel);
    });
}

function setHint(text) {
    const node = el('kiosk-hint');

    if (node) node.textContent = text;
}

/**
 * A short message on the idle screen, then back to scanning.
 *
 * Deliberately returns to idle on a timer rather than waiting for the person
 * to acknowledge it. Nobody dismisses a dialog on a tablet they are walking
 * away from, and the next person must not arrive at somebody else's error.
 */
let noticeTimer = null;

/**
 * @param {string} text
 * @param {{offerPin?: boolean, ms?: number}} options
 *        offerPin reveals the fallback, for as long as the notice is up.
 */
function notice(text, { offerPin = false, ms = null } = {}) {
    show('idle');
    setHint(text);

    const offer   = el('kiosk-pin-offer');
    const showing = offerPin && pinAllowed();

    offer?.classList.toggle('hidden', ! showing);

    // A plain notice is read in a glance; an offer has to survive somebody
    // deciding to take it, walking a step closer, and pressing it.
    clearTimeout(noticeTimer);
    noticeTimer = setTimeout(() => {
        if (state.mode === 'idle') {
            setHint(defaultHint());
            offer?.classList.add('hidden');
        }
    }, ms ?? (showing ? OFFER_MS : NOTICE_MS));
}

function defaultHint() {
    return 'Look at the camera to clock in';
}

/* ── Detection loop ───────────────────────────────────────────────────── */

/**
 * Watch for somebody standing in front of the screen.
 *
 * Two stages on purpose. The cheap detector runs continuously and only
 * answers "is there a face, is it close enough, has it stopped moving"; the
 * expensive descriptor pipeline runs once, when that is true. Running the full
 * pipeline continuously would heat the tablet all day to answer a question
 * that is "no" for most of it.
 */
async function detectLoop() {
    if (state.detecting) return;

    state.detecting = true;

    // `faceapi`, not `api` — that name belongs to the fetch helper above, and
    // the two live one scope apart.
    let faceapi = null;
    let options = null;

    try {
        faceapi = await loadModels();
        options = new faceapi.TinyFaceDetectorOptions({
            inputSize: DETECTOR_INPUT_SIZE,
            scoreThreshold: DETECTOR_SCORE,
        });
        setStatus('');
    } catch (error) {
        setStatus('Face model could not load. Staff can still use a PIN.');
        state.detecting = false;

        return;
    }

    while (state.detecting) {
        await sleep(DETECT_INTERVAL_MS);

        // Only ever scans on the idle screen. A confirm card up, a PIN
        // half-keyed, or a result showing all mean somebody is mid-interaction
        // and a second identification would talk over them.
        if (state.mode !== 'idle' || Date.now() < state.pausedUntil) {
            state.stableSince = null;
            continue;
        }

        if (! state.camera?.playing) {
            state.stableSince = null;
            continue;
        }

        let found = null;

        try {
            found = await faceapi.detectSingleFace(state.camera.video, options).withFaceLandmarks();
        } catch (error) {
            continue;
        }

        // Both have to agree before this counts as a person: the detector says
        // where a face is, the geometry check says whether the landmarks
        // describe one. tinyFaceDetector alone will happily call a hand a face.
        const real = found ? looksLikeFace(found.landmarks) : { ok: false };

        if (! real.ok || ! closeEnough(found.detection)) {
            state.stableSince = null;
            continue;
        }

        state.stableSince ??= Date.now();

        if (Date.now() - state.stableSince >= STABLE_MS) {
            state.stableSince = null;
            await identify();
        }
    }
}

/** Whether the face is at the counter rather than across the kitchen. */
function closeEnough(detection) {
    const width = state.camera?.video?.videoWidth || 1;

    return (detection.box.width / width) >= MIN_FACE_WIDTH;
}

/* ── Identify ─────────────────────────────────────────────────────────── */

async function identify() {
    show('idle');
    setHint('One moment…');

    let face = null;

    try {
        // The accurate detector, via ClockCamera.capture(). This frame becomes
        // the descriptor that names somebody, and it happens once per person —
        // there is nothing worth spending the speed on.
        face = await state.camera.capture();
    } catch (error) {
        face = null;
    }

    if (! face) {
        state.pausedUntil = Date.now() + RETRY_AFTER_MS;
        notice('Could not read a face. Move a little closer, or use your PIN.');

        return;
    }

    state.selfie = face.selfie;

    let result;

    try {
        result = await api(endpoint('identify'), { descriptor: face.descriptor });
    } catch (error) {
        notice('Connection problem. Try again.');

        return;
    }

    const data = result.data || {};

    if (data.status === 'matched') {
        openConfirm(data);

        return;
    }

    state.pausedUntil = Date.now() + RETRY_AFTER_MS;

    if (data.status === 'ambiguous') {
        /*
         * A close call between two colleagues is never resolved by a tap. The
         * shortlist is a shortcut into the PIN step, not an answer to it — the
         * tap still has to be followed by that person's PIN.
         *
         * Remembered rather than acted on: the offer below opens the panel
         * only if somebody actually presses it.
         */
        state.shortlist = data.shortlist || [];
        notice(data.message || 'Could not tell you apart from a colleague.', { offerPin: true });

        return;
    }

    if (data.status === 'cooldown') {
        // No PIN offer here. They are already clocked in; the fallback would
        // only invite a second punch.
        notice(data.message || 'Already recorded.');

        return;
    }

    /*
     * Not recognised.
     *
     * The offer appears HERE and only here — after a real failure, on a timer,
     * and needing a deliberate press. It is not on the idle screen, and it is
     * not opened for them either: a panel that springs up on every miss would
     * put a list of everybody who works here on a counter screen several times
     * an hour, and would show the PIN route MORE often than the button it
     * replaced, which is the opposite of the point.
     */
    state.shortlist = [];
    notice(data.message || 'Not recognised.', { offerPin: true });
}

/* ── Confirm ──────────────────────────────────────────────────────────── */

let confirmTimer = null;

function openConfirm(data) {
    state.token = data.token || null;

    const name = data.employee?.name || '';

    setText('kiosk-name', firstName(name));
    setText('kiosk-full-name', name);
    setText('kiosk-primary-label', data.next?.label || 'Clock in');

    const breakButton = el('kiosk-break');

    if (breakButton) {
        const hasBreak = Boolean(data.next?.break);
        breakButton.classList.toggle('hidden', ! hasBreak);
        setText('kiosk-break-label', data.next?.break_label || '');
    }

    show('confirm');

    // Nobody taps a card they have walked away from, and the next person must
    // not arrive at somebody else's name.
    clearTimeout(confirmTimer);
    confirmTimer = setTimeout(() => {
        if (state.mode === 'confirm') resetToIdle();
    }, CONFIRM_TIMEOUT_MS);
}

/* ── PIN fallback ─────────────────────────────────────────────────────── */

/**
 * The door for everybody the camera could not name.
 *
 * The cook at 6am with a hairnet and steamed-up glasses, the new hire nobody
 * has enrolled yet, and the two colleagues the model cannot tell apart. The
 * punch it produces carries no face, so the server flags it and a manager sees
 * it. That is the trade, made deliberately.
 *
 * It is reached only from a FAILED recognition — there is no longer a button
 * for it on the idle screen. Offered side by side, a PIN beats a camera every
 * time on familiarity alone, and a kiosk whose staff all clock in by PIN has
 * bought nothing.
 *
 * A company may switch it off entirely, at a cost worth being clear about: the
 * casualty is not really the unenrolled new hire, who is a known and small
 * group, but the enrolled cook whose face simply cannot be read this morning.
 * They have no way in at all and their manager marks the grid by hand.
 *
 * @returns {boolean} whether the fallback was actually opened.
 */
function openPin(shortlist = []) {
    // Nothing to open. The panel is not even in the markup when the fallback
    // is off, so this would otherwise show an empty screen with no way back.
    if (! pinAllowed()) {
        return false;
    }

    state.pinEmployee = null;
    state.pin = '';

    const list = el('kiosk-pin-list');

    if (list) {
        // The shortlist floats to the top when there is one; the full list
        // stays underneath, because the model having an opinion is not a
        // reason to make everybody else scroll past it.
        const ids = shortlist.map((row) => String(row.id));

        list.querySelectorAll('[data-employee]').forEach((node) => {
            node.classList.toggle('order-first', ids.includes(node.dataset.employee));
            node.classList.toggle('ring-2', ids.includes(node.dataset.employee));
        });
    }

    const search = el('kiosk-pin-search');

    if (search) search.value = '';

    filterPinList('');
    paintPinStep();
    show('pin');

    return true;
}

function setPinHint(text) {
    setText('kiosk-pin-hint', text);
}

function filterPinList(term) {
    const needle = term.trim().toLowerCase();

    document.querySelectorAll('#kiosk-pin-list [data-employee]').forEach((node) => {
        const name = (node.dataset.name || '').toLowerCase();
        node.classList.toggle('hidden', needle !== '' && ! name.includes(needle));
    });
}

/** Which half of the fallback is showing: pick a name, or key the PIN. */
function paintPinStep() {
    const picking = ! state.pinEmployee;

    el('kiosk-pin-picker')?.classList.toggle('hidden', ! picking);
    el('kiosk-pin-keypad')?.classList.toggle('hidden', picking);

    setText('kiosk-pin-name', state.pinEmployee?.name || '');

    const dots = el('kiosk-pin-dots');

    if (dots) {
        dots.textContent = '•'.repeat(state.pin.length).padEnd(4, '·');
    }
}

/* ── Punch ────────────────────────────────────────────────────────────── */

async function punch(intent = 'shift') {
    if (state.mode === 'busy') return;

    const body = {
        intent,
        selfie: state.selfie,
    };

    if (state.token) {
        body.token = state.token;
    } else if (state.pinEmployee) {
        body.employee_id = state.pinEmployee.id;
        body.pin = state.pin;
    } else {
        return;
    }

    const previous = state.mode;
    state.mode = 'busy';
    setText('kiosk-primary-label', 'Recording…');

    let result;

    try {
        result = await api(endpoint('punch'), body);
    } catch (error) {
        state.mode = previous;
        notice('Connection problem. Try again.');

        return;
    }

    const data = result.data || {};

    if (! result.ok || data.status !== 'ok') {
        state.mode = previous;

        if (previous === 'pin') {
            state.pin = '';
            paintPinStep();
            setPinHint(data.message || 'Wrong PIN.');
            show('pin');

            return;
        }

        notice(data.message || 'Could not record that. Try again.');

        return;
    }

    showResult(data);
}

function showResult(data) {
    setText('kiosk-result-name', data.name || '');
    setText('kiosk-result-headline', data.headline || 'Recorded');
    setText('kiosk-result-at', data.at || '');
    setText('kiosk-result-note', data.note || '');

    el('kiosk-result-note')?.classList.toggle('hidden', ! data.note);

    // Flagged punches are still recorded and the person is still clocked in.
    // The tint says "a manager will look at this", never "that did not work" —
    // somebody who reads it as a failure will stand there tapping again.
    el('kiosk-result-card')?.classList.toggle('kiosk-result-flagged', Boolean(data.flagged));

    show('result');

    setTimeout(() => {
        if (state.mode === 'result') resetToIdle();
    }, RESULT_MS);
}

function resetToIdle() {
    clearTimeout(confirmTimer);

    state.token = null;
    state.selfie = null;
    state.pinEmployee = null;
    state.pin = '';
    state.shortlist = [];
    state.stableSince = null;

    // The offer belongs to the failure that raised it. Leaving it up would
    // hand the next person a PIN route they never failed their way into.
    el('kiosk-pin-offer')?.classList.add('hidden');
    // A short pause, so the person who just punched is not immediately
    // re-identified as they pick their bag back up.
    state.pausedUntil = Date.now() + RETRY_AFTER_MS;

    show('idle');
    setHint(defaultHint());
}

/* ── Camera and screen lifecycle ──────────────────────────────────────── */

async function startCamera() {
    state.camera ??= new ClockCamera({
        video: el('kiosk-video'),
        canvas: el('kiosk-canvas'),
        facing: 'user',
    });

    state.camera.attach(el('kiosk-video'), el('kiosk-canvas'));

    try {
        await state.camera.start();
        el('kiosk-camera-overlay')?.classList.add('hidden');
        setStatus('');
    } catch (error) {
        el('kiosk-camera-overlay')?.classList.remove('hidden');
        setStatus(cameraProblem(error));

        return false;
    }

    return true;
}

function cameraProblem(error) {
    switch (error?.name) {
        case 'NotAllowedError':
        case 'SecurityError':
            return 'Camera blocked. Allow camera for this site, then tap the preview.';
        case 'NotFoundError':
            return 'No camera on this device. Staff can still use a PIN.';
        case 'NotReadableError':
            return 'Another app is using the camera. Close it, then tap the preview.';
        case 'TimeoutError':
            return 'The camera did not respond. Tap the preview to try again.';
        default:
            return 'Could not start the camera. Tap the preview to try again.';
    }
}

function setStatus(text) {
    const node = el('kiosk-status');

    if (! node) return;

    node.textContent = text || '';
    node.classList.toggle('hidden', ! text);
}

/**
 * Keep the screen awake.
 *
 * A kiosk that has gone to sleep is indistinguishable from a broken one, and
 * the first person to arrive will tap it and then wait for a camera that is
 * still waking up. The lock is dropped by the OS whenever the page is hidden,
 * so it is re-taken on every return rather than requested once.
 */
async function holdWakeLock() {
    if (! ('wakeLock' in navigator)) return;

    try {
        state.wakeLock = await navigator.wakeLock.request('screen');
    } catch (error) {
        // Refused on a device that is not charging, among other reasons. The
        // kiosk still works; it just sleeps like any other tablet.
    }
}

/**
 * Bring everything back after the page was hidden.
 *
 * iOS Safari reclaims a backgrounded page aggressively, and a kiosk is
 * backgrounded every time somebody swipes up or the device locks. What comes
 * back is a live DOM holding a dead MediaStream — a preview frozen on the last
 * frame, which reads as "the tablet is working" while the detector sees a
 * still photograph forever. Restarting on every return is cheap; not doing it
 * is the failure that gets reported as "the kiosk froze".
 */
async function revive() {
    if (document.visibilityState !== 'visible') return;

    await holdWakeLock();

    if (! state.camera?.playing) {
        state.camera?.stop();
        state.camera = null;

        await startCamera();
    }

    state.camera?.resume();
    resetToIdle();
}

/* ── Wiring ───────────────────────────────────────────────────────────── */

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function setText(id, text) {
    const node = el(id);

    if (node) node.textContent = text ?? '';
}

function firstName(name) {
    return String(name || '').trim().split(/\s+/)[0] || '';
}

function bind() {
    document.addEventListener('click', (event) => {
        // The gesture Safari wants before it will play a stream it paused.
        state.camera?.resume();

        const target = event.target;

        if (target.closest('#kiosk-camera-overlay')) {
            startCamera();

            return;
        }

        if (target.closest('#kiosk-primary')) {
            punch('shift');

            return;
        }

        if (target.closest('#kiosk-break')) {
            punch('break');

            return;
        }

        if (target.closest('[data-kiosk-cancel]')) {
            resetToIdle();

            return;
        }

        if (target.closest('[data-kiosk-pin-open]')) {
            if (openPin(state.shortlist)) {
                setPinHint(state.shortlist.length
                    ? 'Tap your name and key your PIN.'
                    : 'Find your name, then key your PIN.');
            }

            return;
        }

        const person = target.closest('[data-employee]');

        if (person) {
            state.pinEmployee = { id: person.dataset.employee, name: person.dataset.name };
            state.pin = '';
            setPinHint('');
            paintPinStep();

            return;
        }

        const digit = target.closest('[data-kiosk-digit]');

        if (digit) {
            if (state.pin.length < 6) state.pin += digit.dataset.kioskDigit;
            paintPinStep();

            // Nothing auto-submits. A PIN that fires on its own length means
            // one mistyped digit is a failed attempt against a five-try
            // budget, with no chance to look at it first.
            return;
        }

        if (target.closest('[data-kiosk-back]')) {
            state.pin = state.pin.slice(0, -1);
            paintPinStep();

            return;
        }

        if (target.closest('[data-kiosk-pin-submit]')) {
            punch('shift');

            return;
        }

        if (target.closest('[data-kiosk-pin-cancel]')) {
            if (state.pinEmployee) {
                state.pinEmployee = null;
                state.pin = '';
                paintPinStep();
            } else {
                resetToIdle();
            }
        }
    });

    el('kiosk-pin-search')?.addEventListener('input', (event) => {
        filterPinList(event.target.value || '');
    });

    document.addEventListener('visibilitychange', revive);
    window.addEventListener('pageshow', revive);
}

async function boot() {
    if (! root()) return;

    bind();
    show('idle');
    setHint(defaultHint());

    await holdWakeLock();
    await startCamera();

    detectLoop();

    // The heartbeat. Without it a kiosk nobody has touched since lunch reads
    // as offline, and phone punches at that outlet quietly stop being flagged.
    setInterval(() => {
        api(endpoint('ping')).catch(() => {});
    }, PING_MS);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}
