/**
 * The kiosk and staff app's confirmation tone.
 *
 * TWO SOUNDS, AND THE DIFFERENCE BETWEEN THEM MATTERS.
 *
 * The oscillator below is the CHIRP: the in-flight acknowledgement that fires
 * while something is still happening — a face was found, a frame could not be
 * read. It stays synthesised for the reasons it always was. It plays on a
 * tablet that is offline half the morning behind a service worker that is
 * deliberately network-only on everything except the face weights, and it says
 * in forty lines of oscillator what a file would need a fetch to say.
 *
 * The mp3s in /public/sounds are the OUTCOME: a clock-in that was recorded, a
 * clock-in that was refused, a quiz answer marked right or wrong. Those are
 * moments somebody is waiting on a verdict for, they happen once, and a
 * recognisable sound carries further across a kitchen than a chirp does.
 *
 * Keeping both is not indecision. If the two ran off one function then either
 * every scanning hiccup would play the full failure sound — up to twenty times
 * a minute at a shift change, three metres from people working — or the moment
 * that actually decides whether somebody is clocked in would sound identical to
 * a camera not finding a face. They are different events and they say different
 * things.
 *
 * A sample that has not loaded, or will not decode, falls back to the chirp.
 * The outcome is never silent because a fetch failed.
 *
 * WHY IT EXISTS. A face scan gives no physical feedback at all — you do not
 * touch anything, so the only confirmation is visual, and the person is often
 * looking at the camera rather than at the screen when it lands. In a kitchen
 * doorway at a shift change, with a queue behind you, the difference between
 * "it saw me" and "it has not seen me yet" is the difference between walking
 * away and standing there leaning in.
 *
 * WHAT IT MUST NOT BECOME: an alarm. This fires up to sixty times at a shift
 * change, three metres from people working. It is a short, quiet, two-note
 * chirp, well under the level of a till, and it is the same tone every time so
 * it stops being heard consciously within a day — which is the point.
 */

/** Made lazily, because constructing one before a gesture just makes a suspended context. */
let context = null;

/** A page that has been told to stay quiet. Set once, honoured everywhere. */
let muted = false;

function audio() {
    if (context) {
        return context;
    }

    const Ctor = window.AudioContext || window.webkitAudioContext;

    if (! Ctor) {
        return null;
    }

    try {
        context = new Ctor();
    } catch (error) {
        // Some locked-down browsers refuse outright. Silence is a perfectly
        // acceptable outcome here; nothing else on the screen depends on it.
        context = null;
    }

    return context;
}

/**
 * Autoplay policy: a context created before any user gesture starts suspended,
 * and stays that way until one arrives. A kiosk that nobody has touched since
 * it was put in its stand is exactly that case, so this is called on the first
 * touch as well as before every tone — resuming an already-running context is
 * free.
 */
export function unlockSound() {
    const ctx = audio();

    if (ctx && ctx.state === 'suspended') {
        ctx.resume().catch(() => {});
    }

    // The gesture that unlocks the context is also the earliest honest moment
    // to spend bandwidth on the samples.
    if (ctx) {
        preloadSamples();
    }
}

export function muteSound(value = true) {
    muted = Boolean(value);
}

/**
 * One note.
 *
 * Ramped up and down rather than switched on and off: a square-edged start and
 * stop on any waveform is an audible click, and a click is the part people find
 * annoying about beeps.
 */
function note(ctx, { frequency, startAt, duration, gain }) {
    const osc = ctx.createOscillator();
    const amp = ctx.createGain();

    // Sine, not square. A square wave at this frequency is a smoke alarm.
    osc.type = 'sine';
    osc.frequency.setValueAtTime(frequency, startAt);

    amp.gain.setValueAtTime(0.0001, startAt);
    amp.gain.exponentialRampToValueAtTime(gain, startAt + 0.012);
    amp.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);

    osc.connect(amp).connect(ctx.destination);
    osc.start(startAt);
    osc.stop(startAt + duration + 0.02);
}

/**
 * Play a tone.
 *
 * Never throws and never returns a rejected promise. This is decoration on a
 * screen that records attendance; an audio failure must not be able to take a
 * punch down with it, which is why every call site can ignore the result.
 *
 * @param {'success'|'error'} kind
 */
export function playTone(kind = 'success') {
    if (muted) {
        return;
    }

    const ctx = audio();

    if (! ctx) {
        return;
    }

    try {
        if (ctx.state === 'suspended') {
            // Fire and forget. If the resume lands in time the tone plays; if
            // it does not, the next one will.
            ctx.resume().catch(() => {});
        }

        const now = ctx.currentTime;

        if (kind === 'error') {
            // Down a minor third, quieter. Descending reads as "no" without
            // anybody having to be taught it.
            note(ctx, { frequency: 440, startAt: now,        duration: 0.10, gain: 0.05 });
            note(ctx, { frequency: 370, startAt: now + 0.10, duration: 0.14, gain: 0.05 });

            return;
        }

        // Success: up a fourth, A5 → D6. Short, bright, and clearly not the
        // sound a phone makes for anything else.
        note(ctx, { frequency: 880,  startAt: now,        duration: 0.075, gain: 0.06 });
        note(ctx, { frequency: 1175, startAt: now + 0.075, duration: 0.11, gain: 0.06 });
    } catch (error) {
        // As above: silence beats an exception.
    }
}

/**
 * The outcome sounds, served straight from public/ rather than through Vite.
 *
 * Root-relative on purpose: the staff app runs on company subdomains, and the
 * same public directory answers all of them.
 */
const SAMPLES = {
    success: '/sounds/success.mp3',
    error:   '/sounds/failure.mp3',
};

/**
 * How loud an outcome plays, against the file's own level.
 *
 * THE ONE KNOB. These are recordings rather than generated tones, so their
 * loudness is whatever was mastered into them; the chirp's gains below were
 * tuned by ear against a kitchen and cannot be compared to this number. Turn it
 * down here if the kiosk is too loud at a shift change — it is a counter tablet
 * three metres from people working, and the rule the chirp follows applies just
 * as much to a file: this must not become an alarm.
 */
const SAMPLE_GAIN = 0.7;

/** kind → AudioBuffer once decoded, or false once it has definitively failed. */
const buffers = {};

/**
 * The outcome currently sounding, so a new one can cut it off.
 *
 * These run about two and a half seconds, which is longer than the gap between
 * two quick quiz answers. Without this they layer on top of each other and the
 * third one in a row is genuinely unpleasant — and on a kiosk, a success
 * still ringing under a refusal is actively misleading.
 */
let playing = null;

/** In-flight decodes, so a shift change cannot start six fetches for one file. */
const loading = {};

/**
 * Fetch and decode one sample.
 *
 * Never throws and never rejects. A failure is recorded as `false` rather than
 * left undefined, so the next call falls straight through to the chirp instead
 * of retrying a 404 on every punch for the rest of the day.
 */
function loadSample(kind) {
    if (kind in buffers) {
        return Promise.resolve(buffers[kind]);
    }

    if (loading[kind]) {
        return loading[kind];
    }

    const ctx = audio();
    const url = SAMPLES[kind];

    if (! ctx || ! url) {
        return Promise.resolve(false);
    }

    loading[kind] = fetch(url)
        .then((response) => {
            if (! response.ok) {
                throw new Error('sound ' + response.status);
            }

            return response.arrayBuffer();
        })
        // Callback form as well as the promise: Safari resolved decodeAudioData
        // to a promise late, and this file runs on iPads that predate it.
        .then((bytes) => new Promise((resolve, reject) => {
            const decoded = ctx.decodeAudioData(bytes, resolve, reject);

            if (decoded && typeof decoded.then === 'function') {
                decoded.then(resolve, reject);
            }
        }))
        .then((buffer) => (buffers[kind] = buffer))
        .catch(() => (buffers[kind] = false))
        .finally(() => {
            delete loading[kind];
        });

    return loading[kind];
}

/**
 * Warm both samples so the first one is not waiting on a network round trip.
 *
 * Called from unlockSound(), which every screen already runs on the first
 * touch — so the fetch happens while somebody is walking up to the tablet
 * rather than at the moment they need to hear something.
 */
function preloadSamples() {
    Object.keys(SAMPLES).forEach((kind) => {
        loadSample(kind).catch(() => {});
    });
}

/**
 * Play an outcome sound, falling back to the chirp if the sample is not there.
 *
 * Like playTone(), never throws — this is decoration on a screen that records
 * attendance, and audio must not be able to take a punch down with it.
 *
 * @param {'success'|'error'} kind
 */
export function playSound(kind = 'success') {
    if (muted) {
        return;
    }

    const ctx = audio();

    if (! ctx) {
        return;
    }

    if (ctx.state === 'suspended') {
        ctx.resume().catch(() => {});
    }

    const buffer = buffers[kind];

    // Not loaded yet — start it for next time and chirp for this one. A first
    // punch of the day that beeps instead of chiming is a much better outcome
    // than one that waits in silence for a download.
    if (buffer === undefined) {
        loadSample(kind).catch(() => {});
        playTone(kind);

        return;
    }

    if (buffer === false) {
        playTone(kind);

        return;
    }

    try {
        // One outcome at a time. Stopping a source that has already ended
        // throws in some engines, hence the guard around it.
        if (playing) {
            try {
                playing.stop();
            } catch (error) {
                /* Already finished. */
            }
        }

        const source = ctx.createBufferSource();
        const amp    = ctx.createGain();

        source.buffer = buffer;
        amp.gain.setValueAtTime(SAMPLE_GAIN, ctx.currentTime);

        source.connect(amp).connect(ctx.destination);
        source.onended = () => {
            if (playing === source) {
                playing = null;
            }
        };

        source.start();
        playing = source;
    } catch (error) {
        playTone(kind);
    }
}

/** A clock-in was recorded, or a quiz answer was right. */
export function playSuccessSound() {
    playSound('success');
}

/** A clock-in was refused, or a quiz answer was wrong. */
export function playFailureSound() {
    playSound('error');
}

/** Recognised — the one this exists for. */
export function beepSuccess() {
    playTone('success');
}

/** Not recognised, or refused. */
export function beepError() {
    playTone('error');
}
