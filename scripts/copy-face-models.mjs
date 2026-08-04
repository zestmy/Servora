/**
 * Copy the face recognition weights out of node_modules and into
 * public/face-models/ so the browser can fetch them.
 *
 * The weights are not committed. They are ~6.5MB of binary that changes only
 * when the library version does, and a repository is a poor place to keep
 * build output — `npm ci` already pins exactly which bytes these are via the
 * lockfile, which is a stronger guarantee than a copy someone remembered to
 * update.
 *
 * Runs as part of `npm run build`, which deploy/update.sh already calls, so
 * a deploy picks them up with no extra step. If they are ever missing at
 * runtime the clock app says the face check is unavailable rather than
 * failing silently.
 */
import { cp, mkdir, access } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const source = join(root, 'node_modules', '@vladmandic', 'face-api', 'model');
const target = join(root, 'public', 'face-models');

/**
 * Only these three. The package ships age, gender and expression models too,
 * none of which this feature uses — and shipping a model that infers age and
 * gender from a staff photograph, for no purpose, is not a thing to do by
 * accident.
 */
const MODELS = [
    'tiny_face_detector_model',
    'face_landmark_68_model',
    'face_recognition_model',
];

try {
    await access(source);
} catch {
    console.warn(
        '[face-models] @vladmandic/face-api not installed — skipping.\n' +
        '              The clock-in app will run with the face check disabled.'
    );
    process.exit(0);
}

await mkdir(target, { recursive: true });

for (const model of MODELS) {
    for (const file of [`${model}-weights_manifest.json`, `${model}.bin`]) {
        await cp(join(source, file), join(target, file));
    }
}

console.log(`[face-models] copied ${MODELS.length} models to public/face-models`);
