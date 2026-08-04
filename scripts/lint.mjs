/**
 * Run ESLint if it is installed, and say so plainly if it is not.
 *
 * The lint step gates `npm run build`, which gates the deploy — see
 * eslint.config.js for the bug that earned it that position. But ESLint is a
 * devDependency, and a server that installs with NODE_ENV=production skips
 * those. Calling the binary directly there would fail the build with
 * "eslint: not found" and take production down over a missing linter, which
 * is a worse outcome than the bug the linter exists to catch.
 *
 * So: gate where the tooling exists, warn loudly where it does not.
 */
import { spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const root = dirname(dirname(fileURLToPath(import.meta.url)));

if (! existsSync(join(root, 'node_modules', 'eslint'))) {
    console.warn('[lint] ESLint is not installed — skipping. Undefined identifiers will NOT be caught.');
    process.exit(0);
}

const result = spawnSync(
    process.execPath,
    [join(root, 'node_modules', 'eslint', 'bin', 'eslint.js'), 'resources/js'],
    { cwd: root, stdio: 'inherit' },
);

process.exit(result.status ?? 1);
