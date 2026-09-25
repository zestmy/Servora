<?php

namespace App\Livewire\Hr;

use App\Livewire\Hr\Concerns\PicksAttendancePeriod;
use App\Models\AttendanceCode;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\ServiceChargePeriod;
use App\Services\Hr\LatePenalties;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * HR › Service Charge — the pool, its deductions and the split, for one outlet
 * and one period.
 *
 * It used to be a panel toggled open underneath the Attendance Record grid,
 * which put a day-by-day matrix and a payout table on one screen and ran well
 * past the bottom of it. It reads the grid (MC, absent and working days come
 * off the attendance codes) but edits none of it, so it has its own page now.
 *
 * The pool is keyed on OUTLET AND PERIOD and nothing else, so those are the
 * only two filters here. The period picker is the grid's own
 * (PicksAttendancePeriod), so a pool is always saved against dates the grid
 * can show.
 */
class ServiceCharge extends Component
{
    use PicksAttendancePeriod;

    public string $outletFilter = '';

    // Pool amount + per-day deduction percentages for the current period;
    // scLoadedKey tracks which period/outlet the inputs were hydrated for so
    // switching periods reloads the stored values.
    public string $scAmount     = '';
    public string $scRetention  = '0';
    public string $scMcPercent  = '5';
    public string $scAbsPercent = '10';

    /**
     * Working days somebody must have to share this pool ('0' = no minimum).
     *
     * The rule for the joiner who started on the 27th and the leaver who went
     * on the 3rd: they hold a full service point entitlement and would take a
     * full share of a month they were barely in. Their days read UNR either
     * side of their employment, and an unrecorded day is not a worked one —
     * see ServiceChargePeriod::workingDayCounts().
     */
    public string $scMinWorkingDays = '0';

    /**
     * Whether MC, absence, lateness and special deductions go back into the
     * pool and lift the final RM/point, rather than staying with the company.
     *
     * On for a NEW pool; a saved one shows what it was saved with. The column
     * itself defaults off, so no pool saved before this option moves.
     */
    public bool $scRedistribute = true;

    /** Named allocations that take points alongside staff: [['name','points']]. */
    public array $scFunds = [];

    /** employee_id => ['amount' => '', 'note' => ''] for this period only. */
    public array $scSpecial = [];

    /**
     * employee_id => late minutes typed in by hand for this period, for staff
     * whose lateness is not coming from the web clock. Priced at the clock's
     * per-minute rate, no per-shift cap — see ServiceChargePeriod::withManualLateness().
     */
    public array $scManualLate = [];

    /**
     * employee_id => true for staff taking NO share of this pool.
     *
     * Offered for everybody the pool pays. They are on this period because
     * they worked part of it and the default is that they earned their
     * points; the tick is the override for when that is not the agreement.
     *
     * It is a different instrument from the special deduction beside it, and
     * that is why both exist: a special deduction takes money off ONE
     * person's share, while this takes their points out of the divisor and
     * hands the pool back to everybody else.
     */
    public array $scExcluded = [];
    public string $scLoadedKey  = '';

    public function mount(): void
    {
        $user = Auth::user();

        // MC and absent days are read off the codes, so a company that has
        // never opened the grid still needs them.
        AttendanceCode::seedDefaults($user->company_id);

        if ($this->outletFilter === '') {
            $activeOutletId = $user->activeOutletId();
            if ($activeOutletId) $this->outletFilter = (string) $activeOutletId;
        }

        $this->initPeriod();
    }

    /** Outlet IDs this user may see — same scoping as the Employees module. */
    protected function accessibleOutletIds(): array
    {
        return Auth::user()->accessibleOutletIds();
    }

    /**
     * Whether this person may run the pool.
     *
     * Its OWN ability, not hr.compensation. Splitting a pool needs service
     * points and shares; it does not need anybody's basic salary, and the
     * permission that is titled "Attendance & Service Charge" should be the
     * one that grants it. The route checks it too; every write re-checks here
     * because a Livewire action is its own request.
     */
    protected function canManageServiceCharge(): bool
    {
        return (bool) Auth::user()?->can('hr.attendance.service_charge');
    }

    /** Outlet key for the stored pool: the filtered outlet, or null for All. */
    protected function serviceChargeOutletId(): ?int
    {
        return $this->outletFilter !== '' ? (int) $this->outletFilter : null;
    }

    /**
     * Hydrate the panel inputs from the stored pool whenever the visible
     * period or outlet changes; returns the stored row (null if none yet).
     */
    protected function loadServiceCharge(): ?ServiceChargePeriod
    {
        [$from, $to] = $this->period();
        $key = ($this->outletFilter !== '' ? $this->outletFilter : 'all')
            . '|' . $from->format('Y-m-d') . '|' . $to->format('Y-m-d');

        $row = ServiceChargePeriod::where('outlet_id', $this->serviceChargeOutletId())
            ->whereDate('period_from', $from)
            ->whereDate('period_to', $to)
            ->first();

        if ($key !== $this->scLoadedKey) {
            $this->scAmount     = $row ? number_format((float) $row->amount, 2, '.', '') : '';
            $this->scMcPercent  = $row ? rtrim(rtrim(number_format((float) $row->mc_percent, 2, '.', ''), '0'), '.') : '5';
            $this->scAbsPercent = $row ? rtrim(rtrim(number_format((float) $row->abs_percent, 2, '.', ''), '0'), '.') : '10';
            $this->scRetention  = $row ? rtrim(rtrim(number_format((float) $row->retention_percent, 2, '.', ''), '0'), '.') : '0';
            $this->scMinWorkingDays = (string) ($row ? $row->minWorkingDays() : 0);
            $this->scRedistribute   = $row ? $row->redistributesDeductions() : true;

            $this->scFunds = $row
                ? array_map(fn ($f) => ['name' => $f['name'], 'points' => (string) $f['points']], $row->funds())
                : [];

            $this->scSpecial = collect($row?->special_deductions ?? [])
                ->mapWithKeys(fn ($d, $empId) => [(int) $empId => [
                    'amount' => (string) (float) ($d['amount'] ?? 0),
                    'note'   => (string) ($d['note'] ?? ''),
                ]])
                ->all();

            $this->scManualLate = collect($row?->manual_late_minutes ?? [])
                ->map(fn ($m) => (string) (int) $m)
                ->mapWithKeys(fn ($m, $empId) => [(int) $empId => $m])
                ->all();

            $this->scExcluded = collect($row?->excludedEmployeeIds() ?? [])
                ->mapWithKeys(fn ($id) => [$id => true])
                ->all();
            $this->scLoadedKey  = $key;
        }

        return $row;
    }

    /**
     * The ticked exclusions, narrowed to staff this user may actually exclude.
     *
     * The narrowing is REACH, not employment status. It used to be
     * resigned-only, so a tick on anybody still on the books was dropped
     * silently — three quarters of the list had a checkbox that did nothing.
     * What is re-checked, because the ids arrive from a browser, is that this
     * pool actually pays the person: excluding somebody it does not would
     * re-price a rate in an outlet the user may not even see.
     *
     * $savedIds are the exclusions already recorded against this pool, and
     * they survive the check: an exclusion is a decision about ONE closed
     * period, so someone who resigned in June and was later re-hired must not
     * have June silently reversed the next time that pool is saved. Removing
     * one is unticking it, not a change of employment status.
     *
     * @param  array<int, int>  $savedIds
     * @return array<int, int>
     */
    protected function excludedServicePointIds(array $savedIds = []): array
    {
        $ticked = array_map('intval', array_keys(array_filter($this->scExcluded)));

        if (! $ticked) {
            return [];
        }

        // Same reach as the panel that offers the tickbox: a pool member
        // posted to another branch is on this list, so excluding them has to
        // be possible or the tick silently does nothing.
        $outletId = $this->outletFilter !== '' ? (int) $this->outletFilter : null;

        $allowed = Employee::whereIn('id', $ticked)
            ->where(function ($q) use ($outletId) {
                $q->whereIn('outlet_id', $this->accessibleOutletIds() ?: [0])
                    ->when($outletId !== null, fn ($q) => $q->orWhere('service_charge_outlet_id', $outletId));
            })
            ->forServiceChargeOutlet($outletId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_intersect($ticked, array_unique(array_merge($allowed, $savedIds))));
    }

    /**
     * Who this POOL pays — which is not who works at this outlet.
     *
     * REPORTED AS: three people set to be paid from KLCC's pool were not in
     * KLCC's service charge list. They were in the payout report and in the
     * distribution, each taking a share; they were missing from THIS screen,
     * because the grid behind it selects on home outlet_id and they are posted
     * to HQ and the Central Kitchen.
     *
     * That left the panel drawing its rows from one set and its divisor from
     * another: serviceChargeTotalPoints() has always used
     * forServiceChargeOutlet(), so the redirected staff were in the divisor
     * while being absent from the rows. Employee::forServiceChargeOutlet()
     * names this as the failure the feature can most easily cause — the
     * visible shares stop adding up to the pool, and the people who are
     * missing are exactly the ones nobody thinks to check.
     *
     * Written to match ServiceChargeDistribution line for line, including the
     * accessible-outlet OR: a manager who can see this pool must be able to
     * see everybody it pays, including somebody posted to a branch they do not
     * otherwise have access to.
     */
    protected function serviceChargeEmployees(): \Illuminate\Support\Collection
    {
        [$periodFrom, $periodTo] = $this->period();

        $outletId   = $this->outletFilter !== '' ? (int) $this->outletFilter : null;
        $accessible = $this->accessibleOutletIds();

        return Employee::with(['outlet', 'section'])
            ->where(function ($q) use ($accessible, $outletId) {
                $q->whereIn('outlet_id', $accessible ?: [0])
                    ->when($outletId !== null, fn ($q) => $q->orWhere('service_charge_outlet_id', $outletId));
            })
            ->forServiceChargeOutlet($outletId)
            ->employedDuring($periodFrom->toDateString(), $periodTo->toDateString())
            ->inListOrder()
            ->get();
    }

    protected function serviceChargeTotalPoints(array $excludedIds = []): float
    {
        // employedDuring, NOT is_active: the rows being paid use that rule, so
        // a leaver whose points are in the payout but not in this base would
        // shrink the divisor, inflate RM/point and allocate more than the pool
        // holds. The two must be drawn from the same set of people — which is
        // also why the pool's exclusions come off here, not just off the rows.
        // Both ends: the end date is what keeps somebody hired AFTER the
        // period out of its divisor. Six August joiners were sitting in a
        // 1–25 July pool, and the day one of them is given points they would
        // dilute a period they did not work.
        [$periodFrom, $periodTo] = $this->period();

        // Scoped by who this POOL pays, not by who works at the outlet. A
        // person redirected to another outlet's pool takes no share here, so
        // their points must leave the divisor too — otherwise the pool
        // under-allocates and everybody else is quietly short-changed.
        $outletId   = $this->outletFilter !== '' ? (int) $this->outletFilter : null;
        $accessible = $this->accessibleOutletIds();

        // Same OR as serviceChargeEmployees() above — the divisor and the rows
        // have to be the same set of people or the RM/point is wrong for
        // everybody, not just the ones who are missing.
        $query = Employee::where(function ($q) use ($accessible, $outletId) {
                $q->whereIn('outlet_id', $accessible ?: [0])
                    ->when($outletId !== null, fn ($q) => $q->orWhere('service_charge_outlet_id', $outletId));
            })
            ->employedDuring($periodFrom->toDateString(), $periodTo->toDateString())
            ->forServiceChargeOutlet($outletId);

        // Resolved by the caller and passed in, so the divisor and the rows
        // are computed from one list rather than two that could drift.
        return (float) ($excludedIds ? $query->whereNotIn('employees.id', $excludedIds) : $query)
            ->sum('service_points_entitlement');
    }

    public function addServiceChargeFund(): void
    {
        $this->scFunds[] = ['name' => '', 'points' => ''];
    }

    public function removeServiceChargeFund(int $index): void
    {
        unset($this->scFunds[$index]);
        // Re-index, or Livewire renders the array as an object and the
        // remaining rows lose their bindings.
        $this->scFunds = array_values($this->scFunds);
    }

    public function saveServiceCharge(): void
    {
        abort_unless($this->canManageServiceCharge(), 403);

        $this->validate([
            'scAmount'        => 'required|numeric|min:0|max:9999999999',
            'scRetention'     => 'required|numeric|min:0|max:100',
            'scMcPercent'     => 'required|numeric|min:0|max:100',
            'scAbsPercent'    => 'required|numeric|min:0|max:100',
            // Capped at the longest period the grid will render, so a minimum
            // nobody could ever meet cannot be typed in and empty a pool.
            'scMinWorkingDays' => 'required|integer|min:0|max:' . AttendanceRecords::MAX_DAYS,
            'scFunds'         => 'array|max:20',
            'scFunds.*.name'  => 'required|string|max:60',
            'scFunds.*.points' => 'required|numeric|min:0|max:9999',
            'scSpecial.*.amount' => 'nullable|numeric|min:0|max:9999999',
            'scSpecial.*.note'   => 'nullable|string|max:120',
            'scManualLate'       => 'array',
            'scManualLate.*'     => 'nullable|integer|min:0|max:99999',
            'scExcluded'         => 'array',
            'scExcluded.*'       => 'boolean',
            'scRedistribute'     => 'boolean',
        ], [
            'scManualLate.*.integer'    => 'Whole minutes only.',
            'scFunds.*.name.required'   => 'Give every allocation a name.',
            'scFunds.*.points.required' => 'Give every allocation its points.',
        ], [
            'scAmount'     => 'service charge collected',
            'scRetention'  => 'retention %',
            'scMcPercent'  => 'MC deduction %',
            'scAbsPercent' => 'absent deduction %',
            'scMinWorkingDays' => 'minimum working days',
        ]);

        // Only rows with a real amount are stored, so clearing a field removes
        // the deduction rather than leaving a zero behind for someone to
        // wonder about later.
        $special = collect($this->scSpecial)
            ->filter(fn ($d) => (float) ($d['amount'] ?? 0) > 0)
            ->mapWithKeys(fn ($d, $empId) => [(string) $empId => [
                'amount' => round((float) $d['amount'], 2),
                'note'   => trim((string) ($d['note'] ?? '')) ?: null,
            ]])
            ->all();

        // Same rule as the special deduction: a cleared box removes the entry.
        $manualLate = collect($this->scManualLate)
            ->map(fn ($m) => (int) $m)
            ->filter(fn ($m) => $m > 0)
            ->mapWithKeys(fn ($m, $empId) => [(string) $empId => $m])
            ->all();

        $funds = collect($this->scFunds)
            ->map(fn ($f) => ['name' => trim((string) $f['name']), 'points' => round((float) $f['points'], 2)])
            ->filter(fn ($f) => $f['name'] !== '' && $f['points'] > 0)
            ->values()
            ->all();

        [$from, $to] = $this->period();

        // Exclusions already on this pool survive the reach check, so
        // re-saving a closed period cannot reverse a decision made about it.
        $existing = ServiceChargePeriod::where('outlet_id', $this->serviceChargeOutletId())
            ->whereDate('period_from', $from)
            ->whereDate('period_to', $to)
            ->first();

        ServiceChargePeriod::updateOrCreate(
            [
                'company_id'  => Auth::user()->company_id,
                'outlet_id'   => $this->serviceChargeOutletId(),
                'period_from' => $from->format('Y-m-d'),
                'period_to'   => $to->format('Y-m-d'),
            ],
            [
                'amount'             => round((float) $this->scAmount, 2),
                'retention_percent'  => round((float) $this->scRetention, 2),
                'mc_percent'         => round((float) $this->scMcPercent, 2),
                'abs_percent'        => round((float) $this->scAbsPercent, 2),
                'min_working_days'   => max(0, (int) $this->scMinWorkingDays),
                'redistribute_deductions' => $this->scRedistribute,
                'fund_allocations'   => $funds ?: null,
                'special_deductions' => $special ?: null,
                'manual_late_minutes' => $manualLate ?: null,
                'excluded_employees' => $this->excludedServicePointIds(
                    $existing?->excludedEmployeeIds() ?? []
                ) ?: null,
            ]
        );

        /*
         * "Save & Calculate" now SAVES the calculation.
         *
         * Before this only the pool was stored and the split was worked out
         * afresh on every view, so a period closed at RM556 a point read
         * RM574 the next day because one service point had left the divisor in
         * between — nobody having touched the pool. The figures are kept here
         * and everything else reads them back.
         *
         * After the row is written, so the new amount and percentages are what
         * gets calculated rather than the ones being replaced.
         */
        [$scFrom, $scTo] = $this->period();

        app(\App\Services\Hr\ServiceChargeDistribution::class)->freeze(
            Auth::user()->company_id,
            $this->accessibleOutletIds(),
            $scFrom,
            $scTo,
            $this->serviceChargeOutletId(),
            Auth::id(),
        );

        session()->flash('success', 'Service charge saved and calculated. These figures are now fixed for this period.');
    }

    /**
     * Work the split out again against today's staff, and keep the new answer.
     *
     * The deliberate way past a frozen period, for when the pool genuinely
     * should move — somebody was hired, points were corrected, an exclusion
     * was wrong. Separate from Save so that opening the screen, or editing a
     * name, can never re-price a period that has been signed off.
     *
     * APPROVED PAYROLL RUNS ARE NOT TOUCHED. A run copies each person's
     * service charge onto its own line when generated and an approved run
     * cannot be regenerated, so recalculating here cannot reach back into
     * money the company has already committed to. A draft run WILL pick the
     * new figures up the next time it is regenerated, which is the point of
     * it still being a draft.
     */
    public function recalculateServiceCharge(): void
    {
        abort_unless($this->canManageServiceCharge(), 403);

        [$from, $to] = $this->period();

        $result = app(\App\Services\Hr\ServiceChargeDistribution::class)->freeze(
            Auth::user()->company_id,
            $this->accessibleOutletIds(),
            $from,
            $to,
            $this->serviceChargeOutletId(),
            Auth::id(),
        );

        session()->flash(
            $result ? 'success' : 'error',
            $result
                ? 'Service charge recalculated against current staff. Approved payroll runs are unaffected.'
                : 'There is no service charge pool saved for this period to recalculate.',
        );
    }

    public function render()
    {
        $user      = Auth::user();
        $companyId = $user->company_id;

        $accessible = $this->accessibleOutletIds();
        $canViewAll = $user->canViewAllOutlets();

        $outlets = Outlet::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('id', $accessible)
            ->orderBy('name')
            ->get();

        [$from, $to] = $this->period();

        // Who this pool PAYS, which is not who works at this outlet — see
        // serviceChargeEmployees().
        $scEmployees = $this->serviceChargeEmployees();

        $codes = AttendanceCode::orderBy('sort_order')->orderBy('code')->get();

        /*
         * The codes and only the codes — its shape is a contract with
         * ServiceChargePeriod::distribute(), which counts MC and absent days
         * out of it. An hours cell has no code, so it must not appear here at
         * all rather than appear as a null somebody later compares against.
         */
        // "empId:Y-m-d" → attendance_code_id
        $cellMap = AttendanceRecord::whereIn('employee_id', $scEmployees->pluck('id')->all())
            ->whereBetween('work_date', [$from, $to])
            ->whereNotNull('attendance_code_id')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->employee_id . ':' . $r->work_date->format('Y-m-d') => $r->attendance_code_id]);


        $scRow = $this->loadServiceCharge();

        // What the KEPT calculation was actually worked out with, captured
        // before the live ticks overwrite it below.
        $scCalculatedExclusions = $scRow?->isFrozen() ? $scRow->excludedEmployeeIds() : [];

        // Ticks that have not been saved yet are applied to the in-memory row
        // so the table and the RM/point above it move together — a divisor
        // that already dropped the points while the row was still being paid
        // would show an allocation the pool cannot cover. Never persisted:
        // saving goes through updateOrCreate, not this instance.
        $scExcludedIds = $this->excludedServicePointIds($scRow?->excludedEmployeeIds() ?? []);

        if ($scRow) {
            $scRow->excluded_employees = $scExcludedIds ?: null;
        }

        /*
         * A CALCULATED PERIOD DOES NOT MOVE WHEN A TICK DOES, AND HAS TO SAY SO.
         *
         * REPORTED AS: ticking "No service point" changed nothing — the row
         * still showed RM516.75. Everything was working: a frozen pool returns
         * the split it was calculated with (see distribute()), which is the
         * whole point of freezing it, and the tick is held until somebody
         * presses Save & Calculate. But the checkbox is `.live` and the panel
         * gave no sign of that, so a control that looked instant looked broken
         * instead — and a stale row beside it, ZULFADHLI, already read
         * "excluded" because HIS tick was there when the pool was calculated.
         * Two identical ticks, two different outcomes, nothing explaining why.
         *
         * The symmetric difference, not just additions: UNticking somebody the
         * kept calculation excluded is equally pending and equally invisible.
         */
        $scPendingExclusions = $scRow?->isFrozen()
            ? array_values(array_unique(array_merge(
                array_diff($scExcludedIds, $scCalculatedExclusions),
                array_diff($scCalculatedExclusions, $scExcludedIds),
            )))
            : [];

        // The minimum has exactly the same hazard: the box is editable on a
        // calculated period and the table below it keeps the figure it was
        // judged on until somebody recalculates.
        $scPendingMinDays = $scRow?->isFrozen()
            && max(0, (int) $this->scMinWorkingDays) !== $scRow->minWorkingDays();

        // And so does the redistribution tick.
        // Hand-entered lateness, per person: whoever's box no longer matches
        // what the pool was saved and calculated with. Both sides normalised
        // the way saveServiceCharge() stores them, so a blank box and a
        // missing entry agree, and "045" is not a change from 45.
        $scLateMinutes = fn ($entries) => collect($entries ?? [])
            ->map(fn ($m) => (int) $m)
            ->filter(fn ($m) => $m > 0)
            ->mapWithKeys(fn ($m, $empId) => [(int) $empId => $m])
            ->all();

        $scPendingLate = [];
        if ($scRow?->isFrozen()) {
            $typed = $scLateMinutes($this->scManualLate);
            $saved = $scLateMinutes($scRow->manual_late_minutes);

            foreach (array_unique(array_merge(array_keys($typed), array_keys($saved))) as $empId) {
                if (($typed[$empId] ?? 0) !== ($saved[$empId] ?? 0)) {
                    $scPendingLate[] = (int) $empId;
                }
            }
        }

        // Special deductions, the same way — amount AND note, because the note
        // is kept with the calculation and printed on the slip. Normalised
        // as saveServiceCharge() stores them: a zero amount is no entry, and
        // its note goes with it.
        $scSpecials = fn ($entries) => collect($entries ?? [])
            ->filter(fn ($d) => (float) ($d['amount'] ?? 0) > 0)
            ->mapWithKeys(fn ($d, $empId) => [(int) $empId => [
                round((float) $d['amount'], 2),
                trim((string) ($d['note'] ?? '')),
            ]])
            ->all();

        $scPendingSpecial = [];
        if ($scRow?->isFrozen()) {
            $typed = $scSpecials($this->scSpecial);
            $saved = $scSpecials($scRow->special_deductions);

            foreach (array_unique(array_merge(array_keys($typed), array_keys($saved))) as $empId) {
                if (($typed[$empId] ?? null) !== ($saved[$empId] ?? null)) {
                    $scPendingSpecial[] = (int) $empId;
                }
            }
        }

        // Fund allocations, as an ordered list normalised the way
        // saveServiceCharge() keeps them: trimmed names, points to 2dp, and a
        // row without a name or points dropped — so the blank row "+ Add
        // allocation" puts on screen is not a change until it is filled in.
        $scFundList = fn ($funds) => collect($funds ?? [])
            ->map(fn ($f) => [trim((string) ($f['name'] ?? '')), round((float) ($f['points'] ?? 0), 2)])
            ->filter(fn ($f) => $f[0] !== '' && $f[1] > 0)
            ->values()
            ->all();

        $scPendingFunds = $scRow?->isFrozen()
            && $scFundList($this->scFunds) !== $scFundList($scRow->funds());

        // The pool's own figures. Compared as numbers to 2dp, as they are
        // stored, so "5" and "5.00" agree; something that is not a number at
        // all is a change, since Save & Calculate will refuse it anyway.
        $scPendingSettings = [];
        if ($scRow?->isFrozen()) {
            foreach ([
                'amount'    => [$this->scAmount, $scRow->amount],
                'retention' => [$this->scRetention, $scRow->retention_percent],
                'mc'        => [$this->scMcPercent, $scRow->mc_percent],
                'abs'       => [$this->scAbsPercent, $scRow->abs_percent],
            ] as $key => [$typed, $saved]) {
                if (! is_numeric($typed) || round((float) $typed, 2) !== round((float) $saved, 2)) {
                    $scPendingSettings[] = $key;
                }
            }
        }

        $scPendingRedistribute = $scRow?->isFrozen()
            && $this->scRedistribute !== $scRow->redistributesDeductions();

        /*
         * Who falls short of the qualifying period, and therefore whose points
         * have to come OUT OF THE DIVISOR as well as off their own row.
         *
         * serviceChargeTotalPoints() sums the base straight out of the
         * database and knows nothing about the attendance grid, so it has to
         * be told. Same hazard as the exclusions above it: points left in the
         * base for a share that is never paid make RM/point too small and the
         * pool under-allocates — quietly, and for everybody, not just the
         * person who did not qualify.
         *
         * The minimum comes off the SAVED pool when there is one and from the
         * input otherwise, matching how distribute() reads the percentages —
         * so the divisor on this screen is always the one the rows below it
         * were worked out with.
         */
        $scMinDays = $scRow
            ? $scRow->minWorkingDays()
            : max(0, (int) $this->scMinWorkingDays);

        $scBelowMinIds = ServiceChargePeriod::belowMinimumWorkingDays(
            $scEmployees,
            ServiceChargePeriod::workingDayCounts($codes, $cellMap),
            $scMinDays,
        );

        $scDivisorExcludedIds = array_values(array_unique(array_merge($scExcludedIds, $scBelowMinIds)));

        $serviceCharge = ServiceChargePeriod::distribute(
                // The pool is the one this SCREEN is showing, which is not the
                // same as the one that happens to have been saved: an outlet
                // with no figure typed into it yet still has its own pool, and
                // redirected staff still belong to somebody else's.
                $scRow, $this->serviceChargeOutletId(), $scEmployees, $codes, $cellMap,
                is_numeric($this->scMcPercent) ? (float) $this->scMcPercent : 5.0,
                is_numeric($this->scAbsPercent) ? (float) $this->scAbsPercent : 10.0,
                $this->serviceChargeTotalPoints($scDivisorExcludedIds),
                // Same outlet scope as the pool itself, so the deduction and
                // the pool it comes out of can never be drawn from different
                // sets of outlets.
                LatePenalties::forPeriod($companyId, $this->serviceChargeOutletId(), $from, $to),
                false,
                // Only reached while no pool has been saved yet, so the panel
                // previews the minimum being typed instead of showing a table
                // that ignores it until the first save.
                $scMinDays,
            );

        // The RM-per-minute the lateness column was priced at. Shown beside it
        // so the figure can be checked without opening Clock-In Settings.
        $lateRatePerMinute = (float) \App\Models\ClockSetting::forCompany($companyId)->late_rate_per_minute;

        return view('livewire.hr.service-charge', compact(
            'lateRatePerMinute', 'serviceCharge',
            'outlets', 'canViewAll', 'from', 'to',
            'scPendingExclusions', 'scPendingMinDays', 'scPendingRedistribute', 'scPendingLate', 'scPendingSpecial', 'scPendingFunds', 'scPendingSettings',
        ))->layout('layouts.app', ['title' => 'Service Charge']);
    }
}
