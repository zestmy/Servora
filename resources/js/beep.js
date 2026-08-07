/**
 * The kiosk and staff app's confirmation tone.
 *
 * SYNTHESISED, not a file. Three reasons, and all three are about where this
 * plays: a tablet on a counter that is offline half the morning, a phone in a
 * kitchen doorway, and a service worker that is deliberately network-only on
 * everything except the face weights. An mp3 would be one more fetch that can
 * fail, one more thing to cache, and about 8KB to say something a 40-line
 * oscillator says exactly as well.
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

/** Recognised — the one this exists for. */
export function beepSuccess() {
    playTone('success');
}

/** Not recognised, or refused. */
export function beepError() {
    playTone('error');
}
