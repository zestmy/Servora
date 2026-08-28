<?php

namespace App\Services\Payroll;

use App\Models\PayrollRun;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * The dates a payroll run counts each of its inputs over.
 *
 * A run has ONE period — period_start to period_end — and until now everything
 * was counted over it. Three of those things can now be counted over their own
 * range instead, because a company answers them on different calendars: a
 * service charge pool is saved for the dates the attendance grid was showing,
 * overtime is often approved a cycle behind, and a timesheet may close on a
 * different day from the payroll.
 *
 * THE MASTER PERIOD IS NOT ONE OF THE THREE, and that is the whole discipline
 * here. It still decides:
 *
 *   - who is on the run at all (Employee::scopeEmployedDuring)
 *   - which dated allowances and deductions apply
 *   - part-month proration, both the eligible days and the s.60I divisor
 *   - the statutory as-of date, and PCB year-to-date
 *
 * Those decide what somebody is PAID rather than which days were counted.
 * Nothing in this class may be used for any of them: an attendance window
 * shorter than the run would cut every monthly salary on it.
 *
 * A SUB-PERIOD IS NEVER CLAMPED TO THE MASTER. Reaching into last month is the
 * point — overtime approved late is exactly the case — so a range wider than,
 * earlier than or disjoint from the run is legal and deliberate. What stops
 * the same hours being paid twice is the paid_at guard in CompensationSummary,
 * not a clamp here.
 */
final class RunPeriods
{
    public const ATTENDANCE     = 'attendance';
    public const OVERTIME       = 'overtime';
    public const SERVICE_CHARGE = 'service_charge';

    /** In the order they are shown, which is the order they are read. */
    public const COMPONENTS = [self::ATTENDANCE, self::OVERTIME, self::SERVICE_CHARGE];

    public const LABELS = [
        self::ATTENDANCE     => 'Attendance records',
        self::OVERTIME       => 'Overtime',
        self::SERVICE_CHARGE => 'Service charge',
    ];

    /** @var array<string, array{0: Carbon, 1: Carbon}> */
    private array $overrides;

    /**
     * @param  Carbon  $from  the run's own period — the master
     * @param  array<string, array{0: Carbon, 1: Carbon}|null>  $overrides
     *         Keyed by component. A missing or null entry inherits the master,
     *         which is what an ordinary run has for all three.
     *
     * @throws InvalidArgumentException on an unknown component or a range that
     *         ends before it starts. Both are caught here rather than at the
     *         database, so a bad range cannot reach a figure.
     */
    public function __construct(
        private Carbon $from,
        private Carbon $to,
        array $overrides = [],
    ) {
        $this->from = $from->copy()->startOfDay();
        $this->to   = $to->copy()->endOfDay();

        if ($this->from->gt($this->to)) {
            throw new InvalidArgumentException('The period must start before it ends.');
        }

        $resolved = [];

        foreach ($overrides as $component => $range) {
            self::assertComponent($component);

            if ($range === null) {
                continue;
            }

            [$start, $end] = $range;
            $start = $start->copy()->startOfDay();
            $end   = $end->copy()->endOfDay();

            if ($start->gt($end)) {
                throw new InvalidArgumentException(
                    (self::LABELS[$component] ?? $component) . ' must start before it ends.'
                );
            }

            $resolved[$component] = [$start, $end];
        }

        $this->overrides = $resolved;
    }

    /**
     * The periods a SAVED run was built with.
     *
     * Read off the run rather than re-derived from the company's settings, for
     * the same reason period_start is stored at all: a run has to be able to
     * say what it actually covered after the settings move on.
     */
    public static function fromRun(PayrollRun $run): self
    {
        // Defensive rather than expected: period_start has been backfilled on
        // every run since the pay-cycle migration. A run somehow without one
        // still resolves to the month it is filed under instead of throwing.
        $month = Carbon::parse($run->period_month);
        $from  = $run->period_start ? Carbon::parse($run->period_start) : $month->copy()->startOfMonth();
        $to    = $run->period_end   ? Carbon::parse($run->period_end)   : $month->copy()->endOfMonth();

        $overrides = [];

        foreach (self::COMPONENTS as $component) {
            $start = $run->{$component . '_from'};
            $end   = $run->{$component . '_to'};

            // BOTH ends or neither. One date on its own cannot describe a
            // range, and silently pairing it with the master's other end would
            // invent a period nobody chose.
            if ($start && $end) {
                $overrides[$component] = [Carbon::parse($start), Carbon::parse($end)];
            }
        }

        return new self($from, $to, $overrides);
    }

    /** The calendar month a run is filed under — the "Monthly" option. */
    public static function monthOf(Carbon $month): array
    {
        return [
            $month->copy()->startOfMonth()->startOfDay(),
            $month->copy()->endOfMonth()->endOfDay(),
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} the run's own period */
    public function master(): array
    {
        return [$this->from->copy(), $this->to->copy()];
    }

    /**
     * The range one input is counted over — its own, or the run's.
     *
     * @return array{0: Carbon, 1: Carbon}
     * @throws InvalidArgumentException on an unknown component. Deliberately
     *         loud: a typo that quietly returned the master would look like it
     *         worked, and would be found on a payslip rather than here.
     */
    public function for(string $component): array
    {
        self::assertComponent($component);

        $range = $this->overrides[$component] ?? null;

        return $range ? [$range[0]->copy(), $range[1]->copy()] : $this->master();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function attendance(): array
    {
        return $this->for(self::ATTENDANCE);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function overtime(): array
    {
        return $this->for(self::OVERTIME);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function serviceCharge(): array
    {
        return $this->for(self::SERVICE_CHARGE);
    }

    /** Whether this input was given a range of its own. */
    public function isCustom(string $component): bool
    {
        self::assertComponent($component);

        return isset($this->overrides[$component]);
    }

    /** Whether any input departs from the run's own period. */
    public function hasAny(): bool
    {
        return $this->overrides !== [];
    }

    /** @return array<int, string> the components with a range of their own */
    public function customComponents(): array
    {
        return array_values(array_filter(
            self::COMPONENTS,
            fn ($c) => isset($this->overrides[$c]),
        ));
    }

    /**
     * The six columns, for storing on the run.
     *
     * An inherited component writes NULL rather than a copy of the master.
     * Copying would work until somebody edited the run's period and left three
     * stale pairs behind claiming to be what it covered — and it would make
     * every ordinary run look like one with three custom periods.
     *
     * @return array<string, ?string>
     */
    public function columns(): array
    {
        $columns = [];

        foreach (self::COMPONENTS as $component) {
            $range = $this->overrides[$component] ?? null;

            $columns[$component . '_from'] = $range ? $range[0]->toDateString() : null;
            $columns[$component . '_to']   = $range ? $range[1]->toDateString() : null;
        }

        return $columns;
    }

    /** "1 Aug – 31 Aug 2026", or null where the run's own period is used. */
    public function label(string $component): ?string
    {
        if (! $this->isCustom($component)) {
            return null;
        }

        [$from, $to] = $this->for($component);

        return $from->format($from->year === $to->year ? 'j M' : 'j M Y')
            . ' – ' . $to->format('j M Y');
    }

    private static function assertComponent(string $component): void
    {
        if (! in_array($component, self::COMPONENTS, true)) {
            throw new InvalidArgumentException(
                "Unknown payroll run period '{$component}'. Expected one of: "
                . implode(', ', self::COMPONENTS) . '.'
            );
        }
    }
}
