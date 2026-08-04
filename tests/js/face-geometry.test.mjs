/**
 * The face-plausibility gate.
 *
 * These tests exist because of a specific report: an open hand held up to the
 * clock-in camera was accepted as a face. The detector fired on it, the
 * landmark net dutifully fitted 68 points inside the box, and nothing in the
 * pipeline had grounds to disagree.
 *
 * The gate is pure geometry, which is exactly what makes it testable without
 * a camera, a model download, or a picture of anybody. Landmarks are just
 * points, so a face can be written down.
 *
 * The rotation cases carry the most weight. Every rule is meant to be
 * measured in the face's own frame, and the classic way to get that wrong is
 * to assume the perpendicular of the eye line points toward the chin — which
 * inverts the moment somebody tilts their head, or the moment face-api's
 * getLeftEye() turns out to mean the other eye. A gate that rejects tilted
 * heads would look like "the camera stopped recognising me" and would be
 * blamed on anything but this file.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { looksLikeFace } from '../../resources/js/face-geometry.js';

/** A ring of points around a centre, standing in for a landmark group. */
const blob = (x, y, r = 4) =>
    [0, 1, 2, 3, 4, 5].map((i) => ({
        x: x + r * Math.cos((i / 6) * 2 * Math.PI),
        y: y + r * Math.sin((i / 6) * 2 * Math.PI),
    }));

/**
 * A plain, head-on face, in the proportions a real one has: eyes 40 apart,
 * mouth about one eye-separation below them, chin about two, jaw 100 wide.
 */
function face(overrides = {}) {
    const jaw = Array.from({ length: 17 }, (_, i) => {
        const t = (i - 8) / 8;                     // -1 .. 1

        return { x: t * 50, y: 60 - 25 * t * t };  // a curve, chin lowest
    });

    return {
        getLeftEye:     () => blob(-20, -10),
        getRightEye:    () => blob(20, -10),
        getNose:        () => Array.from({ length: 9 }, (_, i) => ({ x: 0, y: -4 + i * 1.5 })),
        getMouth:       () => blob(0, 30, 8),
        getJawOutline:  () => jaw,
        ...overrides,
    };
}

/** The same landmarks, rotated about the origin. */
function rotated(landmarks, degrees) {
    const a = (degrees * Math.PI) / 180;
    const spin = (p) => ({
        x: p.x * Math.cos(a) - p.y * Math.sin(a),
        y: p.x * Math.sin(a) + p.y * Math.cos(a),
    });
    const map = (fn) => () => fn().map(spin);

    return {
        getLeftEye:    map(landmarks.getLeftEye),
        getRightEye:   map(landmarks.getRightEye),
        getNose:       map(landmarks.getNose),
        getMouth:      map(landmarks.getMouth),
        getJawOutline: map(landmarks.getJawOutline),
    };
}

test('an ordinary face passes', () => {
    assert.deepEqual(looksLikeFace(face()), { ok: true, why: '' });
});

test('a face passes at any head roll', () => {
    // Including upside down. Nothing here should refer to image up.
    for (const angle of [-180, -90, -45, -20, -5, 5, 20, 45, 90, 180]) {
        const verdict = looksLikeFace(rotated(face(), angle));

        assert.equal(verdict.ok, true, `rejected at ${angle}deg as "${verdict.why}"`);
    }
});

test('swapping the eyes changes nothing', () => {
    // getLeftEye() vs getRightEye() is a convention that is easy to have
    // backwards, and a gate that depended on it would reject every face.
    const base = face();
    const swapped = face({
        getLeftEye:  base.getRightEye,
        getRightEye: base.getLeftEye,
    });

    assert.equal(looksLikeFace(swapped).ok, true);
});

test('a face turned to the side still passes', () => {
    // Yaw foreshortens the jaw and slides the nose across, which is exactly
    // what the scan asks people to do four times.
    const squashed = face({
        getJawOutline: () => Array.from({ length: 17 }, (_, i) => {
            const t = (i - 8) / 8;

            return { x: t * 20, y: 60 - 25 * t * t };   // jaw span 40, not 100
        }),
        getNose: () => Array.from({ length: 9 }, (_, i) => ({ x: 12, y: -4 + i * 1.5 })),
    });

    assert.equal(looksLikeFace(squashed).ok, true, looksLikeFace(squashed).why);
});

test('missing landmark groups are refused', () => {
    assert.equal(looksLikeFace(null).why, 'landmarks');
    assert.equal(looksLikeFace({}).why, 'landmarks');
    assert.equal(looksLikeFace(face({ getMouth: () => [] })).why, 'landmarks');
});

test('a mouth above the eyes is refused', () => {
    // The single most face-unlike arrangement there is, and the one a gate
    // with an inverted axis would wave straight through.
    assert.equal(looksLikeFace(face({ getMouth: () => blob(0, -40, 8) })).ok, false);
});

test('a mouth too far below the eyes is refused', () => {
    assert.equal(looksLikeFace(face({ getMouth: () => blob(0, 400, 8) })).why, 'mouth');
});

test('eyes on top of each other are refused', () => {
    assert.equal(looksLikeFace(face({ getRightEye: () => blob(-20, -10) })).why, 'eyes');
});

test('an eye line running along the face rather than across it is refused', () => {
    // One eye near the chin: the two axes stop being perpendicular, which is
    // the strongest signal that these points are not laid out like a head.
    assert.equal(looksLikeFace(face({ getRightEye: () => blob(0, 45) })).ok, false);
});

test('a nose outside the eyes-to-mouth span is refused', () => {
    const above = face({ getNose: () => Array.from({ length: 9 }, () => ({ x: 0, y: -60 })) });
    const below = face({ getNose: () => Array.from({ length: 9 }, () => ({ x: 0, y: 200 })) });

    assert.equal(looksLikeFace(above).why, 'nose');
    assert.equal(looksLikeFace(below).why, 'nose');
});

test('a face crammed into a sliver of its jaw is refused', () => {
    // Eyes 40 apart inside a jaw 800 wide: the proportions of no head.
    const wide = face({
        getJawOutline: () => Array.from({ length: 17 }, (_, i) => ({ x: ((i - 8) / 8) * 400, y: 60 })),
    });

    assert.equal(looksLikeFace(wide).why, 'proportions');
});

test('landmarks collapsed to a point are refused', () => {
    const flat = {
        getLeftEye:    () => blob(0, 0, 0),
        getRightEye:   () => blob(0, 0, 0),
        getNose:       () => [{ x: 0, y: 0 }],
        getMouth:      () => blob(0, 0, 0),
        getJawOutline: () => Array.from({ length: 17 }, () => ({ x: 0, y: 0 })),
    };

    assert.equal(looksLikeFace(flat).ok, false);
});

test('the reason is always a short tag, never a sentence', () => {
    // It is logged and shown in the diagnostic line, not read aloud.
    const cases = [null, {}, face({ getMouth: () => blob(0, 400, 8) }), face()];

    for (const input of cases) {
        const { why } = looksLikeFace(input);

        assert.match(why, /^[a-z]*$/);
    }
});
