<?php

namespace App\Livewire\Settings;

use App\Models\LabourCost;
use App\Models\LabourCostAllowance;
use App\Models\Outlet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class LabourCosts extends Component
{
    public string $period = '';
    public ?int $outletId = null;

    // Form state
    public bool $showModal = false;
    public ?int $editingId = null;
    public string $editDeptType = 'foh';
    public string $basic_salary = '0';
    public string $service_point = '0';
    public string $epf = '0';
    public string $eis = '0';
    public string $socso = '0';
    public array $allowances = []; // [{label, amount}]

    public function mount(): void
    {
        $this->period = now()->format('Y-m');
        $user = Auth::user();
        $this->outletId = $user->activeOutletId();
    }

    protected function rules(): array
    {
        return [
            'basic_salary'      => 'required|numeric|min:0',
            'service_point'     => 'required|numeric|min:0',
            'epf'               => 'required|numeric|min:0',
            'eis'               => 'required|numeric|min:0',
            'socso'             => 'required|numeric|min:0',
            'allowances.*.label'  => 'required|string|max:100',
            'allowances.*.amount' => 'required|numeric|min:0',
        ];
    }

    public function previousMonth(): void
    {
        $this->period = Carbon::createFromFormat('Y-m', $this->period)->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->period = Carbon::createFromFormat('Y-m', $this->period)->addMonth()->format('Y-m');
    }

    public function openEdit(string $deptType): void
    {
        $this->resetForm();
        $this->editDeptType = $deptType;

        $month = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth()->toDateString();

        $record = LabourCost::where('outlet_id', $this->outletId)
            ->where('month', $month)
            ->where('department_type', $deptType)
            ->with('allowances')
            ->first();

        if ($record) {
            $this->editingId     = $record->id;
            $this->basic_salary  = (string) $record->basic_salary;
            $this->service_point = (string) $record->service_point;
            $this->epf           = (string) $record->epf;
            $this->eis           = (string) $record->eis;
            $this->socso         = (string) $record->socso;
            $this->allowances    = $record->allowances->map(fn ($a) => [
                'id'     => $a->id,
                'label'  => $a->label,
                'amount' => (string) $a->amount,
            ])->toArray();
        }

        $this->showModal = true;
    }

    public function addAllowance(): void
    {
        $this->allowances[] = ['id' => null, 'label' => '', 'amount' => '0'];
    }

    public function removeAllowance(int $index): void
    {
        unset($this->allowances[$index]);
        $this->allowances = array_values($this->allowances);
    }

    public function save(): void
    {
        $this->validate();

        $month = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth()->toDateString();

        $data = [
            'company_id'      => Auth::user()->company_id,
            'outlet_id'       => $this->outletId,
            'month'           => $month,
            'department_type'  => $this->editDeptType,
            'basic_salary'    => (float) $this->basic_salary,
            'service_point'   => (float) $this->service_point,
            'epf'             => (float) $this->epf,
            'eis'             => (float) $this->eis,
            'socso'           => (float) $this->socso,
        ];

        if ($this->editingId) {
            $record = LabourCost::findOrFail($this->editingId);
            $record->update($data);
        } else {
            $record = LabourCost::create($data);
        }

        // Sync allowances
        $keepIds = [];
        foreach ($this->allowances as $row) {
            if (empty($row['label'])) continue;

            if (!empty($row['id'])) {
                $allowance = LabourCostAllowance::find($row['id']);
                if ($allowance) {
                    $allowance->update(['label' => $row['label'], 'amount' => (float) $row['amount']]);
                    $keepIds[] = $allowance->id;
                }
            } else {
                $new = $record->allowances()->create([
                    'label'  => $row['label'],
                    'amount' => (float) $row['amount'],
                ]);
                $keepIds[] = $new->id;
            }
        }

        // Remove deleted allowances
        $record->allowances()->whereNotIn('id', $keepIds)->delete();

        $deptLabel = $this->editDeptType === 'foh' ? 'FOH' : 'BOH';
        session()->flash('success', "{$deptLabel} labour cost saved for " . Carbon::createFromFormat('Y-m', $this->period)->format('F Y') . '.');
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $outlets = Outlet::where('company_id', Auth::user()->company_id)->orderBy('name')->get();

        $month = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth()->toDateString();

        $records = [];
        if ($this->outletId) {
            $records = LabourCost::where('outlet_id', $this->outletId)
                ->where('month', $month)
                ->with('allowances')
                ->get()
                ->keyBy('department_type');
        }

        $periodLabel = Carbon::createFromFormat('Y-m', $this->period)->format('F Y');

        return view('livewire.settings.labour-costs', compact('outlets', 'records', 'periodLabel'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Labour Costs']);
    }

    private function resetForm(): void
    {
        $this->editingId     = null;
        $this->editDeptType  = 'foh';
        $this->basic_salary  = '0';
        $this->service_point = '0';
        $this->epf           = '0';
        $this->eis           = '0';
        $this->socso         = '0';
        $this->allowances    = [];
        $this->resetValidation();
    }
}
