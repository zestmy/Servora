<?php

namespace App\Support;

/**
 * Raising PHP's execution limit for a slow call, and putting it back.
 *
 * WHY THIS EXISTS AS A CLASS. Thirteen call sites across nine files had all
 * hand-rolled the same pair, and all thirteen had the same bug:
 *
 *     $previous = ini_get('max_execution_time');   // "0" on CLI
 *     set_time_limit(120);
 *     ...
 *     set_time_limit((int) $previous ?: 60);       // <- 0 is falsy, so 60
 *
 * `ini_get()` returns a STRING, and on CLI that string is "0", which means
 * "no limit". `(int) "0" ?: 60` evaluates to 60 — so the line that claims to
 * restore the previous limit actually imposes a NEW one, permanently, on a
 * process that had none.
 *
 * What that cost:
 *
 *   - Queue workers. A worker that renders one AI-extracted document picks up
 *     a 60-second ceiling it never had, and the next long job dies with
 *     "Maximum execution time of 60 seconds exceeded" nowhere near the code
 *     that caused it.
 *   - The test suite, which could not be run in one process at all. The first
 *     test touching any of these paths capped the run, and everything
 *     alphabetically after it died — the failure appeared in the Training
 *     screens, which have nothing to do with any of this.
 *
 * The fix is to restore the value VERBATIM. Zero is a legitimate previous
 * value and must be handed back as zero.
 */
final class ExecutionTime
{
    /**
     * Give the current request or command more time, and return what the
     * limit was so it can be handed back to restore().
     *
     * Returns the raw ini string rather than an int on purpose — restore()
     * needs to tell "0" (unlimited) apart from a failed read, and an int
     * cannot carry that difference.
     */
    public static function raise(int $seconds): string|false
    {
        $previous = ini_get('max_execution_time');

        set_time_limit($seconds);

        return $previous;
    }

    /**
     * Put the limit back exactly as it was.
     *
     * A `false` previous means ini_get() could not read the setting at all,
     * which is not the same as "it was zero" — in that case leave the limit
     * alone rather than guessing a number, since guessing is what caused the
     * bug this class replaces.
     */
    public static function restore(string|false $previous): void
    {
        if ($previous === false) {
            return;
        }

        set_time_limit((int) $previous);
    }

    /**
     * Run a callback with a raised limit and restore it however the callback
     * exits — return, throw, anything.
     *
     * Preferred over raise()/restore() for new code: the finally block is the
     * part that gets forgotten, and a path that returns early without
     * restoring leaves the raised limit behind on a pooled php-fpm worker.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public static function allow(int $seconds, callable $callback): mixed
    {
        $previous = self::raise($seconds);

        try {
            return $callback();
        } finally {
            self::restore($previous);
        }
    }
}
