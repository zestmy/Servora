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

import { beepError, beepSuccess, unlockSound } from './beep.js';
import { ClockCamera, loadModels } from './clock.js';
import { looksLikeFace } from './face-geometry.js';

/**
 * The enrolment ceremony, in the order it is walked.
 *
 * Five angles rather than one, and the same five the HR enrolment screen uses,
 * because a face captured here has to be measured exactly the way one captured
 * there is. One head-on shot stops working the week somebody grows a beard.
 */
const ENROL_POSES = [
    ['centre', 'Look straight at the camera'],
    ['left',   'Slowly turn your head to your LEFT'],
    ['right',  'Slowly turn your head to your RIGHT'],
    ['up',     'Lift your chin UP'],
    ['down',   'Lower your chin DOWN'],
];

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

/**
 * How long a tapped action stays armed with nobody recognised.
 *
 * Short, and it is the only thing standing between "pick your punch, then look
 * at the camera" and a kiosk that clocks in whoever walks past it next. Long
 * enough to tap, step forward and be seen; short enough that somebody who taps
 * and then changes their mind has not left a trap for the next person.
 */
const ARM_MS = 20000;

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

    // The action somebody tapped on the idle screen, and when it stops
    // counting. Null means unarmed, which is the safe state: a recognised face
    // then gets the confirm card and has to be told what to do.
    armed: null,
    armedUntil: 0,

    // Enrolment. `enrolling` gates the detect loop off entirely — a kiosk
    // recognising and offering to clock people in while a manager is holding
    // it up to somebody's face would be chaos.
    enrolling: false,
    enrolCode: '',
    enrolRoster: [],
    enrolTarget: null,
    enrolScanning: false,
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

/**
 * Adopt the setting as the server currently reports it.
 *
 * This screen is opened once and left in a stand for weeks — it is the last
 * page in the building anybody thinks to reload. So the value it was RENDERED
 * with goes stale the moment a manager changes it, and a kiosk that keeps
 * offering a PIN for a fortnight after the switch was thrown makes the switch
 * look broken.
 *
 * The server is the authority either way: it refuses those punches whatever
 * this page believes. This only keeps the screen honest about itself.
 */
function syncPinAllowed(data) {
    if (typeof data?.allow_pin !== 'boolean') return;

    const node = root();

    if (! node) return;

    const next = data.allow_pin ? '1' : '0';

    if (node.dataset.allowPin === next) return;

    node.dataset.allowPin = next;

    // Just withdrawn: pull down anything already on screen that depends on it.
    if (! data.allow_pin) {
        el('kiosk-pin-offer')?.classList.add('hidden');

        if (state.mode === 'pin') resetToIdle();
    }
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

const PANELS = ['idle', 'confirm', 'pin', 'result', 'enrolcode', 'enrol'];

function show(panel) {
    state.mode = panel;

    PANELS.forEach((name) => {
        el(`kiosk-state-${name}`)?.classList.toggle('hidden', name !== panel);
    });

    // The stylesheet decides from this whether the panel gets the bottom band
    // or the whole screen — a message overlays the camera, a keypad replaces
    // it. Keeping that in CSS means one rule per state instead of a class list
    // assembled here and drifting from the markup.
    const shell = root();

    if (shell) shell.dataset.mode = panel;

    // Nothing to scan on a form.
    if (panel === 'pin' || panel === 'enrolcode' || panel === 'result') setScan('off');
}

/* ── Scan reticle ─────────────────────────────────────────────────────── */

/**
 * The ring around the viewfinder.
 *
 * Its whole job is to make the second and a half between "somebody is standing
 * there" and "here is your name" legible. Before this the screen said nothing
 * during it, which reads as a kiosk that has not noticed you — and the reflex
 * that follows is to lean in and wave, which moves the face and restarts the
 * stability timer that was about to fire.
 *
 * @param {'off'|'idle'|'locking'|'working'|'ok'|'fail'} mode
 * @param {number} fraction 0 → 1, only meaningful while locking.
 */
function setScan(mode, fraction = 0) {
    const shell = root();

    if (! shell) return;

    shell.dataset.scan = mode;

    // Held at full for the outcome states so the ring does not snap back to
    // empty at the exact moment it is confirming something.
    const value = mode === 'locking'
        ? Math.max(0, Math.min(1, fraction))
        : (mode === 'ok' || mode === 'fail' || mode === 'working' ? 1 : 0);

    shell.style.setProperty('--kiosk-scan', String(value));
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
    // Amber ring for as long as the message is up, so the outcome is legible
    // from further away than the sentence is.
    setScan('fail');
    beepError();

    const offer   = el('kiosk-pin-offer');
    const showing = offerPin && pinAllowed();

    offer?.classList.toggle('hidden', ! showing);

    // A plain notice is read in a glance; an offer has to survive somebody
    // deciding to take it, walking a step closer, and pressing it.
    clearTimeout(noticeTimer);
    noticeTimer = setTimeout(() => {
        if (state.mode === 'idle') {
            // Back to whichever idle this is. A failed scan does NOT disarm —
            // somebody who tapped "Clock IN" and was not recognised wants to
            // try again, not to tap it a second time — so the armed prompt has
            // to come back rather than the generic one.
            const intent = armedIntent();

            setHint(intent ? `${ARM_LABELS[intent]} — look at the camera` : defaultHint());
            offer?.classList.add('hidden');
            setScan('idle');
        }
    }, ms ?? (showing ? OFFER_MS : NOTICE_MS));
}

function defaultHint() {
    return 'Look at the camera to clock in';
}

/* ── Arming ───────────────────────────────────────────────────────────────
 *
 * "Say what you are doing, then be recognised" rather than "be recognised,
 * then say what you are doing". The second order is one tap more per person
 * and, worse, the tap comes AFTER the wait — so at a shift change the queue
 * moves at the speed of people reading cards with their own names on.
 *
 * Armed, a recognised face is punched immediately. Unarmed, it gets the
 * confirm card exactly as before, which is what keeps a kiosk in a doorway
 * from recording everybody who walks past it.
 */

const ARM_LABELS = {
    in:          'Clock IN',
    out:         'Clock OUT',
    break_start: 'Start break',
    break_end:   'End break',
};

/** Whether the armed action is still good. Expiry is checked, never trusted. */
function armedIntent() {
    if (state.armed && Date.now() < state.armedUntil) {
        return state.armed;
    }

    if (state.armed) {
        disarm();
    }

    return null;
}

function arm(type) {
    if (! ARM_LABELS[type]) return;

    // Tapping the lit one again turns it off. Somebody who armed the wrong
    // punch should not have to wait out the timer to undo it, and there is no
    // other affordance on this screen for "never mind".
    if (state.armed === type) {
        disarm();

        return;
    }

    state.armed      = type;
    state.armedUntil = Date.now() + ARM_MS;

    paintArmed();
    setHint(`${ARM_LABELS[type]} — look at the camera`);
    setText('kiosk-subhint', 'Recognised faces are recorded straight away.');
}

function disarm() {
    state.armed      = null;
    state.armedUntil = 0;

    paintArmed();

    if (state.mode === 'idle') {
        setHint(defaultHint());
        setText('kiosk-subhint', 'Tap what you are doing, then look at the camera.');
    }
}

function paintArmed() {
    document.querySelectorAll('[data-kiosk-arm]').forEach((node) => {
        node.classList.toggle('kiosk-arm-on', node.dataset.kioskArm === state.armed);
    });
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
        // half-keyed, a result showing, or an enrolment in progress all mean
        // somebody is mid-interaction and a second identification would talk
        // over them.
        if (state.enrolling || state.mode !== 'idle' || Date.now() < state.pausedUntil) {
            state.stableSince = null;
            continue;
        }

        if (! state.camera?.playing) {
            state.stableSince = null;
            setScan('idle');
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
            setScan('idle');
            continue;
        }

        state.stableSince ??= Date.now();

        const held = Date.now() - state.stableSince;

        // The ring fills over exactly the window the loop is waiting out, so
        // "hold still a moment longer" is shown rather than said.
        setScan('locking', held / STABLE_MS);

        if (held >= STABLE_MS) {
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
    // Indeterminate from here: the descriptor is going to the server and
    // nothing on this side knows how long that takes.
    setScan('working');

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
        result = await api(endpoint('identify'), {
            descriptor: face.descriptor,
            // Sent so the server can tell a break apart from a shift punch
            // before it applies the shift cooldown. Nothing is recorded here.
            intent: armedIntent(),
        });
    } catch (error) {
        notice('Connection problem. Try again.');

        return;
    }

    const data = result.data || {};

    // Before anything reads pinAllowed() below.
    syncPinAllowed(data);

    if (data.status === 'matched') {
        // Recognised, and the one moment worth making a sound about — this is
        // the only feedback that reaches somebody who is looking AT the camera
        // rather than at the screen beside it.
        beepSuccess();

        const intent = armedIntent();

        if (intent) {
            /*
             * Somebody already said what they were doing, so there is nothing
             * left to ask. Straight to the punch, with the name on screen while
             * it goes — the confirmation people actually read is the big green
             * card at the end, not the card in the middle.
             */
            state.token = data.token || null;

            setText('kiosk-name', data.employee?.name || '');
            setScan('ok');

            await punch(intent);

            return;
        }

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

    /*
     * The FULL name, not the first.
     *
     * "Hi Mohd" is a greeting; this is a receipt for something about to be
     * written onto somebody's attendance record, and at this outlet a first
     * name identifies about a third of the staff. The person has to be able to
     * tell at a glance that the kiosk has the right one of them — which is the
     * entire job of this card, and a shortened name cannot do it.
     */
    setText('kiosk-name', name);

    paintActions(data.options || [], data.next);

    show('confirm');
    // Recognised. The ring completes in green behind the name, which is the
    // one bit of feedback that survives being read from two metres back.
    setScan('ok');

    // Nobody taps a card they have walked away from, and the next person must
    // not arrive at somebody else's name.
    clearTimeout(confirmTimer);
    confirmTimer = setTimeout(() => {
        if (state.mode === 'confirm') resetToIdle();
    }, CONFIRM_TIMEOUT_MS);
}

/**
 * Draw the four punch buttons.
 *
 * The suggested one is filled and moved to the front, because the ordinary case
 * has to stay a single tap on the obvious thing. The rest are outlined and the
 * SAME SIZE — an override that is fiddlier to hit than the wrong answer is not
 * an override, and the whole reason these exist is that the suggestion is
 * sometimes wrong.
 *
 * Unavailable ones are shown dimmed rather than removed. A grid that changes
 * shape between people is one staff have to re-read every time, and "End break"
 * quietly missing is indistinguishable from a kiosk that has broken.
 */
function paintActions(options, next) {
    const host = el('kiosk-actions');

    if (! host) return;

    // A server that sent nothing usable still has to leave a working screen,
    // so fall back to the derived pair the old card used.
    let rows = Array.isArray(options) && options.length ? options.slice() : [
        { type: next?.type || 'in', label: next?.label || 'Clock IN', available: true, suggested: true },
    ];

    rows.sort((a, b) => (b.suggested === true) - (a.suggested === true));

    host.textContent = '';

    rows.forEach((row) => {
        const button = document.createElement('button');

        button.type = 'button';
        button.dataset.kioskPunch = row.type;
        button.disabled = row.available === false;
        button.textContent = row.label;

        button.className = row.suggested
            ? 'min-h-[4.5rem] rounded-panel bg-brand-600 px-2 text-2xl font-bold text-white shadow-btn '
              + 'hover:bg-brand-700 active:bg-brand-700'
            : 'min-h-[4.5rem] rounded-panel border border-white/25 bg-gray-950/50 px-2 text-xl font-semibold '
              + 'text-gray-100 hover:bg-gray-800 active:bg-gray-800 '
              + 'disabled:opacity-35 disabled:hover:bg-gray-950/50';

        host.appendChild(button);
    });
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

    // Every action disabled and the pressed one renamed, so a second tap in
    // the second before the server answers cannot become a second punch.
    document.querySelectorAll('[data-kiosk-punch]').forEach((node) => {
        node.disabled = true;

        if (node.dataset.kioskPunch === intent) {
            node.textContent = 'Recording…';
        }
    });

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

    // Disarmed after every punch, without exception. Twelve people clocking
    // into the same shift is twelve taps, and that is the trade being made
    // deliberately — the alternative is a screen left holding "Clock IN" for
    // whoever walks past next, which is the failure this whole flow exists to
    // avoid. The tap is also the cheap half: it can happen while the person in
    // front is still being scanned.
    disarm();

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
    setScan('idle');
}

/* ── Enrolment ────────────────────────────────────────────────────────────
 *
 * Gated by a window a manager opens with a code from HR. The server enforces
 * it on EVERY capture, not just when the window opens — everything here is the
 * screen following that, never the thing deciding it.
 *
 * Enrolment is the root of trust for the whole kiosk: a punch is only as good
 * as the enrolment it matched. Which is why the window closes by itself, why
 * the code is separate from the pairing code, and why the server refuses a
 * face that already belongs to somebody else at the outlet.
 */

function openEnrolCode() {
    state.enrolCode = '';
    setText('kiosk-enrol-error', '');
    paintEnrolCode();

    const input = el('kiosk-enrol-input');

    if (input) input.value = '';

    show('enrolcode');
}

function paintEnrolCode() {
    const dots = el('kiosk-enrol-dots');

    if (dots) dots.textContent = (state.enrolCode || '').padEnd(6, '·');
}

async function startEnrolment() {
    const typed = (el('kiosk-enrol-input')?.value || state.enrolCode || '').trim();

    if (! typed) {
        setText('kiosk-enrol-error', 'Key the code a manager gave you.');

        return;
    }

    let result;

    try {
        result = await api(endpoint('enrolStart'), { code: typed });
    } catch (error) {
        setText('kiosk-enrol-error', 'Connection problem. Try again.');

        return;
    }

    if (! result.ok || result.data?.status !== 'ok') {
        setText('kiosk-enrol-error', result.data?.message || 'That code did not work.');
        state.enrolCode = '';
        paintEnrolCode();

        return;
    }

    state.enrolling   = true;
    state.enrolRoster = result.data.employees || [];
    state.enrolTarget = null;

    paintEnrolLeft(result.data.minutes_left);
    paintEnrolRoster();
    setEnrolStep('list');
    show('enrol');
}

async function finishEnrolment() {
    // Told to the server rather than just forgotten, so a window is not left
    // open on a tablet somebody has walked away from.
    try {
        await api(endpoint('enrolStop'));
    } catch (error) {
        // Closing is best-effort: the window expires on its own regardless.
    }

    state.enrolling     = false;
    state.enrolTarget   = null;
    state.enrolScanning = false;

    resetToIdle();
}

function paintEnrolLeft(minutes) {
    if (typeof minutes === 'number') {
        setText('kiosk-enrol-left', minutes > 0 ? `— ${minutes} min left` : '— closing');
    }
}

function paintEnrolRoster() {
    const list = el('kiosk-enrol-list');

    if (! list) return;

    const needle = (el('kiosk-enrol-search')?.value || '').trim().toLowerCase();

    list.innerHTML = '';

    state.enrolRoster
        .filter((p) => ! needle || p.name.toLowerCase().includes(needle))
        .forEach((p) => {
            const b = document.createElement('button');

            b.type = 'button';
            b.dataset.kioskEnrolPick = String(p.id);
            b.className = 'min-h-[3.5rem] flex-auto basis-[calc(50%-0.25rem)] rounded-control border '
                + 'px-4 text-left text-base font-semibold '
                // Done is muted, not hidden: a manager needs to see the whole
                // roster to know where they are up to, and a name that vanished
                // when it was finished would read as a lost record.
                + (p.enough
                    ? 'border-success-700 bg-gray-800 text-success-300'
                    : 'border-gray-700 bg-gray-800 text-gray-100');

            b.innerHTML = `${p.name}<span class="block text-[11px] font-normal opacity-70">`
                + (p.enough ? `${p.count} captures — done` : `${p.count} of 3`)
                + '</span>';

            list.appendChild(b);
        });
}

function pickEnrolTarget(id) {
    const person = state.enrolRoster.find((p) => String(p.id) === String(id));

    if (! person) return;

    state.enrolTarget = person;

    setText('kiosk-enrol-who', person.name);
    setText('kiosk-enrol-pose', '');
    setText('kiosk-enrol-progress', person.enough
        ? `${person.count} captures on file — scanning again adds more angles.`
        : `${person.count} of 3 captures.`);

    el('kiosk-enrol-picker')?.classList.add('hidden');
    el('kiosk-enrol-scan')?.classList.remove('hidden');

    // Hands the screen back to the camera — see the [data-enrolstep] rules in
    // the kiosk stylesheet. The staff list wanted the whole display; a manager
    // aiming a tablet at somebody's face wants to see what it is aiming at.
    setEnrolStep('scan');
    setScan('idle');
}

function backToEnrolList() {
    state.enrolScanning = false;
    state.enrolTarget   = null;

    el('kiosk-enrol-scan')?.classList.add('hidden');
    el('kiosk-enrol-picker')?.classList.remove('hidden');

    setEnrolStep('list');
    paintEnrolRoster();
}

/** @param {'list'|'scan'} step */
function setEnrolStep(step) {
    const shell = root();

    if (shell) shell.dataset.enrolstep = step;
}

/**
 * Walk the five poses, saving each the moment it is held.
 *
 * A pose that cannot be reached is SKIPPED rather than retried forever — four
 * good angles beat a scan somebody abandoned halfway through, and the person
 * being enrolled is standing there while it happens.
 */
async function runEnrolScan() {
    if (state.enrolScanning || ! state.enrolTarget || ! state.camera) return;

    state.enrolScanning = true;

    let saved = 0;
    let stopped = null;

    try {
        for (const [pose, label] of ENROL_POSES) {
            if (! state.enrolScanning) break;

            setText('kiosk-enrol-pose', label);

            const held = await state.camera.waitForPose(pose, (fraction, hint) => {
                if (hint) setText('kiosk-enrol-pose', hint === 'Hold it…' ? 'Hold it…' : label);
                // The same ring, fed by the pose's own progress. Holding a
                // head turned to one side for a second is much easier when
                // something is counting it down in front of you.
                setScan('locking', fraction);
            });

            if (! held) {
                setScan('fail');
                continue;
            }

            setText('kiosk-enrol-pose', 'Hold still…');
            setScan('working');

            const face = await state.camera.capture();

            if (! face) {
                setScan('fail');
                continue;
            }

            const result = await api(endpoint('enrolCapture'), {
                employee_id: state.enrolTarget.id,
                descriptor:  face.descriptor,
                photo:       face.selfie,
            });

            const data = result.data || {};

            if (result.ok && data.status === 'ok') {
                saved++;
                setScan('ok');
                state.enrolTarget.count  = data.count;
                state.enrolTarget.enough = data.enough;
                paintEnrolLeft(data.minutes_left);
                setText('kiosk-enrol-progress', `${data.count} captures saved.`);
                continue;
            }

            // A refusal is worth stopping for, not pressing past: the window
            // closed, the slate is full, or — the one that matters — this face
            // is already enrolled as somebody else.
            stopped = data.message || 'That capture was refused.';
            break;
        }
    } catch (error) {
        stopped = 'Connection problem. Try the scan again.';
    } finally {
        state.enrolScanning = false;

        setScan(stopped ? 'fail' : 'idle');
        setText('kiosk-enrol-pose', '');
        setText('kiosk-enrol-progress', stopped
            ? stopped
            : (saved
                ? `Scan finished — ${saved} captures saved (${state.enrolTarget?.count ?? 0} on file).`
                : 'Scan finished without a usable capture. Try again in better light.'));

        // Refresh the roster row so the list shows the new count on return.
        const target = state.enrolTarget;

        if (target) {
            const row = state.enrolRoster.find((p) => p.id === target.id);

            if (row) { row.count = target.count; row.enough = target.enough; }
        }
    }
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

    // Mid-enrolment, only the camera needs reviving — resetToIdle() below
    // would throw a manager out of a scan they are halfway through.
    if (state.enrolling) {
        await holdWakeLock();
        state.camera?.resume();

        return;
    }

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

function bind() {
    document.addEventListener('click', (event) => {
        // The gesture Safari wants before it will play a stream it paused.
        state.camera?.resume();

        const target = event.target;

        if (target.closest('#kiosk-camera-overlay')) {
            startCamera();

            return;
        }

        const armButton = target.closest('[data-kiosk-arm]');

        if (armButton) {
            arm(armButton.dataset.kioskArm);

            return;
        }

        const action = target.closest('[data-kiosk-punch]');

        if (action) {
            // The type the person chose. The server re-derives what it means
            // against its own record either way — this says what was pressed,
            // not what gets written.
            punch(action.dataset.kioskPunch);

            return;
        }

        if (target.closest('[data-kiosk-cancel]')) {
            resetToIdle();

            return;
        }

        /* ── Enrolment ────────────────────────────────────────────── */

        if (target.closest('[data-kiosk-enrol-open]')) {
            openEnrolCode();

            return;
        }

        const enrolKey = target.closest('[data-kiosk-enrol-key]');

        if (enrolKey) {
            if (state.enrolCode.length < 12) {
                state.enrolCode += enrolKey.dataset.kioskEnrolKey;

                const input = el('kiosk-enrol-input');

                if (input) input.value = state.enrolCode;
            }
            paintEnrolCode();

            return;
        }

        if (target.closest('[data-kiosk-enrol-submit]')) {
            startEnrolment();

            return;
        }

        if (target.closest('[data-kiosk-enrol-cancel]')) {
            resetToIdle();

            return;
        }

        if (target.closest('[data-kiosk-enrol-finish]')) {
            finishEnrolment();

            return;
        }

        if (target.closest('[data-kiosk-enrol-back]')) {
            backToEnrolList();

            return;
        }

        if (target.closest('[data-kiosk-enrol-scan]')) {
            runEnrolScan();

            return;
        }

        const pick = target.closest('[data-kiosk-enrol-pick]');

        if (pick) {
            pickEnrolTarget(pick.dataset.kioskEnrolPick);

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

    el('kiosk-enrol-search')?.addEventListener('input', paintEnrolRoster);

    // The typed code is the authoritative one — the 9-key grid is only a
    // shortcut, and the alphabet is 31 characters wide.
    el('kiosk-enrol-input')?.addEventListener('input', (event) => {
        state.enrolCode = (event.target.value || '').toUpperCase();
        paintEnrolCode();
    });

    /*
     * Audio needs a user gesture before it will make a sound, and a kiosk in a
     * stand may go a while without one. Hooked to the FIRST touch or click of
     * the session, whatever it was for — the context stays running for the life
     * of the page after that, which on this screen is the whole shift.
     *
     * Consequence worth knowing: the very first scan after the tablet is set up
     * is silent, because nothing has been touched yet. Every one after it is
     * not. Capture phase, so it runs even where a handler below stops
     * propagation.
     */
    ['pointerdown', 'touchstart', 'keydown'].forEach((event) => {
        document.addEventListener(event, unlockSound, { once: true, capture: true, passive: true });
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

    // A window a manager opened before this page loaded — a reload, or the
    // service worker replacing the page mid-session. Without this the tablet
    // would drop back to the punch screen and the manager would have to ask
    // for another code for a window that is still open.
    if (root()?.dataset.enrolling === '1') {
        state.enrolling = true;
        openEnrolCode();
        setText('kiosk-enrol-error', 'Enrolment is still open — key the code again to carry on.');
    }

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
