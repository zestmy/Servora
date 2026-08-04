<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Why the queue worker keeps exiting.
 *
 * Written after servora-queue was found restarting seven times a minute,
 * exiting CLEANLY each time — ExecMainStatus=0, "Deactivated successfully".
 * A clean exit rules out a crash and points at the one thing that asks a
 * healthy worker to stop: the restart signal.
 *
 * `queue:restart` writes a timestamp to the CACHE. A worker reads it once at
 * startup and again on every loop, and stops the moment the two differ. So a
 * value that keeps changing — or a cache that keeps losing and regaining it —
 * makes every worker quit within seconds of starting, forever, with no error
 * anywhere.
 *
 * Reads only. Safe to run on production at any time.
 */
class QueueSignal extends Command
{
    protected $signature = 'queue:signal {--watch=0 : Re-read this many times, a second apart}';

    protected $description = 'Show the queue restart signal that tells workers to stop';

    /** The key Laravel writes when `queue:restart` is called. */
    private const KEY = 'illuminate:queue:restart';

    public function handle(): int
    {
        $this->line('cache store : ' . config('cache.default'));
        $this->line('queue conn  : ' . config('queue.default'));
        $this->line('server time : ' . now()->toDateTimeString() . '  (unix ' . now()->getTimestamp() . ')');

        $readings = max(1, (int) $this->option('watch'));
        $seen     = [];

        for ($i = 0; $i < $readings; $i++) {
            $value = Cache::get(self::KEY);
            $seen[] = $value;

            $this->line(sprintf(
                'signal      : %s%s',
                var_export($value, true),
                is_numeric($value)
                    ? '  (' . \Carbon\Carbon::createFromTimestamp((int) $value)->toDateTimeString() . ')'
                    : ''
            ));

            if ($i < $readings - 1) {
                sleep(1);
            }
        }

        // The finding, stated rather than left for the reader to spot.
        if (count(array_unique($seen, SORT_REGULAR)) > 1) {
            $this->error('The signal CHANGED between reads — that is what stops every worker.');

            return self::FAILURE;
        }

        $value = $seen[0];

        if ($value === null) {
            $this->info('No restart signal set. Workers are not being asked to stop by this.');

            return self::SUCCESS;
        }

        if (is_numeric($value) && (int) $value > now()->getTimestamp()) {
            $this->error('The signal is in the FUTURE — every worker will stop on its first loop.');
            $this->line('Clear it with:  php artisan queue:clear-signal');

            return self::FAILURE;
        }

        $this->info('Signal is in the past and stable, which is normal.');

        return self::SUCCESS;
    }
}
