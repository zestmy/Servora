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

const MODEL_URL = document.querySelector('meta[name="face-models-url"]')?.content || '/face-models';

/** Detector input size. 320 is the smallest that reliably finds a face at
 *  arm's length, and it runs about four times faster than 608 on the mid-range
 *  Android phones this is used on. */
const DETECTOR_INPUT_SIZE = 320;

/** Below this the "face" is usually a shadow or a pattern on a wall. */
const DETECTOR_SCORE_THRESHOLD = 0.45;

/**
 * Eye aspect ratio under which an eye counts as closed. Derived from the
 * 68-point landmark model, where a comfortably open eye sits around 0.30 and
 * a closed one near 0.10.
 */
const BLINK_EAR = 0.19;

/** Give up waiting for a blink after this and capture anyway, saying so.
 *  A hard requirement would strand anyone in bad light at the door. */
const BLINK_TIMEOUT_MS = 8000;

let faceapi = null;
let modelsReady = null;

/** Load the library and its weights once per page. */
async function loadModels() {
    if (modelsReady) return modelsReady;

    modelsReady = (async () => {
        faceapi = await import('@vladmandic/face-api');

        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
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

/** Vertical-to-horizontal ratio of one eye's six landmark points. */
function eyeAspectRatio(eye) {
    const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
    const horizontal = dist(eye[0], eye[3]);

    if (horizontal === 0) return 1;

    return (dist(eye[1], eye[5]) + dist(eye[2], eye[4])) / (2 * horizontal);
}

export class ClockCamera {
    constructor({ video, canvas, onStatus }) {
        this.video = video;
        this.canvas = canvas;
        this.onStatus = onStatus || (() => {});
        this.stream = null;
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

        try {
            this.stream = await Promise.race([
                // facingMode 'user' rather than exact: a kitchen tablet
                // mounted on a wall may only have one camera, and exact would
                // fail outright on it.
                navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
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

        this.video.srcObject = this.stream;
        this.video.play?.().catch(() => {});
    }

    stop() {
        this.stream?.getTracks().forEach((track) => track.stop());
        this.stream = null;
        this.video.srcObject = null;
    }

    /**
     * Watch until both eyes close in one frame, or the timeout runs out.
     *
     * @returns {Promise<boolean>} whether a blink was actually seen.
     */
    async waitForBlink() {
        const api = await loadModels();
        const options = new api.TinyFaceDetectorOptions({
            inputSize: DETECTOR_INPUT_SIZE,
            scoreThreshold: DETECTOR_SCORE_THRESHOLD,
        });

        const deadline = Date.now() + BLINK_TIMEOUT_MS;
        let sawOpen = false;

        while (Date.now() < deadline) {
            const result = await api
                .detectSingleFace(this.video, options)
                .withFaceLandmarks();

            if (result) {
                const left = eyeAspectRatio(result.landmarks.getLeftEye());
                const right = eyeAspectRatio(result.landmarks.getRightEye());
                const closed = left < BLINK_EAR && right < BLINK_EAR;

                // Open-then-closed, not merely closed: someone who walks up
                // mid-blink, or a photo of someone with narrow eyes, would
                // otherwise pass on the very first frame.
                if (closed && sawOpen) {
                    return true;
                }

                if (! closed) {
                    sawOpen = true;
                }
            }

            this.onStatus(result ? 'Blink once' : 'Looking for your face…');

            await new Promise((r) => setTimeout(r, 120));
        }

        return false;
    }

    /**
     * A descriptor and a still, or null if no face could be read.
     *
     * @returns {Promise<{descriptor: number[], selfie: string, score: number}|null>}
     */
    async capture() {
        const api = await loadModels();
        const options = new api.TinyFaceDetectorOptions({
            inputSize: DETECTOR_INPUT_SIZE,
            scoreThreshold: DETECTOR_SCORE_THRESHOLD,
        });

        // Best of three. A single frame catches motion blur often enough to
        // be annoying, and the detector's own score is a decent proxy for
        // which frame is worth keeping.
        let best = null;

        for (let i = 0; i < 3; i++) {
            const result = await api
                .detectSingleFace(this.video, options)
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (result && (! best || result.detection.score > best.detection.score)) {
                best = result;
            }

            if (i < 2) await new Promise((r) => setTimeout(r, 100));
        }

        if (! best) return null;

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

        // Un-mirror. The preview is flipped so it behaves like a mirror,
        // which is what people expect of a front camera — but the stored
        // evidence should show the person the way a witness would see them.
        ctx.save();
        ctx.translate(width, 0);
        ctx.scale(-1, 1);
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

/* ── Punch screen controller ──────────────────────────────────────────────
 *
 * Deliberately driven from this module rather than from the Livewire
 * @script block on the page.
 *
 * The camera used to be started from @script, which meant a Livewire that
 * failed to boot took the preview down with it — and the two failures look
 * identical from the doorway: a dark rectangle and nothing happening. This
 * module is a plain <script type="module"> and runs whatever Livewire does,
 * so the preview comes up on its own and only the final submit needs $wire.
 */

const screen = {
    camera: null,
    faceAvailable: false,
    starting: false,
    error: null,
};

const el = (id) => document.getElementById(id);

const setOverlay = (text) => {
    const node = el('clock-camera-message');
    if (node) node.textContent = text;
};

const setStatus = (text) => {
    const node = el('clock-status');
    if (node) node.textContent = text || '';
};

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
 * Hidden while everything works, and revealed only once something has
 * plainly gone wrong — at which point "it's broken" needs to become a fact
 * somebody can act on without a laptop and a debugger.
 */
function showDiagnostics() {
    const node = el('clock-diagnostics');
    if (! node) return;

    const camera = screen.camera?.stream ? 'on' : (screen.error || 'off');

    node.textContent = `app ${window.Livewire ? 'ok' : 'NOT STARTED'}`
        + ` · camera ${camera}`
        + ` · face ${screen.faceAvailable ? 'ok' : 'off'}`;

    if (! screen.camera?.stream || ! window.Livewire) {
        node.classList.remove('hidden');
    }
}

async function startScreen() {
    const video  = el('clock-video');
    const canvas = el('clock-canvas');

    // Not the punch screen (history, login) — nothing to do.
    if (! video || ! canvas) return;

    if (screen.starting || screen.camera?.stream) return;

    screen.starting = true;
    screen.error    = null;
    setOverlay('Starting camera…');

    screen.camera ??= new ClockCamera({ video, canvas, onStatus: setStatus });

    try {
        await screen.camera.start();
        el('clock-camera-overlay')?.classList.add('hidden');
    } catch (error) {
        screen.error = error?.name || 'failed';
        setOverlay(cameraProblem(error));
        screen.starting = false;
        showDiagnostics();

        return;
    }

    setStatus('Getting the face check ready…');

    try {
        await loadModels();
        screen.faceAvailable = true;
        setStatus('');
    } catch (error) {
        // The weights are ~6.5MB; a bad connection is the usual cause.
        setStatus('Face check unavailable — your punch will be sent for review.');
    }

    screen.starting = false;
}

/**
 * Capture, locate, and hand the observations to Livewire.
 *
 * $wire is passed in from the page's @script block — the one thing this
 * module cannot get for itself.
 */
async function performPunch(wire) {
    if (screen.starting) {
        setStatus('Just getting the camera ready…');

        return;
    }

    if (screen.busy) return;
    screen.busy = true;

    try {
        // Requested first and awaited last: the GPS fix is the slowest part
        // and has no reason to queue behind the camera work.
        const positionPromise = currentPosition();

        let face = null;

        if (screen.faceAvailable) {
            setStatus('Blink once');
            await screen.camera.waitForBlink();

            setStatus('Hold still…');
            face = await screen.camera.capture();

            if (! face) {
                setStatus('Could not see your face. Try again in better light.');

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
        });

        setStatus('');
    } catch (error) {
        setStatus('Something went wrong. Try again.');
    } finally {
        screen.busy = false;
    }
}

function boot() {
    // Proves to anyone reading the DOM that this module evaluated at all.
    document.documentElement.dataset.clockJs = 'ok';

    // A retry from a real tap is the call iOS reliably prompts for, so the
    // overlay is the recovery path for every camera failure.
    const overlay = el('clock-camera-overlay');
    if (overlay && ! overlay.dataset.bound) {
        overlay.dataset.bound = '1';
        overlay.addEventListener('click', startScreen);
    }

    startScreen();

    // Long enough that a slow model download is not mistaken for a fault.
    setTimeout(showDiagnostics, 15000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}

// Livewire swaps the DOM without a page load.
document.addEventListener('livewire:navigated', boot);

// Free the camera on the way out, so the indicator light goes off.
document.addEventListener('livewire:navigating', () => screen.camera?.stop());

/**
 * Exposed on window because the punch screen's inline Livewire @script block
 * is not part of the module graph and cannot import from it. It needs
 * exactly one thing: somewhere to hand $wire.
 */
window.ServoraClock = {
    ClockCamera, currentPosition, loadModels,
    screen, startScreen, performPunch, showDiagnostics,
};

