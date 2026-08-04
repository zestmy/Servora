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

/**
 * Exposed on window because the punch screen drives this from an inline
 * Livewire @script block, which is not part of the module graph and so
 * cannot import from it.
 */
window.ServoraClock = { ClockCamera, currentPosition, loadModels };

