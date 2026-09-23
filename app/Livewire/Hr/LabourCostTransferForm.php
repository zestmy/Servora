<?php

namespace App\Livewire\Hr;

use App\Models\Employee;
use App\Models\LabourCostTransfer;
use App\Models\Outlet;
use App\Services\Hr\LabourCostTransferCalculator;
use App\Traits\ValidatesCompanyOutlet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Labour Cost Transfer — charge an outlet for staff lent to it.
 *
 * A line is one employee over one date range: the days are costed at their
 * daily rate and the approved OT claims inside the range are added on top.
 * Every figure on a line is a PREVIEW from LabourCostTransferCalculator; save()
 * throws them away and prices again, so the browser never sets a rate.
 */
class LabourCostTransferForm extends Component
{
    use ValidatesCompanyOutlet;

    public ?int $transferId = null;

    public string $transfer_number = '';
    public string $transfer_date   = '';
    public string $to_outlet_id    = '';
    public string $purpose         = 'support';
    public string $reference       = '';
    public string $notes           = '';
    public string $status          = 'draft';

    public array  $lines          = [];
    public string $employeeSearch = '';

    protected function rules(): array
    {
        return [
            'transfer_date'     => 'required|date',
            'to_outlet_id'      => ['required', $this->outletExistsRule()],
            'purpose'           => 'required|in:' . implode(',', array_keys(LabourCostTransfer::PURPOSES)),
            'reference'         => 'nullable|string|max:200',
            'notes'             => 'nullable|string|max:2000',
            'lines'             => 'required|array|min:1',
            'lines.*.date_start' => 'required|date',
            'lines.*.date_end'   => 'required|date|after_or_equal:lines.*.date_start',
            'lines.*.days'       => 'required|numeric|min:0.5',
        ];
    }

    protected function messages(): array
    {
        return [
            'lines.required'             => 'Add at least one employee.',
            'lines.min'                  => 'Add at least one employee.',
            'lines.*.date_end.after_or_equal' => 'An end date is before its start date.',
            'lines.*.days.min'           => 'Every line needs at least half a day.',
            'to_outlet_id.required'      => 'Choose the outlet taking on the cost.',
        ];
    }

    private function authorizeAccess(): void
    {
        abort_unless(Auth::user()?->canDo('hr.compensation'), 403);
    }

    public function mount(?int $id = null): void
    {
        $this->authorizeAccess();
        $this->transfer_date = now()->toDateString();

        if (! $id) {
            $this->transfer_number = LabourCostTransfer::generateNumber();
            return;
        }

        $transfer = LabourCostTransfer::with(['lines.fromOutlet', 'lines.employee'])->findOrFail($id);
        $this->assertCanSee($transfer);

        $this->transferId      = $transfer->id;
        $this->transfer_number = $transfer->transfer_number;
        $this->transfer_date   = $transfer->transfer_date->toDateString();
        $this->to_outlet_id    = (string) $transfer->to_outlet_id;
        $this->purpose         = $transfer->purpose;
        $this->reference       = $transfer->reference ?? '';
        $this->notes           = $transfer->notes ?? '';
        $this->status          = $transfer->status;

        $this->lines = $transfer->lines->map(fn ($l) => [
            'employee_id'      => $l->employee_id,
            'employee_name'    => $l->employee_name,
            'staff_id'         => $l->employee?->staff_id ?? '',
            'from_outlet_id'   => $l->from_outlet_id,
            'from_outlet_name' => $l->fromOutlet?->name ?? '—',
            'date_start'       => $l->date_start->toDateString(),
            'date_end'         => $l->date_end->toDateString(),
            'days'             => (string) (float) $l->days,
            'daily_rate'       => (float) $l->daily_rate,
            'salary_amount'    => (float) $l->salary_amount,
            'ot_hours'         => (float) $l->ot_hours,
            'ot_amount'        => (float) $l->ot_amount,
            'total_amount'     => (float) $l->total_amount,
            'ot_claims'        => count($l->ot_claim_ids ?? []),
            'has_salary'       => (float) $l->daily_rate > 0,
            'overlap'          => null,
        ])->all();

        // A draft is repriced on open, so it shows what saving would store now.
        if ($this->status === 'draft') {
            foreach (array_keys($this->lines) as $idx) {
                $this->reprice($idx);
            }
        }
    }

    /** Anyone who can see either end of a transfer may open it. */
    private function assertCanSee(LabourCostTransfer $transfer): void
    {
        $ids = Auth::user()->accessibleOutletIds();
        $ends = array_merge([$transfer->to_outlet_id], $transfer->lines->pluck('from_outlet_id')->all());

        abort_unless((bool) array_intersect($ids, $ends), 403, 'You do not have access to this transfer.');
    }

    /** Employees whose pay this user may move: active, at an outlet they can access. */
    private function employeeQuery()
    {
        return Employee::with('outlet')
            ->where('is_active', true)
            ->whereIn('outlet_id', Auth::user()->accessibleOutletIds());
    }

    public function addEmployee(int $employeeId): void
    {
        if ($this->status !== 'draft') return;

        $employee = $this->employeeQuery()->find($employeeId);
        if (! $employee) {
            return;
        }

        // Same person again is allowed (two separate stints), but default the
        // new line to start after the last one so it does not overlap itself.
        $start = $this->transfer_date ?: now()->toDateString();
        foreach ($this->lines as $line) {
            if ((int) $line['employee_id'] === $employeeId && $line['date_end'] >= $start) {
                $start = Carbon::parse($line['date_end'])->addDay()->toDateString();
            }
        }

        $this->lines[] = [
            'employee_id'      => $employee->id,
            'employee_name'    => $employee->name,
            'staff_id'         => $employee->staff_id ?? '',
            'from_outlet_id'   => $employee->outlet_id,
            'from_outlet_name' => $employee->outlet?->name ?? '—',
            'date_start'       => $start,
            'date_end'         => $start,
            'days'             => '1',
        ];

        $this->reprice(array_key_last($this->lines));
        $this->employeeSearch = '';
    }

    public function removeLine(int $idx): void
    {
        if ($this->status !== 'draft') return;

        unset($this->lines[$idx]);
        $this->lines = array_values($this->lines);
    }

    public function updatedLines($value, $key): void
    {
        [$idx, $field] = array_pad(explode('.', $key), 2, null);
        if (! isset($this->lines[(int) $idx])) return;

        // New dates mean a new span: reset the day count to all of it. Typing
        // the days directly is how a rest day inside the span is left out.
        if (in_array($field, ['date_start', 'date_end'], true)) {
            $line = $this->lines[(int) $idx];
            if (! empty($line['date_start']) && ! empty($line['date_end'])) {
                $this->lines[(int) $idx]['days'] = (string) LabourCostTransferCalculator::calendarDays($line['date_start'], $line['date_end']);
            }
        }

        if (in_array($field, ['date_start', 'date_end', 'days'], true)) {
            $this->reprice((int) $idx);
        }
    }

    /** Refresh one line's preview figures from the server's own numbers. */
    private function reprice(int $idx): void
    {
        $line = $this->lines[$idx] ?? null;
        if (! $line) return;

        $empty = [
            'daily_rate' => 0.0, 'salary_amount' => 0.0, 'ot_hours' => 0.0, 'ot_amount' => 0.0,
            'total_amount' => 0.0, 'ot_claims' => 0, 'has_salary' => true, 'overlap' => null,
        ];

        $employee = Employee::withTrashed()->find($line['employee_id']);
        if (! $employee || empty($line['date_start']) || empty($line['date_end']) || $line['date_end'] < $line['date_start']) {
            $this->lines[$idx] = array_merge($line, $empty);
            return;
        }

        $price = $this->calculator()->price($employee, $line['date_start'], $line['date_end'], (float) $line['days']);
        $clash = LabourCostTransferCalculator::overlapping($employee->id, $line['date_start'], $line['date_end'], $this->transferId);

        $this->lines[$idx] = array_merge($line, [
            'days'          => (string) $price['days'],
            'daily_rate'    => $price['daily_rate'],
            'salary_amount' => $price['salary_amount'],
            'ot_hours'      => $price['ot_hours'],
            'ot_amount'     => $price['ot_amount'],
            'total_amount'  => $price['total_amount'],
            'ot_claims'     => count($price['ot_claim_ids']),
            'has_salary'    => $price['has_salary'],
            'overlap'       => $clash ? $clash->transfer?->transfer_number : null,
        ]);
    }

    private function calculator(): LabourCostTransferCalculator
    {
        return new LabourCostTransferCalculator((int) Auth::user()->company_id);
    }

    public function save(): void
    {
        $this->authorizeAccess();
        if ($this->status !== 'draft') return;

        $this->validate();

        $errors = $this->lineErrors();
        if ($errors) {
            foreach ($errors as $key => $message) {
                $this->addError($key, $message);
            }
            return;
        }

        $transfer = $this->persist();

        session()->flash('success', 'Labour cost transfer ' . $transfer->transfer_number . ' saved.');
        $this->redirectRoute('hr.labour-transfers.show', ['id' => $transfer->id]);
    }

    public function confirm(): void
    {
        $this->authorizeAccess();
        if ($this->status !== 'draft') return;

        $this->validate();

        $errors = $this->lineErrors();
        if ($errors) {
            foreach ($errors as $key => $message) {
                $this->addError($key, $message);
            }
            return;
        }

        $transfer = $this->persist();
        $transfer->update([
            'status'       => 'confirmed',
            'confirmed_by' => Auth::id(),
            'confirmed_at' => now(),
        ]);

        session()->flash('success', 'Labour cost transfer ' . $transfer->transfer_number . ' confirmed.');
        $this->redirectRoute('hr.labour-transfers.show', ['id' => $transfer->id]);
    }

    public function cancelTransfer(): void
    {
        $this->authorizeAccess();
        if (! $this->transferId || $this->status === 'cancelled') return;

        LabourCostTransfer::findOrFail($this->transferId)->update(['status' => 'cancelled']);
        $this->status = 'cancelled';
        session()->flash('success', 'Transfer cancelled. Its days are free to be transferred again.');
    }

    /**
     * Checks the rules array cannot express: the person is someone this user
     * may move, is not being "transferred" to their own outlet, and has not
     * already been charged out for any of these days.
     *
     * @return array<string, string>
     */
    private function lineErrors(): array
    {
        $errors = [];
        $to = (int) $this->to_outlet_id;

        foreach ($this->lines as $idx => $line) {
            $employee = $this->employeeQuery()->find($line['employee_id']);
            $who = $line['employee_name'] ?? 'This employee';

            if (! $employee) {
                $errors["lines.$idx.employee_id"] = "$who is not an active employee at an outlet you can access.";
                continue;
            }
            if ((int) $employee->outlet_id === $to) {
                $errors["lines.$idx.employee_id"] = "$who already belongs to the receiving outlet, so there is nothing to transfer.";
            }

            $span = LabourCostTransferCalculator::calendarDays($line['date_start'], $line['date_end']);
            if ((float) $line['days'] > $span) {
                $errors["lines.$idx.days"] = "$who: {$line['days']} days is more than the $span in the date range.";
            }

            if ($clash = LabourCostTransferCalculator::overlapping($employee->id, $line['date_start'], $line['date_end'], $this->transferId)) {
                $errors["lines.$idx.date_start"] = "$who is already on {$clash->transfer?->transfer_number} for some of these dates.";
            }

            // Two lines on THIS document for the same person on the same day.
            foreach ($this->lines as $j => $other) {
                if ($j <= $idx || (int) $other['employee_id'] !== (int) $line['employee_id']) continue;
                if ($other['date_start'] <= $line['date_end'] && $other['date_end'] >= $line['date_start']) {
                    $errors["lines.$j.date_start"] = "$who appears twice on this transfer for overlapping dates.";
                }
            }
        }

        return $errors;
    }

    private function persist(): LabourCostTransfer
    {
        return DB::transaction(function () {
            $header = [
                'transfer_date' => $this->transfer_date,
                'to_outlet_id'  => (int) $this->to_outlet_id,
                'purpose'       => $this->purpose,
                'reference'     => trim($this->reference) ?: null,
                'notes'         => trim($this->notes) ?: null,
            ];

            if ($this->transferId) {
                $transfer = LabourCostTransfer::findOrFail($this->transferId);
                $transfer->update($header);
            } else {
                $transfer = LabourCostTransfer::create($header + [
                    'company_id'      => Auth::user()->company_id,
                    'transfer_number' => LabourCostTransfer::generateNumber(),
                    'status'          => 'draft',
                    'created_by'      => Auth::id(),
                ]);
                $this->transferId = $transfer->id;
            }

            $calculator = $this->calculator();
            $transfer->lines()->delete();

            foreach ($this->lines as $line) {
                $employee = $this->employeeQuery()->findOrFail($line['employee_id']);
                $price    = $calculator->price($employee, $line['date_start'], $line['date_end'], (float) $line['days']);

                $transfer->lines()->create([
                    'employee_id'    => $employee->id,
                    'employee_name'  => $employee->name,
                    'from_outlet_id' => $employee->outlet_id,
                    'date_start'     => $line['date_start'],
                    'date_end'       => $line['date_end'],
                    'days'           => $price['days'],
                    'daily_rate'     => $price['daily_rate'],
                    'salary_amount'  => $price['salary_amount'],
                    'ot_hours'       => $price['ot_hours'],
                    'ot_amount'      => $price['ot_amount'],
                    'total_amount'   => $price['total_amount'],
                    'ot_claim_ids'   => $price['ot_claim_ids'],
                ]);
            }

            return $transfer;
        });
    }

    public function render()
    {
        $employeeResults = collect();
        if ($this->status === 'draft' && strlen(trim($this->employeeSearch)) >= 2) {
            $term = '%' . trim($this->employeeSearch) . '%';
            $employeeResults = $this->employeeQuery()
                ->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('staff_id', 'like', $term))
                ->orderBy('name')
                ->limit(8)
                ->get();
        }

        $outlets = Outlet::where('company_id', Auth::user()->company_id)
            ->selectable($this->to_outlet_id)
            ->orderBy('name')
            ->get();

        $summary = LabourCostTransferCalculator::summaryByOutlet(array_map(fn ($l) => [
            'from_outlet_id' => $l['from_outlet_id'],
            'to_outlet_id'   => (int) $this->to_outlet_id ?: 0,
            'employee_id'    => $l['employee_id'],
            'days'           => (float) $l['days'],
            'ot_hours'       => (float) ($l['ot_hours'] ?? 0),
            'salary_amount'  => (float) ($l['salary_amount'] ?? 0),
            'ot_amount'      => (float) ($l['ot_amount'] ?? 0),
            'total_amount'   => (float) ($l['total_amount'] ?? 0),
        ], $this->lines));

        $outletNames = Outlet::withoutGlobalScopes()->whereIn('id', array_column($summary, 'outlet_id'))->pluck('name', 'id');

        $totals = [
            'days'   => collect($this->lines)->sum(fn ($l) => (float) $l['days']),
            'ot'     => collect($this->lines)->sum(fn ($l) => (float) ($l['ot_hours'] ?? 0)),
            'salary' => collect($this->lines)->sum(fn ($l) => (float) ($l['salary_amount'] ?? 0)),
            'ot_amt' => collect($this->lines)->sum(fn ($l) => (float) ($l['ot_amount'] ?? 0)),
            'total'  => collect($this->lines)->sum(fn ($l) => (float) ($l['total_amount'] ?? 0)),
        ];

        $isDraft   = $this->status === 'draft';
        $pageTitle = $this->transferId ? 'Labour Transfer ' . $this->transfer_number : 'New Labour Cost Transfer';

        return view('livewire.hr.labour-cost-transfer-form', compact(
            'employeeResults', 'outlets', 'summary', 'outletNames', 'totals', 'isDraft'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => $pageTitle]);
    }
}
