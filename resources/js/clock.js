/**
 * Camera, face descriptor and GPS for the staff clock-in app.
 *
 * Loaded only by the clock layout, and the face model itself is behind a
 * dynamic import so the ~1.3MB library is a separate chunk that the rest of
 * the app never pays for.
 *
 * WHAT THIS FILE IS TRUSTED WITH, and what it is not:
 *
 * It turns a camera frame into a 128-float descriptor, and reports the
 * device's coordinates. That is all. It does not decide whether the face
 * matched or whether the person is on site — those comparisons happen on the
 * server against the company's thresholds, because everything here runs on
 * the employee's own phone and is theirs to edit.
 *
 * The blink prompt below is likewise a deterrent, not a control. It makes
 * holding up a printed photo visibly awkward, which is enough to stop the
 * casual attempt; it cannot stop anyone willing to open devtools. The real
 * backstop for spoofing is that every punch keeps a selfie and a mismatch
 * puts it in front of a manager.
 */

import { looksLikeFace } from './face-geometry.js';

const MODEL_URL = document.querySelector('meta[name="face-models-url"]')?.content || '/face-models';

/** Detector input size. 320 is the smallest that reliably finds a face at
 *  arm's length, and it runs about four times faster than 608 on the mid-range
 *  Android phones this is used on. */
const DETECTOR_INPUT_SIZE = 320;

/**
 * Below this the "face" is usually a shadow or a pattern on a wall.
 *
 * Raised from 0.45, which was low enough that an open hand held up to the
 * camera scored as a face. tinyFaceDetector is a speed-first model and it is
 * generous with skin-toned blobs; it is kept for the live framing loop, where
 * it runs several times a second and a wrong answer costs a wrong hint, and
 * no longer trusted for anything that gets recorded.
 */
const DETECTOR_SCORE_THRESHOLD = 0.6;

/**
 * The detector used when it MATTERS — every capture that becomes a
 * descriptor, at enrolment and at the door.
 *
 * SSD MobileNet v1 is the accurate detector face-api ships: 5.4MB against
 * tiny's 190KB, several times slower per frame, and dramatically less willing
 * to call a hand a face. That trade is obviously right here — a capture
 * happens once per punch, where tenths of a second are free and a false
 * positive is a wrong person's attendance record.
 */
const CAPTURE_MIN_CONFIDENCE = 0.7;

let faceapi = null;
let modelsReady = null;

/** Load the library and its weights once per page. */
async function loadModels() {
    if (modelsReady) return modelsReady;

    modelsReady = (async () => {
        faceapi = await import('@vladmandic/face-api');

        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
            faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
        ]);

        return faceapi;
    })().catch((error) => {
        // Reset so a later attempt can retry rather than resolving the same
        // rejected promise forever — a flaky first load on outlet wifi is the
        // normal case, not an exceptional one.
        modelsReady = null;
        throw error;
    });

    return modelsReady;
}

/**
 * Rough head pose from the 68-point landmarks.
 *
 * Not a real 3D solve — no camera intrinsics, no PnP. Two ratios are enough
 * to tell "turned left" from "facing forward", and that is the whole job:
 *
 *   yaw   how far the nose sits from the middle of the jaw span. Positive is
 *         the subject's own LEFT, because landmark 0 is their right jaw and
 *         16 their left, so the nose slides toward 16 as they turn left.
 *   pitch where the nose tip sits between the eye line and the mouth. Tilting
 *         the chin up foreshortens the lower face and the tip rises toward
 *         the eyes; tilting down does the reverse.
 *
 * Both are normalised by the face's own size, so they hold whether somebody
 * is at arm's length or leaning in.
 */
function estimatePose(landmarks) {
    const jaw   = landmarks.getJawOutline();
    const nose  = landmarks.getNose();
    const mouth = landmarks.getMouth();
    const eyeL  = landmarks.getLeftEye();
    const eyeR  = landmarks.getRightEye();

    if (! jaw?.length || ! nose?.length || ! mouth?.length) return null;

    const meanY = (points) => points.reduce((sum, p) => sum + p.y, 0) / points.length;

    const noseTip = nose[6] || nose[nose.length - 1];
    const jawFrom = jaw[0];
    const jawTo   = jaw[jaw.length - 1];
    const span    = jawTo.x - jawFrom.x;

    if (! span) return null;

    const eyeY   = (meanY(eyeL) + meanY(eyeR)) / 2;
    const mouthY = meanY(mouth);
    const height = mouthY - eyeY;

    if (! height) return null;

    return {
        // 0 is dead centre either way.
        yaw:   ((noseTip.x - jawFrom.x) / span) - 0.5,
        pitch: ((noseTip.y - eyeY) / height) - NEUTRAL_PITCH,
    };
}

/** Where the nose tip sits between eyes and mouth on a level head. */
const NEUTRAL_PITCH = 0.55;

/**
 * The poses a scan can ask for.
 *
 * Thresholds are deliberately loose. A scan that will not accept a
 * three-quarter turn is a scan somebody gives up on, and the descriptors
 * are more useful for the variety than for the exact angle.
 */
const POSES = {
    centre: { label: 'Look straight at the camera', test: (p) => Math.abs(p.yaw) < 0.07 && Math.abs(p.pitch) < 0.10 },
    left:   { label: 'Slowly turn your head to your LEFT',  test: (p) => p.yaw > 0.11 },
    right:  { label: 'Slowly turn your head to your RIGHT', test: (p) => p.yaw < -0.11 },
    up:     { label: 'Lift your chin UP',   test: (p) => p.pitch < -0.13 },
    down:   { label: 'Lower your chin DOWN', test: (p) => p.pitch > 0.15 },
};

/** The enrolment ceremony: centre first, then the four angles. */
const SCAN_SEQUENCE = ['centre', 'left', 'right', 'up', 'down'];

/** What a clock-in punch may ask for. Centre is excluded — facing the camera
 *  is what somebody does anyway, so it proves nothing about liveness. */
const PUNCH_CHALLENGES = ['left', 'right', 'up', 'down'];

/** How long a pose must be held before it counts — long enough not to fire
 *  as somebody sweeps past it, short enough not to feel like a stare. */
const POSE_HOLD_MS = 550;

/** Give up on a pose after this and say so, rather than waiting forever. */
const POSE_TIMEOUT_MS = 12000;

/** How long to wait for the ~6.5MB of face weights before giving up on them
 *  and letting the punch proceed without a face check. */
const MODEL_TIMEOUT_MS = 30000;

/** How long the camera gets to open before the diagnostic line is shown.
 *  Long enough to cover a permission prompt somebody is reading. */
const REVEAL_AFTER_MS = 6000;

/*
 * Which camera. These are the values getUserMedia's facingMode takes, and
 * they are declared here because for several releases they were not declared
 * anywhere at all.
 *
 * Nine sites referenced them and every one threw ReferenceError. The first
 * was preferredFacing(), called while constructing the ClockCamera — so the
 * camera never opened, on any device, and getUserMedia was never so much as
 * called. It read as "camera not opening" and survived every fix aimed at the
 * start sequence, because the start sequence was not the part that was
 * broken.
 *
 * The try/catch in preferredFacing() is what kept it invisible: written to
 * survive a browser that refuses localStorage, it swallowed the real
 * ReferenceError and then threw an identical one from its own catch, where
 * nothing was left to report it.
 */
const FACING_USER        = 'user';
const FACING_ENVIRONMENT = 'environment';
const FACING_KEY         = 'servora-clock-facing';

export class ClockCamera {
    constructor({ video, canvas, onStatus, facing = FACING_USER }) {
        this.video = video;
        this.canvas = canvas;
        this.onStatus = onStatus || (() => {});
        this.stream = null;
        this.facing = facing;
    }

    /**
     * Open the camera.
     *
     * Both awaits below have bitten us on iOS and are deliberately defensive:
     *
     * getUserMedia does NOT reject when a permission prompt goes unanswered —
     * it stays pending forever. Awaiting it bare left the preview stuck on
     * "Starting camera…" with neither a success nor an error path ever
     * running, which is the one outcome this screen must never produce. It
     * races a timeout so the caller can offer a retry instead.
     *
     * play() is not awaited at all. The element carries `autoplay`, so Safari
     * frequently starts playback itself and then rejects the explicit call
     * with an AbortError — and in some versions never settles it. None of
     * that matters: frames flow as soon as srcObject is set, which is what
     * the detector reads.
     */
    async start({ timeoutMs = 12000 } = {}) {
        if (this.stream) return;

        if (! navigator.mediaDevices?.getUserMedia) {
            throw new DOMException('This browser has no camera API.', 'NotSupportedError');
        }

        let timer;

        note('gum');

        try {
            this.stream = await Promise.race([
                // facingMode 'user' rather than exact: a kitchen tablet
                // mounted on a wall may only have one camera, and exact would
                // fail outright on it.
                navigator.mediaDevices.getUserMedia({
                    // facingMode as a PREFERENCE, not `exact`: a wall-mounted
                    // tablet may have only one camera, and exact would fail
                    // outright rather than falling back to what exists.
                    video: { facingMode: this.facing, width: { ideal: 640 }, height: { ideal: 480 } },
                    audio: false,
                }),
                new Promise((_, reject) => {
                    timer = setTimeout(
                        () => reject(new DOMException('Timed out waiting for the camera.', 'TimeoutError')),
                        timeoutMs,
                    );
                }),
            ]);
        } finally {
            clearTimeout(timer);
        }

        note('gum:ok');

        this.paint();
    }

    /**
     * Point this camera at a <video> element, keeping the stream.
     *
     * A MediaStream is not owned by the element showing it. When Livewire
     * rebuilds the component the <video> is replaced, and the old code
     * responded by stopping the camera and asking for it again — a second
     * permission round trip on iOS, for a stream that was alive and well the
     * whole time. Worse, it did that while a start was still in flight,
     * abandoning it and force-clearing the lock, so two getUserMedia calls
     * could be outstanding at once against elements neither of which was on
     * the page any more.
     *
     * Re-pointing costs nothing and prompts for nothing. It is also correct
     * mid-start: if the stream has not arrived yet, start() paints whichever
     * element is current by the time it does.
     */
    attach(video, canvas) {
        if (canvas) this.canvas = canvas;

        if (! video || video === this.video) return;

        // Release the old node first, so two elements never hold one stream.
        if (this.video) this.video.srcObject = null;

        this.video = video;

        if (this.stream) this.paint();
    }

    /** Put the current stream on the current element and get it moving. */
    paint() {
        if (! this.video || ! this.stream) return;

        this.video.srcObject = this.stream;

        // Only the front camera is mirrored. A mirror is what people expect
        // of a camera pointed at themselves; doing it to the rear one would
        // flip the room and any text in it.
        this.video.style.transform = this.facing === FACING_USER ? 'scaleX(-1)' : 'none';

        // Metadata can land after srcObject is assigned, and Safari will not
        // start a stream it has no dimensions for yet — so play is attempted
        // both now and once the element knows what it is showing.
        this.video.addEventListener('loadedmetadata', () => this.resume(), { once: true });
        this.resume();
    }

    /**
     * Start or restart playback, ignoring rejection.
     *
     * A paused <video> is INVISIBLE but still capturable: the detector and
     * the canvas both read its current decoded frame, so a rejected play()
     * produced exactly "the camera is not opening, but Capture works". This
     * is called again from every tap, because a play() made under a real
     * user gesture is the one Safari does not refuse.
     */
    resume() {
        if (! this.video || ! this.stream) return;

        if (this.video.paused || this.video.readyState < 2) {
            this.video.play?.().catch(() => {});
        }
    }

    /** Whether the preview is actually showing moving pictures. */
    get playing() {
        return Boolean(this.stream) && ! this.video?.paused && (this.video?.videoWidth ?? 0) > 0;
    }

    stop() {
        this.stream?.getTracks().forEach((track) => track.stop());
        this.stream = null;

        // Guarded: attach() can leave this holding a node that has since been
        // removed, and an unguarded assignment here would throw from a
        // teardown path — turning a tidy-up into the failure.
        if (this.video) this.video.srcObject = null;
    }

    /**
     * Wait until a named pose is HELD, reporting progress as it goes.
     *
     * Replaces the old blink prompt. A blink is a poor liveness signal — a
     * photo waved slightly can pass one, and a still photo cannot turn its
     * head at all. It is still a deterrent rather than a control: the real
     * backstop remains that every punch keeps a selfie a manager can look at.
     *
     * @param {string} poseKey
     * @param {(fraction: number, hint: string) => void} onProgress
     * @returns {Promise<boolean>} whether the pose was actually reached.
     */
    async waitForPose(poseKey, onProgress = () => {}) {
        const api  = await loadModels();
        const pose = POSES[poseKey];

        if (! pose) return false;

        const options = new api.TinyFaceDetectorOptions({
            inputSize: DETECTOR_INPUT_SIZE,
            scoreThreshold: DETECTOR_SCORE_THRESHOLD,
        });

        const deadline = Date.now() + POSE_TIMEOUT_MS;
        let heldSince = null;

        while (Date.now() < deadline) {
            const result = await api.detectSingleFace(this.video, options).withFaceLandmarks();

            // A pose read off something that is not a face is a pose read off
            // nothing. Without this the scan happily tracked an open hand
            // through all four angles.
            const real   = result ? looksLikeFace(result.landmarks) : { ok: false };
            const angles = real.ok ? estimatePose(result.landmarks) : null;

            if (angles && pose.test(angles)) {
                heldSince ??= Date.now();

                const held = Math.min(1, (Date.now() - heldSince) / POSE_HOLD_MS);
                onProgress(held, 'Hold it…');

                if (held >= 1) return true;
            } else {
                // Reset rather than decay: a pose half-held then abandoned is
                // not most of a pose.
                heldSince = null;
                onProgress(0, real.ok ? pose.label : 'Looking for a face…');
            }

            await new Promise((r) => setTimeout(r, 110));
        }

        onProgress(0, '');

        return false;
    }

    /**
     * A descriptor and a still, or null if no face could be read.
     *
     * @returns {Promise<{descriptor: number[], selfie: string, score: number}|null>}
     */
    async capture() {
        const api = await loadModels();

        // The accurate detector, not the fast one. This is the frame that
        // becomes a descriptor and gets written to somebody's attendance
        // record, and it happens once per punch — there is nothing to spend
        // the speed on. tinyFaceDetector at its old threshold accepted an
        // open hand here.
        const options = new api.SsdMobilenetv1Options({
            minConfidence: CAPTURE_MIN_CONFIDENCE,
            maxResults: 1,
        });

        // Best of three. A single frame catches motion blur often enough to
        // be annoying, and the detector's own score is a decent proxy for
        // which frame is worth keeping.
        let best = null;
        let rejected = null;

        for (let i = 0; i < 3; i++) {
            const result = await api
                .detectSingleFace(this.video, options)
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (result) {
                // Second opinion, and a cheap one. The detector says where a
                // face is; this says whether the landmarks it produced
                // describe one. Both have to agree before a descriptor is
                // built out of the frame.
                const real = looksLikeFace(result.landmarks);

                if (! real.ok) {
                    rejected = real.why;
                } else if (! best || result.detection.score > best.detection.score) {
                    best = result;
                }
            }

            if (i < 2) await new Promise((r) => setTimeout(r, 100));
        }

        if (! best) {
            // Distinguishes "nothing found" from "found something that was
            // not a face", because the two need different things from the
            // person holding the phone.
            this.lastRejection = rejected;

            return null;
        }

        this.lastRejection = null;

        return {
            descriptor: Array.from(best.descriptor),
            score: best.detection.score,
            selfie: this.still(),
        };
    }

    /** The current frame as a JPEG data URL, sized for a review screen. */
    still() {
        const width = 480;
        const height = Math.round((this.video.videoHeight / this.video.videoWidth) * width) || 360;

        this.canvas.width = width;
        this.canvas.height = height;

        const ctx = this.canvas.getContext('2d');

        // Un-mirror, but only what was mirrored. The front preview is
        // flipped so it behaves like a mirror, which is what people expect —
        // but the stored evidence should show the person the way a witness
        // would see them. The rear camera was never flipped, so flipping it
        // here would invent a reversal that never happened.
        ctx.save();

        if (this.facing === FACING_USER) {
            ctx.translate(width, 0);
            ctx.scale(-1, 1);
        }

        ctx.drawImage(this.video, 0, 0, width, height);
        ctx.restore();

        return this.canvas.toDataURL('image/jpeg', 0.75);
    }
}

/**
 * Current position, or null if it cannot be had.
 *
 * Never rejects: a refused permission is an ordinary outcome here, and the
 * caller decides what a missing fix means.
 */
export function currentPosition({ timeout = 12000 } = {}) {
    return new Promise((resolve) => {
        if (! navigator.geolocation) {
            resolve(null);
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => resolve({
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy: position.coords.accuracy,
            }),
            () => resolve(null),
            // enableHighAccuracy asks for GPS rather than the wifi/cell
            // estimate, which indoors is routinely a few hundred metres out —
            // wide enough to put someone outside their own outlet's fence.
            { enableHighAccuracy: true, timeout, maximumAge: 0 },
        );
    });
}

export { loadModels };

/* ── Camera screen controller ─────────────────────────────────────────────
 *
 * Drives BOTH camera screens — the staff punch screen and the manager-facing
 * face enrolment screen — from this module rather than from their inline
 * Livewire @script blocks.
 *
 * @script runs when Livewire initialises. The Vite tag that loads this file
 * is a deferred module. Those two orders are not guaranteed, so a @script
 * that reached for window.ServoraClock could find nothing there yet and give
 * up — which on the enrolment screen is exactly what happened. Booting from
 * inside the module removes the race: by definition this code runs only once
 * the module has evaluated. Only the final submit still needs $wire.
 */

/**
 * Each screen names its own elements. Same behaviour, different ids —
 * a face enrolled on one screen has to be measured the same way it will be
 * measured at the door, so they share one pipeline rather than two.
 */
const SCREENS = [
    {
        key: 'punch',
        video: 'clock-video', canvas: 'clock-canvas',
        overlay: 'clock-camera-overlay', message: 'clock-camera-message',
        status: 'clock-status', diagnostics: 'clock-diagnostics',
        guide: 'clock-guide-oval', hint: 'clock-guide-hint', progress: null,
        ring: 'clock-guide-ring',
    },
    {
        key: 'enrol',
        video: 'enrol-video', canvas: 'enrol-canvas',
        overlay: 'enrol-overlay', message: 'enrol-overlay-message',
        status: 'enrol-status', diagnostics: 'enrol-diagnostics',
        guide: 'enrol-guide-oval', hint: 'enrol-guide-hint', progress: 'enrol-progress',
        ring: 'enrol-guide-ring',
    },
];

const screen = {
    dom: null,
    videoNode: null,
    guiding: false,
    scanning: false,
    camera: null,
    faceAvailable: false,
    starting: false,
    busy: false,
    error: null,
    bootedAt: 0,
};

/* ── The event trail ──────────────────────────────────────────────────────
 *
 * A snapshot of the state was not enough. "cam off · idle" was reported from
 * a phone where the overlay still read "Starting camera…", and that single
 * line is consistent with at least four different stories: a start that never
 * ran, one that ran and threw past its own catch, one abandoned mid-flight by
 * a DOM swap, and one that finished against an element no longer on the page.
 * Guessing between them cost three attempts.
 *
 * So the screen now carries its own history: a short ring buffer of what
 * actually happened, in order, printed on the same line. It is a dozen short
 * tokens, it costs nothing, and it turns a screenshot into an answer.
 */
const trail = [];

function note(event) {
    trail.push(event);

    if (trail.length > 14) trail.shift();
}

const el = (id) => (id ? document.getElementById(id) : null);

/** Which camera screen, if any, is on the page right now. */
function findScreen() {
    return SCREENS.find((s) => el(s.video) && el(s.canvas)) || null;
}

const setOverlay = (text) => {
    const node = el(screen.dom?.message);
    if (node) node.textContent = text;
};

const setStatus = (text) => {
    const node = el(screen.dom?.status);
    if (node) node.textContent = text || '';
};

/**
 * Why a capture produced nothing.
 *
 * "No face found" was the only answer for both possible causes, and they need
 * opposite things from the person holding the phone: an empty frame means get
 * closer, whereas a frame the checks REJECTED means what is in it is not a
 * face — which is worth saying plainly, because the person doing it usually
 * knows they are holding up a hand.
 */
function captureProblem(camera) {
    return camera?.lastRejection
        ? 'That did not look like a face. Point the camera at your face and try again.'
        : 'No face found. Move closer, into better light, and try again.';
}

/** Every message ends with what to DO — the reader is standing at a door. */
function cameraProblem(error) {
    switch (error?.name) {
        case 'NotAllowedError':
        case 'SecurityError':
            return 'Camera blocked. Allow camera for this site in your browser settings, then tap here.';
        case 'NotFoundError':
        case 'OverconstrainedError':
            return 'No camera found on this device. Tap to try again.';
        case 'NotReadableError':
            return 'Another app is using the camera. Close it, then tap here.';
        case 'TimeoutError':
            return 'The camera did not respond — a permission prompt may be waiting. Tap to try again.';
        case 'NotSupportedError':
            return 'This browser cannot use the camera. Ask your manager to record this shift.';
        default:
            return 'Could not start the camera. Tap to try again.';
    }
}

/**
 * One line a staff member can read out over the phone.
 *
 * Hidden while everything works, revealed once something has plainly gone
 * wrong — at which point "it's broken" needs to become a fact somebody can
 * act on without a laptop and a debugger.
 */
function showDiagnostics() {
    const node = el(screen.dom?.diagnostics);
    if (! node) return;

    const video  = el(screen.dom?.video);
    const camera = screen.camera?.stream ? 'on' : (screen.error || 'off');

    // Enough to place the failure exactly: whether the start sequence is
    // still running, whether the browser offers a camera API at all, and
    // whether the element ever received frames.
    node.textContent = [
        `app ${window.Livewire ? 'ok' : 'NOT STARTED'}`,
        `cam ${camera}`,
        screen.starting ? 'starting' : 'idle',
        navigator.mediaDevices?.getUserMedia ? 'api' : 'NO API',
        window.isSecureContext ? 'https' : 'INSECURE',
        `${video?.videoWidth || 0}x${video?.videoHeight || 0}`,
        video?.paused ? 'paused' : 'playing',
        `face ${screen.faceAvailable ? 'ok' : 'off'}`,
        // The history, last first, because the most recent event is the one
        // that explains the current state.
        trail.length ? `[${trail.slice().reverse().join(' <- ')}]` : '[no events]',
    ].join(' · ');

    // Hidden while a start is still plausibly in progress. Revealing it at
    // once would put a fault line on a screen that is about to work.
    const settled = screen.bootedAt && Date.now() - screen.bootedAt > REVEAL_AFTER_MS;

    if ((settled && ! screen.camera?.stream) || ! window.Livewire) {
        node.classList.remove('hidden');
    }
}

async function startScreen() {
    if (! screen.dom) return;

    if (screen.starting) {
        note('skip:busy');

        return;
    }

    if (screen.camera?.stream) {
        note('skip:live');
        screen.camera.resume();

        return;
    }

    screen.starting = true;
    screen.error    = null;
    setOverlay('Starting camera…');
    note('start');

    // try/catch/finally around the WHOLE body. `starting` is a lock, and it
    // used to be released on each path individually — so any await that never
    // settled pinned it true forever, and every retry after that (a tap, a
    // re-boot, the observer) returned early without doing anything at all.
    //
    // The catch matters just as much. startCamera() guards getUserMedia, but
    // anything thrown OUTSIDE that guard escaped as an unhandled rejection —
    // no error recorded, overlay still reading "Starting camera…", lock duly
    // released by the finally. That is indistinguishable from a hang and it
    // is precisely what was reported.
    try {
        await startCamera();
    } catch (error) {
        screen.error = error?.name || 'script';
        note(`throw:${screen.error}`);
        setOverlay('Something went wrong starting the camera. Tap to try again.');
    } finally {
        screen.starting = false;
    }

    // No stream, no error, nothing to tap through. Whatever the cause, that
    // combination is a bug in this file rather than a camera the phone
    // refused, and a screen that says nothing is the one outcome this must
    // never produce. Name it and offer the retry.
    if (! screen.camera?.stream && ! screen.error) {
        screen.error = 'nostream';
        note('nostream');
        setOverlay('The camera did not start. Tap to try again.');
    }

    if (! screen.camera?.stream) showDiagnostics();
}

async function startCamera() {
    screen.camera ??= new ClockCamera({
        video: el(screen.dom.video),
        canvas: el(screen.dom.canvas),
        onStatus: setStatus,
        facing: preferredFacing(),
    });

    // Always the CURRENT elements: a camera kept across a Livewire rebuild
    // is still pointing at the nodes it was made with until it is told
    // otherwise, and painting a detached <video> shows nobody anything.
    screen.camera.attach(el(screen.dom.video), el(screen.dom.canvas));

    try {
        await screen.camera.start();
        el(screen.dom.overlay)?.classList.add('hidden');
        note('open');
    } catch (error) {
        screen.error = error?.name || 'failed';
        note(`err:${screen.error}`);
        setOverlay(cameraProblem(error));
        showDiagnostics();

        return;
    }

    // A stream that opened but is not painting looks identical to a dead
    // camera, so it is named rather than left as a black rectangle.
    setTimeout(() => {
        if (screen.camera && ! screen.camera.playing) {
            setStatus('Tap the preview to start the picture.');
        }
    }, 1500);

    setStatus('Getting the face check ready…');

    try {
        // A deadline of its own. The weights are ~6.5MB and a stalled fetch
        // never rejects — which used to hold the whole start sequence open.
        await Promise.race([
            loadModels(),
            new Promise((_, reject) => setTimeout(
                () => reject(new DOMException('Model download timed out.', 'TimeoutError')),
                MODEL_TIMEOUT_MS,
            )),
        ]);

        screen.faceAvailable = true;
        setStatus('');
    } catch (error) {
        // The weights are ~6.5MB; a bad connection is the usual cause.
        setStatus(screen.dom.key === 'enrol'
            ? 'Face model could not load. Check the connection and reload.'
            : 'Face check unavailable — your punch will be sent for review.');
    }

    runGuide();

    // Enrolment starts scanning on its own — that IS the feature. It waits
    // for somebody to be selected, and stays quiet once there are enough
    // faces on file, so reopening a finished person is not a fresh ceremony.
    if (screen.dom?.key === 'enrol') {
        const bar = el(screen.dom.progress);
        const needed = Number(bar?.dataset.needed || 3);
        const have = Number(bar?.dataset.have || 0);

        if (document.getElementById('enrol-form')?.dataset.employee && have < needed) {
            runScan();
        }
    }
}

/** Shared guard for the two actions below. */
function notReady() {
    if (screen.starting) {
        setStatus('Just getting the camera ready…');

        return true;
    }

    return screen.busy;
}

/* ── Framing guide ────────────────────────────────────────────────────────
 *
 * A dashed oval over the preview, and one line of advice under it.
 *
 * Enrolment quality decides whether somebody can clock in for months
 * afterwards, and the two ways it goes wrong — a face too small to describe,
 * or half out of frame — are invisible to the person holding the phone. A
 * manager gets no feedback at all until a punch is flagged weeks later.
 *
 * The loop runs the DETECTOR only, not the landmark or recognition nets, so
 * it costs a fraction of a real capture. It yields while a capture is in
 * flight so the two never contend for the main thread.
 */

/** How wide a face should be, as a fraction of the frame. */
const FRAME_MIN_WIDTH = 0.28;
const FRAME_MAX_WIDTH = 0.62;

/** How far off-centre a face may sit before it is worth mentioning. */
const FRAME_MAX_OFFSET_X = 0.16;
const FRAME_MAX_OFFSET_Y = 0.18;

const GUIDE_COLOURS = {
    idle: 'rgba(255,255,255,0.55)',
    poor: '#fbbf24',
    good: '#34d399',
};

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * Which camera this device should use.
 *
 * localStorage, not the session: a wall-mounted tablet facing the counter
 * wants the rear camera every time, and that is a property of the DEVICE,
 * not of whoever is signed in on it.
 */
function preferredFacing() {
    try {
        return localStorage.getItem(FACING_KEY) === FACING_ENVIRONMENT
            ? FACING_ENVIRONMENT
            : FACING_USER;
    } catch (error) {
        // Private browsing can refuse storage outright.
        return FACING_USER;
    }
}

function rememberFacing(facing) {
    try {
        localStorage.setItem(FACING_KEY, facing);
    } catch (error) {
        // Not remembering is survivable; failing to switch is not.
    }
}

/** Swap between the front and rear camera, restarting the preview. */
async function flipCamera() {
    const next = preferredFacing() === FACING_USER ? FACING_ENVIRONMENT : FACING_USER;

    rememberFacing(next);
    note(`flip:${next === FACING_USER ? 'front' : 'back'}`);

    stopGuide();
    screen.camera?.stop();
    screen.camera = null;
    screen.faceAvailable = false;

    el(screen.dom?.overlay)?.classList.remove('hidden');

    await startScreen();
}

/**
 * Judge one detection against the frame.
 *
 * @returns {{state: 'idle'|'poor'|'good', hint: string}}
 */
function assessFraming(detection, video) {
    if (! detection) {
        return { state: 'idle', hint: 'Looking for a face…' };
    }

    const width  = video.videoWidth || 1;
    const height = video.videoHeight || 1;
    const box    = detection.box;

    const widthRatio = box.width / width;
    // The preview is mirrored, but a distance from the centre is symmetric,
    // so the offsets below need no un-mirroring.
    const offsetX = Math.abs((box.x + box.width / 2) / width - 0.5);
    const offsetY = Math.abs((box.y + box.height / 2) / height - 0.5);

    if (widthRatio < FRAME_MIN_WIDTH) {
        return { state: 'poor', hint: 'Move closer' };
    }

    if (widthRatio > FRAME_MAX_WIDTH) {
        return { state: 'poor', hint: 'Move back a little' };
    }

    if (offsetX > FRAME_MAX_OFFSET_X || offsetY > FRAME_MAX_OFFSET_Y) {
        return { state: 'poor', hint: 'Centre your face in the oval' };
    }

    return { state: 'good', hint: 'Good — hold still' };
}

/** Set the advice line and the oval colour together. */
function setGuideHint(hint, state = 'idle') {
    paintGuide({ state, hint });
}

function paintGuide({ state, hint }) {
    const oval = el(screen.dom?.guide);
    const line = el(screen.dom?.hint);

    if (oval) {
        // An SVG ellipse, so stroke rather than border.
        oval.style.stroke = GUIDE_COLOURS[state];
        // Solid once framed: the dashes say "aim here", a solid outline says
        // "you are there", which reads at a glance without being read.
        oval.style.strokeDasharray = state === 'good' ? 'none' : '7 6';
    }

    if (line) {
        line.textContent = hint;
        line.style.color = state === 'good' ? '#047857' : '';
    }
}

/**
 * The arc around the oval — "scan progress in the face frame".
 *
 * Drawn as a stroked ellipse whose dash offset is wound back as the fraction
 * rises, so the ring fills clockwise from the top. getTotalLength() is asked
 * for the real perimeter rather than approximating one, because an ellipse's
 * circumference has no closed form and a wrong constant shows up as a ring
 * that never quite closes.
 *
 * @param {number} fraction 0 to 1
 */
function paintRing(fraction) {
    const ring = el(screen.dom?.ring);

    if (! ring) return;

    const length = ring.getTotalLength?.() || 0;

    if (! length) return;

    const clamped = Math.max(0, Math.min(1, fraction));

    ring.style.strokeDasharray  = `${length}`;
    ring.style.strokeDashoffset = `${length * (1 - clamped)}`;
    ring.style.opacity = clamped > 0 ? '1' : '0';
}

/**
 * The guided enrolment scan.
 *
 * Walks the five poses, capturing each the moment it is held. This is where
 * a multi-angle ceremony earns its keep: enrolment happens once, and the
 * descriptors it produces decide whether somebody can clock in for months
 * afterwards. One head-on shot stops working the week they grow a beard.
 *
 * A pose that cannot be reached is SKIPPED, not retried forever. Four good
 * angles beat a scan somebody abandoned halfway through, and the manual
 * Capture button is still there for whatever is missing.
 */
async function runScan() {
    if (screen.scanning || screen.dom?.key !== 'enrol') return;

    const form = document.getElementById('enrol-form');

    if (! form?.dataset.employee || ! screen.faceAvailable) return;

    screen.scanning = true;
    stopGuide();

    let taken = 0;
    let skipped = 0;

    try {
        for (const [index, poseKey] of SCAN_SEQUENCE.entries()) {
            if (! screen.scanning) break;

            setStatus(`Step ${index + 1} of ${SCAN_SEQUENCE.length}`);
            setGuideHint(POSES[poseKey].label, 'poor');

            const held = await screen.camera.waitForPose(poseKey, (fraction, hint) => {
                // The ring shows this pose's hold, offset by the steps done —
                // so it sweeps once across the whole scan, not five times.
                paintRing((index + fraction) / SCAN_SEQUENCE.length);

                if (hint) setGuideHint(hint, fraction > 0 ? 'good' : 'poor');
            });

            if (! held) {
                skipped++;
                continue;
            }

            const saved = await captureAndSave();

            if (saved) {
                taken++;
            } else {
                // Storage refused it — a full slate, or a face it could not
                // read. Either way, pressing on would repeat the failure.
                break;
            }

            paintRing((index + 1) / SCAN_SEQUENCE.length);
        }
    } finally {
        screen.scanning = false;
        paintRing(0);
        setGuideHint('', 'idle');

        setStatus(taken
            ? `Scan finished — ${taken} added${skipped ? `, ${skipped} skipped` : ''}.`
            : 'Scan finished without a usable capture. Try the Capture button.');

        runGuide();
    }
}

async function runGuide() {
    if (screen.guiding || ! screen.dom?.guide) return;

    screen.guiding = true;

    try {
        const api = await loadModels();
        const options = new api.TinyFaceDetectorOptions({
            inputSize: DETECTOR_INPUT_SIZE,
            scoreThreshold: DETECTOR_SCORE_THRESHOLD,
        });

        while (screen.guiding) {
            const video = el(screen.dom?.guide) ? screen.videoNode : null;

            // Yield entirely while a capture runs — they would otherwise
            // fight over the main thread and both get slower.
            if (! video || screen.busy || ! screen.camera?.playing) {
                await sleep(300);
                continue;
            }

            // Landmarks as well as a box. The guide used to run on the box
            // alone, so anything the detector fired on was reported as a
            // well-framed face — which is what said "hold still" to an open
            // hand. The landmark net is small and this loop runs about four
            // times a second; it comfortably affords the second opinion.
            const found = await api.detectSingleFace(video, options).withFaceLandmarks();
            const real  = found ? looksLikeFace(found.landmarks) : { ok: false };

            paintGuide(assessFraming(real.ok ? found.detection : null, video));

            // ~4 a second. Fast enough to feel live, slow enough to leave the
            // phone able to do anything else.
            await sleep(220);
        }
    } catch (error) {
        // The guide is a convenience. Losing it must never stop a capture.
        screen.guiding = false;
    }
}

function stopGuide() {
    screen.guiding = false;
}

/** Fill the capture progress bar. */
function paintProgress(count) {
    const wrap = el(screen.dom?.progress);

    if (! wrap) return;

    const needed = Number(wrap.dataset.needed || 3);

    wrap.querySelectorAll('[data-slot]').forEach((slot, index) => {
        const filled = index < count;
        slot.style.backgroundColor = filled
            ? (index < needed ? '#0d5f61' : '#6ee7b7')
            : '#e5e7eb';
    });

    const label = wrap.querySelector('[data-progress-label]');

    if (label) {
        label.textContent = count >= needed
            ? `${count} on file — enough to clock in with`
            : `${count} of ${needed} needed`;
    }
}

/**
 * Capture, locate, and hand the observations to Livewire.
 *
 * $wire is passed in from the page's @script block — the one thing this
 * module cannot get for itself.
 */
async function performPunch(wire, intent = 'shift') {
    if (notReady()) return;

    screen.busy = true;

    try {
        // Requested first and awaited last: the GPS fix is the slowest part
        // and has no reason to queue behind the camera work.
        const positionPromise = currentPosition();

        let face = null;

        if (screen.faceAvailable) {
            // ONE pose, chosen at random each time. A single gesture is all a
            // doorway will tolerate twice a day — the five-step ceremony
            // belongs to enrolment, which happens once. Random matters: a
            // fixed challenge could be pre-recorded, a varying one cannot.
            const challenge = PUNCH_CHALLENGES[Math.floor(Math.random() * PUNCH_CHALLENGES.length)];

            setStatus(POSES[challenge].label);
            const held = await screen.camera.waitForPose(challenge, (fraction, hint) => {
                paintRing(fraction);
                if (hint) setStatus(hint);
            });

            paintRing(held ? 1 : 0);

            // A pose that never came is not a refusal. Capture anyway and let
            // the manager see the selfie — stranding somebody outside because
            // they could not turn their head far enough is the wrong trade.
            setStatus('Hold still…');
            face = await screen.camera.capture();

            paintRing(0);

            if (! face) {
                setStatus(captureProblem(screen.camera));

                return;
            }
        }

        setStatus('Checking where you are…');
        const position = await positionPromise;

        setStatus('Recording…');

        await wire.submit({
            latitude:   position?.latitude ?? null,
            longitude:  position?.longitude ?? null,
            accuracy:   position?.accuracy ?? null,
            descriptor: face?.descriptor ?? null,
            // A still is kept even when the descriptor could not be computed —
            // a manager reviewing a flagged punch would much rather have a
            // photo than a shrug.
            selfie:     face?.selfie ?? (screen.camera?.stream ? screen.camera.still() : null),
            device:     navigator.userAgentData?.platform ?? null,
        }, intent);

        setStatus('');
    } catch (error) {
        setStatus('Something went wrong. Try again.');
    } finally {
        screen.busy = false;
    }
}

/**
 * One enrolment capture, posted straight to the server.
 *
 * A plain fetch rather than a Livewire action: enrolment gates the entire
 * feature — nobody can clock in with a face check until their face is on
 * file — and it was failing silently because the save went through $wire on
 * a screen where Livewire had not started. This works either way.
 */
async function performEnrolCapture() {
    if (notReady()) return;

    if (! screen.faceAvailable) {
        setStatus('Face model still loading — a moment.');

        return;
    }

    if (! document.getElementById('enrol-form')?.dataset.employee) {
        setStatus('Pick somebody from the list first.');

        return;
    }

    setStatus('Hold still…');
    await captureAndSave();
}

/**
 * Take one frame and store it.
 *
 * Shared by the manual button and the guided scan, so the two cannot drift
 * on what a capture is or on how a failure reads.
 *
 * @returns {Promise<boolean>} whether it was saved.
 */
async function captureAndSave() {
    const form = document.getElementById('enrol-form');

    if (! form?.dataset.employee || screen.busy) return false;

    const employeeId = form.dataset.employee;

    screen.busy = true;

    try {
        const face = await screen.camera.capture();

        if (! face) {
            setStatus(captureProblem(screen.camera));

            return false;
        }

        const response = await fetch(form.dataset.endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({
                employee_id: employeeId,
                descriptor: face.descriptor,
                photo: face.selfie,
            }),
        });

        const result = await response.json().catch(() => ({}));

        if (! response.ok) {
            setStatus(result.message || 'Capture failed. Try again.');

            return false;
        }

        appendCapture(result);
        paintProgress(result.count);

        setStatus(result.enough
            ? `Saved — ${result.count} on file. That is enough to clock in with.`
            : `Saved — ${result.count} on file.`);

        return true;
    } catch (error) {
        setStatus('Capture failed. Try again.');

        return false;
    } finally {
        screen.busy = false;
    }
}

/**
 * Show the capture just taken without reloading.
 *
 * A reload would restart the camera and its permission dance between every
 * shot, and a manager takes three or four in a row.
 */
function appendCapture(result) {
    const list = document.getElementById('enrol-captures');

    if (! list || ! result?.photo_url) return;

    document.getElementById('enrol-empty')?.classList.add('hidden');

    const figure = document.createElement('div');
    figure.className = 'relative';

    const image = document.createElement('img');
    image.src = result.photo_url;
    image.alt = 'Enrolment capture';
    image.className = 'w-full aspect-square object-cover rounded-lg border border-gray-200';
    figure.appendChild(image);

    // The same form the server renders, built here so a capture taken two
    // seconds ago can be thrown away without reloading — which is when you
    // most want to, because you have just seen it was blurred.
    if (result.delete_url) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = result.delete_url;
        form.className = 'absolute -top-1.5 -right-1.5';
        form.addEventListener('submit', (event) => {
            if (! confirm('Delete this capture?')) event.preventDefault();
        });

        for (const [name, value] of [
            ['_token', document.querySelector('meta[name="csrf-token"]')?.content || ''],
            ['_method', 'DELETE'],
        ]) {
            const field = document.createElement('input');
            field.type = 'hidden';
            field.name = name;
            field.value = value;
            form.appendChild(field);
        }

        const remove = document.createElement('button');
        remove.type = 'submit';
        remove.setAttribute('aria-label', 'Delete capture');
        remove.className = 'w-6 h-6 rounded-full bg-white border border-gray-300 '
            + 'text-gray-600 text-sm leading-none shadow-sm hover:bg-danger-50 hover:text-danger-700';
        remove.textContent = '\u00d7';

        form.appendChild(remove);
        figure.appendChild(form);
    }

    list.appendChild(figure);

    const counter = document.getElementById('enrol-count');
    if (counter) counter.textContent = result.count;
}

function boot() {
    // Proves to anyone reading the DOM that this module evaluated at all.
    document.documentElement.dataset.clockJs = 'ok';

    const found = findScreen();

    if (! found) return;

    note('boot');
    screen.bootedAt ||= Date.now();

    // A different screen than last time (Livewire navigation): drop the old
    // camera so the new elements get a fresh one.
    if (screen.dom && screen.dom.key !== found.key) {
        screen.camera?.stop();
        screen.camera = null;
        screen.faceAvailable = false;
    }

    screen.dom = found;

    // A <video> that has been REPLACED — not merely re-rendered — carries no
    // stream, however healthy the ClockCamera pointing at the old node was.
    // Livewire discarding and rebuilding this component is exactly how the
    // camera died mid-shift, so identity is checked rather than assumed.
    //
    // The response used to be to stop the camera, drop it, and clear the
    // start lock. That threw away a working stream to ask for it again, and
    // clearing the lock mid-flight let a second getUserMedia start while the
    // first was still outstanding — two pending prompts, neither pointed at
    // an element still on the page. Re-pointing the camera does the same job
    // with no prompt, no teardown, and no second call.
    const video = el(found.video);

    if (screen.videoNode && screen.videoNode !== video) {
        note('swap');
        screen.camera?.attach(video, el(found.canvas));
    }

    screen.videoNode = video;

    // Everything below is delegated and bound once. A handler attached to a
    // specific button stops firing the moment that button is replaced, which
    // is the failure this whole block exists to survive.
    if (! document.body.dataset.clockBound) {
        document.body.dataset.clockBound = '1';

        document.addEventListener('click', (event) => {
            // Cheap, and it costs nothing when the preview is already live:
            // this is the user gesture Safari wants before it will play.
            screen.camera?.resume();

            if (event.target.closest('[data-clock-scan]')) {
                if (screen.scanning) {
                    screen.scanning = false;
                    setStatus('Scan stopped.');
                } else {
                    runScan();
                }

                return;
            }

            if (event.target.closest('[data-clock-flip]')) {
                flipCamera();

                return;
            }

            if (event.target.closest('#enrol-capture')) {
                performEnrolCapture();

                return;
            }

            // A retry from a real tap is the call iOS reliably prompts for,
            // so the overlay is the recovery path for every camera failure.
            if (event.target.closest('#clock-camera-overlay')
                || event.target.closest('#enrol-overlay')) {
                startScreen();
            }
        });

        // Re-boot whenever the camera element is swapped out from under us.
        // Cheaper than guessing which framework hook fired, and it holds for
        // any cause — a morph, a navigation, a full re-render.
        new MutationObserver(() => {
            const current = SCREENS.map((s) => el(s.video)).find(Boolean) || null;

            if (current && current !== screen.videoNode) boot();
        }).observe(document.body, { childList: true, subtree: true });
    }

    startScreen();

    // A live line rather than two snapshots. The old pair of timers fired at
    // six and twenty seconds, so what somebody photographed was the state at
    // one of two arbitrary instants — which is how a report arrived showing a
    // combination that no single moment of the code can actually produce.
    // Refreshing keeps the line and the screen telling the same story.
    if (! window.__servoraClockDiag) {
        window.__servoraClockDiag = setInterval(showDiagnostics, 1000);
    }
}

/*
 * Anything that escaped a promise. startScreen() now catches its own, but a
 * rejection from a listener or a timer still lands here — and on a screen
 * with no console attached, unrecorded is the same as invisible.
 */
window.addEventListener('unhandledrejection', (event) => {
    note(`reject:${event.reason?.name || event.reason?.message?.slice(0, 20) || '?'}`);
});

window.addEventListener('error', (event) => {
    note(`error:${event.message?.slice(0, 24) || '?'}`);
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}

// Livewire swaps the DOM without a page load.
document.addEventListener('livewire:navigated', boot);

// Free the camera on the way out, so the indicator light goes off.
document.addEventListener('livewire:navigating', () => {
    stopGuide();
    screen.camera?.stop();
});

/**
 * Exposed on window because the inline Livewire @script blocks are not part
 * of the module graph and cannot import from it. They need exactly one
 * thing: somewhere to hand $wire.
 */
window.ServoraClock = {
    ClockCamera, currentPosition, loadModels,
    screen, startScreen, performPunch, performEnrolCapture, showDiagnostics,
    assessFraming, paintProgress, flipCamera, preferredFacing,
    runScan, estimatePose, POSES, SCAN_SEQUENCE,
};
