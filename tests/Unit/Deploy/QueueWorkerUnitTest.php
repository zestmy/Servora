<?php

namespace Tests\Unit\Deploy;

use PHPUnit\Framework\TestCase;

/**
 * Guards the queue worker unit against the flag that broke it.
 *
 * `--max-time` on queue:work is not a harmless recycle knob. Worker::pauseWorker()
 * — the branch that runs while `artisan down` is in effect — calls
 * stopIfNecessary() without $startTime, so it defaults to 0, and the check is:
 *
 *     $options->maxTime && hrtime(true) / 1e9 - $startTime >= $options->maxTime
 *
 * hrtime(true) counts from boot on Linux, so with $startTime at 0 that reads
 * the machine's uptime. Any box up longer than --max-time exits the worker on
 * its first paused loop, silently, with status 0, forever.
 *
 * This is cheap to reintroduce — it looks like exactly the flag you would add
 * to recycle a long-running worker — so it is worth a test rather than only a
 * comment. RuntimeMaxSec is the replacement and is asserted here too, because
 * dropping --max-time without it would quietly give up hourly recycling.
 */
class QueueWorkerUnitTest extends TestCase
{
    private function unit(): string
    {
        $path = dirname(__DIR__, 3) . '/deploy/servora-queue.service';

        $this->assertFileExists($path, 'The queue worker unit is the deployed artifact; it must be in the repo.');

        return file_get_contents($path);
    }

    public function test_exec_start_has_no_max_time(): void
    {
        $unit = $this->unit();

        preg_match('/^ExecStart=.*$/m', $unit, $m);

        $this->assertNotEmpty($m, 'The unit must declare an ExecStart.');
        $this->assertStringNotContainsString(
            '--max-time',
            $m[0],
            'ExecStart carries --max-time again: under maintenance mode this exits the worker '
            . 'every --sleep seconds with status 0. Use RuntimeMaxSec to recycle the process instead.'
        );
        $this->assertStringContainsString('queue:work', $m[0]);
    }

    public function test_the_process_is_still_recycled_on_a_schedule(): void
    {
        $unit = $this->unit();

        $this->assertMatchesRegularExpression(
            '/^RuntimeMaxSec=\d+$/m',
            $unit,
            'Removing --max-time gave up hourly recycling unless RuntimeMaxSec takes over.'
        );

        // Restart=always is what makes RuntimeMaxSec a recycle rather than a
        // shutdown: systemd stops the worker, then brings it straight back.
        $this->assertMatchesRegularExpression('/^Restart=always$/m', $unit);
    }

    public function test_placeholders_are_the_ones_the_deploy_scripts_substitute(): void
    {
        $unit = $this->unit();
        $root = dirname(__DIR__, 3);

        foreach (['__WEB_USER__', '__APP_DIR__'] as $token) {
            $this->assertStringContainsString($token, $unit);
        }

        // Both scripts render the same file. A rename on one side only would
        // ship a unit with a literal __APP_DIR__ as its WorkingDirectory.
        foreach (['deploy/install.sh', 'deploy/update.sh'] as $script) {
            $body = file_get_contents("{$root}/{$script}");

            $this->assertStringContainsString('servora-queue.service', $body, "{$script} should render the unit.");
            $this->assertStringContainsString('__WEB_USER__', $body, "{$script} must substitute __WEB_USER__.");
            $this->assertStringContainsString('__APP_DIR__', $body, "{$script} must substitute __APP_DIR__.");
        }
    }

    public function test_update_stops_the_worker_before_maintenance_mode(): void
    {
        $update = file_get_contents(dirname(__DIR__, 3) . '/deploy/update.sh');

        $stop = strpos($update, 'systemctl stop servora-queue');
        $down = strpos($update, 'php artisan down');

        $this->assertIsInt($stop, 'The deploy must stop the worker.');
        $this->assertIsInt($down);
        $this->assertLessThan(
            $down,
            $stop,
            'The worker has to stop BEFORE maintenance mode, or it spends the whole deploy '
            . 'in the paused-loop exit path — and runs jobs against half-migrated code.'
        );
    }
}
