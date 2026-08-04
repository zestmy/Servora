/**
 * Does a set of 68 landmarks describe a face at all?
 *
 * THE PROBLEM THIS SOLVES. The landmark net is not a classifier. Handed a
 * box, it fits 68 points inside it and returns them with no opinion on
 * whether there was a face there — so when the detector fired on an open hand
 * held up to the camera, nothing downstream had grounds to object. The
 * pipeline read the hand's "pose", tracked it through the scan, and built a
 * descriptor out of it.
 *
 * This is the missing objection. It is geometric rather than learned, on
 * purpose: it needs no weights, runs in microseconds inside a loop that
 * already has a neural net in it, and every rule it applies can be read and
 * argued with. The detector remains what decides whether a face is present —
 * this catches what it gets wrong.
 *
 * HOW IT AVOIDS BEING FRAGILE. Everything is measured in the face's own
 * frame: an axis from the eyes to the chin, and the eye line across it. No
 * rule refers to image up or image left, so head roll cannot break one, and
 * no rule depends on which eye face-api's getLeftEye() returns — a convention
 * that is easy to get backwards and would silently invert every test.
 *
 * All bands are wide. They exist to reject 0.1 and 4.0, not to grade a face,
 * and they have to survive the four scan poses, which turn and tilt the head
 * far enough to compress every distance here.
 */

/** Mean position of a group of landmarks. */
function centre(points) {
    return {
        x: points.reduce((sum, p) => sum + p.x, 0) / points.length,
        y: points.reduce((sum, p) => sum + p.y, 0) / points.length,
    };
}

/**
 * @param {object} landmarks a face-api FaceLandmarks68, or anything with the
 *                 same five accessors
 * @returns {{ok: boolean, why: string}} why is '' when ok
 */
export function looksLikeFace(landmarks) {
    const eyeL  = landmarks?.getLeftEye?.();
    const eyeR  = landmarks?.getRightEye?.();
    const mouth = landmarks?.getMouth?.();
    const nose  = landmarks?.getNose?.();
    const jaw   = landmarks?.getJawOutline?.();

    if (! eyeL?.length || ! eyeR?.length || ! mouth?.length || ! nose?.length || ! jaw?.length) {
        return { ok: false, why: 'landmarks' };
    }

    const midEye = centre([centre(eyeL), centre(eyeR)]);
    const eyeAxis = {
        x: centre(eyeR).x - centre(eyeL).x,
        y: centre(eyeR).y - centre(eyeL).y,
    };
    const eyeGap = Math.hypot(eyeAxis.x, eyeAxis.y);

    // Two eyes on top of each other is not a face, and it would make every
    // ratio below a division by roughly zero.
    if (! (eyeGap > 1)) {
        return { ok: false, why: 'eyes' };
    }

    // The chin is the middle of the jaw outline, and it is what defines DOWN
    // here. Deriving the direction from the landmarks themselves — rather
    // than assuming the perpendicular of the eye line points chinward — is
    // what makes this independent of eye ordering.
    const chin = jaw[Math.floor(jaw.length / 2)];
    const axis = { x: chin.x - midEye.x, y: chin.y - midEye.y };
    const faceLength = Math.hypot(axis.x, axis.y);

    if (! (faceLength > 1)) {
        return { ok: false, why: 'chin' };
    }

    const down = { x: axis.x / faceLength, y: axis.y / faceLength };

    // A face is about twice as far from eyes to chin as it is across the
    // eyes. Way outside that and the points are not laid out like a head.
    const chinDrop = faceLength / eyeGap;

    if (chinDrop < 0.7 || chinDrop > 4.5) {
        return { ok: false, why: 'length' };
    }

    // The eye line runs ACROSS the face, so it should be close to
    // perpendicular to the eyes-to-chin axis. This is the strongest single
    // rule: on a real head the angle barely moves, and on an arbitrary blob
    // it is whatever the fit happened to produce.
    const skew = Math.abs((eyeAxis.x / eyeGap) * down.x + (eyeAxis.y / eyeGap) * down.y);

    if (skew > 0.6) {
        return { ok: false, why: 'skew' };
    }

    // How far below the eye line something sits, in eye-separations.
    const below = (point) =>
        ((point.x - midEye.x) * down.x + (point.y - midEye.y) * down.y) / eyeGap;

    const mouthDrop = below(centre(mouth));

    if (mouthDrop < 0.45 || mouthDrop > 2.6) {
        return { ok: false, why: 'mouth' };
    }

    // Between the eyes and the mouth — not above one or below the other.
    const noseDrop = below(nose[6] || nose[nose.length - 1]);

    if (noseDrop < 0.1 || noseDrop > mouthDrop) {
        return { ok: false, why: 'nose' };
    }

    const jawSpan = Math.hypot(
        jaw[jaw.length - 1].x - jaw[0].x,
        jaw[jaw.length - 1].y - jaw[0].y,
    );

    // Head-on, the eyes span about 0.4 of the jaw's width. Turning the head
    // foreshortens the jaw faster than the eyes, so the ratio climbs; the
    // ceiling is set to allow a hard profile rather than to be tight.
    if (jawSpan > 1) {
        const spread = eyeGap / jawSpan;

        if (spread < 0.15 || spread > 1.3) {
            return { ok: false, why: 'proportions' };
        }
    }

    return { ok: true, why: '' };
}
