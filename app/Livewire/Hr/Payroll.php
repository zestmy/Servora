<?php

namespace App\Livewire\Hr;

use App\Models\Outlet;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollRunBuilder;
use App\Services\Payroll\RunPeriods;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Payroll runs: the list, and generating a new one.
 *
 * Compensation is where a month is REVIEWED — it recomputes every time it is
 * opened, which is what you want while allowances and claims are still moving.
 * A run is where a month is COMMITTED: the figures are snapshotted so a
 * payslip, a bank file and a statutory submission all describe the same
 * payroll, and still will next year.
 */
class Payroll extends Component
{
    use WithPagination;

    public string $newMonth  = '';
    public string $newOutlet = '';
    public bool   $showNew   = false;

    /**
     * The segment this run covers, beyond the outlet.
     *
     * Both empty means the whole company, which is what most runs are. They
     * earn their place where a company pays part of its staff differently from
     * the rest: outsourced heads are invoiced by an agent against a contract
     * rate and carry no statutory contributions, so they are settled on their
     * own day against their own document and belong on their own run.
     */
    public string $newSection          = '';
    public string $newEmploymentStatus = '';

    /**
     * A period for THIS run that is not the company's cycle.
     *
     * Off by default and empty until opened, so the ordinary month is one
     * field and a button. The month a company changes its cycle is the case it
     * exists for: moving to 26th–25th leaves the last calendar month six days
     * short and the first new one starting mid-month, and neither is a thing
     * the cycle setting can describe — a setting is the steady state, and this
     * is the seam between two of them.
     */
    public bool   $customPeriod = false;
    public string $newFrom      = '';
    public string $newTo        = '';

    /**
     * A period for one INPUT that is not the run's own.
     *
     * A different question from $customPeriod above, which moves the whole
     * run. These move ONE thing and leave the run where it is:
     *
     *   service charge  a pool is matched on both its exact dates, so a
     *                   company distributing by calendar month while running
     *                   payroll 26th–25th matched no pool and was paid
     *                   nothing. This is the reason the feature exists.
     *   overtime        claims are often approved a cycle behind.
     *   attendance      the timesheet may close on a different day from the
     *                   payroll. Prices hourly and daily staff only.
     *
     * 'follows' on all three is the default and is stored as nulls, which is
     * what every run so far carries. See Services\Payroll\RunPeriods.
     *
     * @var array<string, string>  component => follows|monthly|custom
     */
    public array $periodMode = [];

    /** @var array<string, array{from: string, to: string}> */
    public array $periodDates = [];

    public const MODE_FOLLOWS = 'follows';
    public const MODE_MONTHLY = 'monthly';
    public const MODE_CUSTOM  = 'custom';

    public const MODES = [
        self::MODE_FOLLOWS => 'Follows the run',
        self::MODE_MONTHLY => 'Calendar month',
        self::MODE_CUSTOM  => 'Custom range',
    ];

    public function mount(): void
    {
        // Default to LAST month: payroll for August is run at the end of
        // August or in early September, so this month is rarely the answer
        // on the day someone opens this screen.
        $this->newMonth = now()->subMonthNoOverflow()->format('Y-m');

        $this->resetComponentPeriods();
    }

    /** Every input back to following the run, which is the ordinary case. */
    protected function resetComponentPeriods(): void
    {
        foreach (RunPeriods::COMPONENTS as $component) {
            $this->periodMode[$component]  = self::MODE_FOLLOWS;
            $this->periodDates[$component] = ['from' => '', 'to' => ''];
        }
    }

    /**
     * Opening the override pre-fills it with the cycle's own answer.
     *
     * Nobody wants to type a range from scratch — the change is nearly always
     * "the same as usual but ending on the 25th", so the edit is two digits
     * from a filled-in field rather than two dates from an empty one.
     */
    public function updatedCustomPeriod(bool $value): void
    {
        if (! $value) {
            return;
        }

        $range = $this->newMonthRange();

        $this->newFrom = $range ? $range[0]->toDateString() : '';
        $this->newTo   = $range ? $range[1]->toDateString() : '';
    }

    /** A different month re-seeds the range, or it would describe the old one. */
    public function updatedNewMonth(): void
    {
        if ($this->customPeriod) {
            $this->updatedCustomPeriod(true);
        }

        /*
         * A component range typed against the old month would quietly go on
         * describing it — so it is re-seeded from what the NEW month resolves
         * to, exactly as the whole-run override above is.
         *
         * Read from runRange() rather than from the component itself: asking
         * the component would hand back the dates already typed into it, which
         * is the very thing being replaced.
         */
        $range = $this->runRange();

        if (! $range) {
            return;
        }

        foreach (RunPeriods::COMPONENTS as $component) {
            if (($this->periodMode[$component] ?? self::MODE_FOLLOWS) !== self::MODE_CUSTOM) {
                continue;
            }

            $this->periodDates[$component] = [
                'from' => $range[0]->toDateString(),
                'to'   => $range[1]->toDateString(),
            ];
        }
    }

    /**
     * Switching an input to a custom range pre-fills it with what it is
     * already using, so the edit is two digits rather than two dates typed
     * from nothing. The same courtesy the whole-run override gets.
     */
    public function updatedPeriodMode($value, ?string $key = null): void
    {
        if ($key === null || ! in_array($key, RunPeriods::COMPONENTS, true)) {
            return;
        }

        if ($value !== self::MODE_CUSTOM) {
            return;
        }

        $range = $this->resolvedRangeFor($key) ?? $this->runRange();

        if ($range) {
            $this->periodDates[$key] = [
                'from' => $range[0]->toDateString(),
                'to'   => $range[1]->toDateString(),
            ];
        }
    }

    /**
     * The run's own period — the cycle, or the whole-run override when open.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function runRange(): ?array
    {
        if ($this->customPeriod && $this->newFrom !== '' && $this->newTo !== '') {
            try {
                return [Carbon::parse($this->newFrom)->startOfDay(), Carbon::parse($this->newTo)->endOfDay()];
            } catch (\Throwable $e) {
                return null;
            }
        }

        return $this->newMonthRange();
    }

    /**
     * What ONE input will actually be counted over, resolved live so the
     * dates are visible before generating rather than discovered afterwards.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function resolvedRangeFor(string $component): ?array
    {
        $mode = $this->periodMode[$component] ?? self::MODE_FOLLOWS;

        if ($mode === self::MODE_MONTHLY) {
            try {
                return RunPeriods::monthOf(Carbon::createFromFormat('Y-m', $this->newMonth)->startOfMonth());
            } catch (\Throwable $e) {
                return null;
            }
        }

        if ($mode === self::MODE_CUSTOM) {
            $from = $this->periodDates[$component]['from'] ?? '';
            $to   = $this->periodDates[$component]['to'] ?? '';

            if ($from === '' || $to === '') {
                return null;
            }

            try {
                return [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()];
            } catch (\Throwable $e) {
                return null;
            }
        }

        return $this->runRange();
    }

    /**
     * The overrides for the builder: only the inputs that are NOT following
     * the run, because null is what says "this run counted everything over
     * its own period" and a copy of the master would not.
     *
     * @return array<string, array{0: Carbon, 1: Carbon}>
     */
    protected function componentPeriodOverrides(): array
    {
        $overrides = [];

        foreach (RunPeriods::COMPONENTS as $component) {
            if (($this->periodMode[$component] ?? self::MODE_FOLLOWS) === self::MODE_FOLLOWS) {
                continue;
            }

            $range = $this->resolvedRangeFor($component);

            if ($range) {
                $overrides[$component] = $range;
            }
        }

        return $overrides;
    }

    /**
     * Which saved pool, if any, the service charge period would find.
     *
     * THE POINT OF SHOWING THIS. A pool is matched on BOTH its exact dates, so
     * a range one day out finds nothing and the run pays no service charge —
     * silently, because the lookup answers null rather than raising. Typing
     * dates into a box cannot fix that on its own; seeing whether they hit
     * anything can. Hence the pools below are pickable rather than merely
     * listed.
     *
     * @return array{matched: \Illuminate\Support\Collection, pools: \Illuminate\Support\Collection}
     */
    public function serviceChargePools(): array
    {
        $user  = Auth::user();
        $pools = app(\App\Services\Hr\ServiceChargeDistribution::class)
            ->savedPeriods($user->company_id, $this->accessibleOutletIds());

        $range = $this->resolvedRangeFor(RunPeriods::SERVICE_CHARGE);

        $matched = $range
            ? $pools->filter(fn ($p) => $p->period_from->isSameDay($range[0])
                && $p->period_to->isSameDay($range[1]))
            : collect();

        return ['matched' => $matched, 'pools' => $pools];
    }

    /**
     * Take a saved pool's dates for the service charge period.
     *
     * One click instead of two typed dates, because the dates have to match
     * EXACTLY and typing them is the step that goes wrong.
     */
    public function useServiceChargePool(int $poolId): void
    {
        $pool = \App\Models\ServiceChargePeriod::withoutGlobalScopes()
            ->where('company_id', Auth::user()->company_id)
            ->where(function ($q) {
                $q->whereNull('outlet_id')->orWhereIn('outlet_id', $this->accessibleOutletIds() ?: [0]);
            })
            ->find($poolId);

        if (! $pool) {
            return;
        }

        $this->periodMode[RunPeriods::SERVICE_CHARGE]  = self::MODE_CUSTOM;
        $this->periodDates[RunPeriods::SERVICE_CHARGE] = [
            'from' => $pool->period_from->toDateString(),
            'to'   => $pool->period_to->toDateString(),
        ];
    }

    protected function accessibleOutletIds(): array
    {
        return Auth::user()->accessibleOutletIds();
    }

    public function openNew(): void
    {
        $this->showNew = true;
        $this->resetErrorBag();
    }

    public function generate(): void
    {
        $this->validate([
            'newMonth'  => 'required|date_format:Y-m',
            'newOutlet' => 'nullable|integer',
            'newSection' => 'nullable|integer|exists:sections,id',
            'newEmploymentStatus' => 'nullable|in:' . implode(',', array_keys(PayrollRun::employmentSegments())),
            // Required together and only when the override is open: a range
            // with one end filled in would quietly become a cycle run.
            'newFrom'   => [$this->customPeriod ? 'required' : 'nullable', 'date'],
            'newTo'     => [$this->customPeriod ? 'required' : 'nullable', 'date', 'after_or_equal:newFrom'],
            // Both dates or neither, and only where the mode asks for them: a
            // half-filled custom range would otherwise resolve to nothing and
            // silently fall back to following the run.
            'periodMode.*'       => 'required|in:' . implode(',', array_keys(self::MODES)),
            'periodDates.*.from' => 'nullable|date',
            'periodDates.*.to'   => 'nullable|date',
        ], [], [
            'newMonth' => 'month',
            'newFrom'  => 'period start',
            'newTo'    => 'period end',
            'newSection' => 'section',
            'newEmploymentStatus' => 'employment',
        ]);

        /*
         * A custom range needs both ends, and has to run forwards.
         *
         * Checked here rather than in the rules above because the requirement
         * is conditional on that input's own mode, and a rule that fires on
         * every component would refuse the two that are following the run.
         */
        foreach (RunPeriods::COMPONENTS as $component) {
            if (($this->periodMode[$component] ?? self::MODE_FOLLOWS) !== self::MODE_CUSTOM) {
                continue;
            }

            $from = $this->periodDates[$component]['from'] ?? '';
            $to   = $this->periodDates[$component]['to'] ?? '';
            $label = RunPeriods::LABELS[$component];

            if ($from === '' || $to === '') {
                $this->addError("periodDates.{$component}.from", "Give {$label} both a start and an end date.");
                return;
            }

            if (Carbon::parse($from)->gt(Carbon::parse($to))) {
                $this->addError("periodDates.{$component}.to", "{$label} must start before it ends.");
                return;
            }
        }

        $user     = Auth::user();
        $outletId = $this->newOutlet !== '' ? (int) $this->newOutlet : null;

        // The outlet arrives from a select, so it is re-checked here rather
        // than trusted — a run scoped to an outlet the user cannot see would
        // expose pay for the whole of it.
        if ($outletId !== null && ! in_array($outletId, $this->accessibleOutletIds(), true)) {
            $this->addError('newOutlet', 'You do not have access to that outlet.');
            return;
        }

        // And "All outlets" is a choice about the whole company, so it needs
        // the whole company. Without this a restricted user could generate a
        // run they are then not allowed to open — which reads as the run
        // having failed rather than as a refusal.
        if ($outletId === null && ! $user->coversEveryOutlet()) {
            $this->addError('newOutlet', 'A company-wide run needs access to every outlet. Choose an outlet instead.');
            return;
        }

        try {
            $run = app(PayrollRunBuilder::class)->generate(
                $user->company_id,
                $this->accessibleOutletIds(),
                Carbon::createFromFormat('Y-m', $this->newMonth)->startOfMonth(),
                $outletId,
                $user->id,
                $this->customPeriod ? Carbon::parse($this->newFrom)->startOfDay() : null,
                $this->customPeriod ? Carbon::parse($this->newTo)->endOfDay() : null,
                $this->newSection !== '' ? (int) $this->newSection : null,
                $this->newEmploymentStatus !== '' ? $this->newEmploymentStatus : null,
                // An ARRAY always, never null: the form is stating every
                // input's period, and an empty one means "all three follow the
                // run", which has to be able to clear a draft's earlier
                // choices rather than silently keep them.
                $this->componentPeriodOverrides(),
            );
        } catch (\RuntimeException $e) {
            $this->addError('newMonth', $e->getMessage());
            return;
        }

        $this->showNew      = false;
        $this->customPeriod = false;
        $this->resetComponentPeriods();

        session()->flash('success', "Payroll generated for {$run->periodLabel()}"
            // Named back, because a run built over a range nobody expects is
            // exactly the one somebody needs to be told about — and the same
            // goes for a run that covered a slice of the company rather than
            // the whole of it.
            . ($run->hasCustomRange() ? " ({$run->rangeLabel()})" : '')
            . ($run->isSegmented() ? " — {$run->scopeLabel()}" : '')
            . ": {$run->employee_count} employee(s).");

        $this->redirectRoute('hr.payroll.show', ['run' => $run->uuid], navigate: true);
    }

    public function deleteRun(int $id): void
    {
        $run = PayrollRun::findOrFail($id);

        // Only a draft. An approved run is a record of a decision, and a paid
        // one is a record of money that moved.
        if (! $run->isEditable()) {
            session()->flash('error', 'An approved payroll run cannot be deleted.');
            return;
        }

        $run->delete();
        session()->flash('success', 'Draft payroll run deleted.');
    }

    /**
     * The dates the chosen month will actually cover, resolved live so the
     * range is visible BEFORE generating rather than discovered after.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function newMonthRange(): ?array
    {
        try {
            $month = Carbon::createFromFormat('Y-m', $this->newMonth)->startOfMonth();
        } catch (\Throwable $e) {
            return null;
        }

        return \App\Models\CompensationSetting::forCompany(Auth::user()->company_id)->cycleFor($month);
    }

    /**
     * How many people the chosen segment would actually pay.
     *
     * Shown BEFORE generating, because a segment is easy to get wrong in a way
     * that is invisible afterwards: picking a section that nobody in the
     * chosen outlet belongs to produces a perfectly valid empty run, and
     * "0 employees" on a button is a much better place to find that out than
     * an empty payslip list.
     *
     * Counted through exactly the filters the builder applies, and over the
     * same employedDuring() window, so it cannot say one number and the run
     * produce another.
     */
    public function segmentHeadcount(): int
    {
        $range = $this->customPeriod && $this->newFrom
            ? [Carbon::parse($this->newFrom)]
            : $this->newMonthRange();

        if (! $range) {
            return 0;
        }

        $outletId = $this->newOutlet !== '' ? (int) $this->newOutlet : null;

        return \App\Models\Employee::query()
            ->whereIn('outlet_id', $this->accessibleOutletIds() ?: [0])
            ->when($outletId !== null, fn ($q) => $q->where('outlet_id', $outletId))
            ->when($this->newSection !== '', fn ($q) => $q->where('section_id', (int) $this->newSection))
            ->tap(fn ($q) => PayrollRun::applyEmploymentStatus($q, $this->newEmploymentStatus ?: null))
            ->employedDuring($range[0]->toDateString(), ($range[1] ?? $range[0])->toDateString())
            ->count();
    }

    public function render()
    {
        $user = Auth::user();

        $runs = PayrollRun::with('outlet:id,name', 'section:id,name', 'approvedBy:id,name')
            // Every run in the company used to be listed, whatever branch it
            // paid — the gross, net and headcount of one were readable from
            // the index by anyone with hr.payroll.
            ->visibleTo($user)
            ->orderByDesc('period_month')
            ->orderByDesc('id')
            ->paginate(15);

        $outlets = Outlet::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->whereIn('id', $this->accessibleOutletIds())
            ->orderBy('name')
            ->get();

        return view('livewire.hr.payroll', [
            'runs'     => $runs,
            'outlets'  => $outlets,
            'sections' => \App\Models\Section::active()->ordered()->get(),
            'segments' => PayrollRun::employmentSegments(),
            // Only worth a query while the panel is open.
            'headcount' => $this->showNew ? $this->segmentHeadcount() : null,
            'newRange' => $this->newMonthRange(),
            'settings' => \App\Models\CompensationSetting::forCompany($user->company_id),
            // The dates each input would actually be counted over, resolved
            // live: seeing them before generating is the whole point of the
            // panel, and a pool that does not match is only visible this way.
            'componentRanges' => $this->showNew
                ? collect(RunPeriods::COMPONENTS)
                    ->mapWithKeys(fn ($c) => [$c => $this->resolvedRangeFor($c)])
                    ->all()
                : [],
            'servicePools' => $this->showNew ? $this->serviceChargePools() : null,
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Payroll']);
    }
}
