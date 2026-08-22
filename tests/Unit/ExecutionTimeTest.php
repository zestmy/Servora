<?php

namespace Tests\Unit;

use App\Support\ExecutionTime;
use PHPUnit\Framework\TestCase;

/**
 * Restoring PHP's execution limit puts back what was there — including zero.
 *
 * The bug this replaces was in thirteen places and read
 * `set_time_limit((int) $previous ?: 60)`. On CLI, ini_get() returns the
 * string "0" (meaning no limit), `(int) "0"` is 0, 0 is falsy, and the line
 * that claimed to restore the previous limit imposed a 60-second one instead
 * — permanently, on a process that had none.
 *
 * It surfaced far from its cause: queue workers acquiring a ceiling they never
 * had, and a test suite that could not run in one process because the first
 * test touching an AI extraction path capped the whole run.
 */
class ExecutionTimeTest extends TestCase
{
    private string|false $original;

    protected function setUp(): void
    {
        parent::setUp();
        $this->original = ini_get('max_execution_time');
    }

    protected function tearDown(): void
    {
        if ($this->original !== false) {
            set_time_limit((int) $this->original);
        }
        parent::tearDown();
    }

    /** The regression, stated directly. */
    public function test_restoring_an_unlimited_process_leaves_it_unlimited(): void
    {
        set_time_limit(0);

        $previous = ExecutionTime::raise(120);

        $this->assertSame('0', $previous, 'raise() must hand back the raw ini string so 0 survives.');
        $this->assertSame('120', ini_get('max_execution_time'));

        ExecutionTime::restore($previous);

        $this->assertSame(
            '0',
            ini_get('max_execution_time'),
            'Restoring an unlimited process imposed a limit — the exact bug this class exists to remove.'
        );
    }

    public function test_a_real_previous_limit_is_restored_exactly(): void
    {
        set_time_limit(45);

        $previous = ExecutionTime::raise(300);
        $this->assertSame('300', ini_get('max_execution_time'));

        ExecutionTime::restore($previous);

        $this->assertSame('45', ini_get('max_execution_time'));
    }

    /**
     * A previous of `false` means ini_get() could not read the setting, which
     * is not the same as "it was zero". Guessing a number there is what caused
     * the original bug, so restore() declines to guess.
     */
    public function test_an_unreadable_previous_value_leaves_the_limit_alone(): void
    {
        set_time_limit(90);

        ExecutionTime::restore(false);

        $this->assertSame('90', ini_get('max_execution_time'));
    }

    public function test_allow_restores_the_limit_after_the_callback_returns(): void
    {
        set_time_limit(0);

        $result = ExecutionTime::allow(120, function () {
            $this->assertSame('120', ini_get('max_execution_time'), 'The callback should run with the raised limit.');

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertSame('0', ini_get('max_execution_time'));
    }

    /** The reason allow() exists: the restore that gets forgotten is the one after a throw. */
    public function test_allow_restores_the_limit_even_when_the_callback_throws(): void
    {
        set_time_limit(30);

        try {
            ExecutionTime::allow(120, fn () => throw new \RuntimeException('the API fell over'));
            $this->fail('The exception should have propagated.');
        } catch (\RuntimeException $e) {
            $this->assertSame('the API fell over', $e->getMessage());
        }

        $this->assertSame('30', ini_get('max_execution_time'));
    }
}
