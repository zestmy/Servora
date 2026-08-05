<?php

namespace App\Livewire\Settings;

use App\Models\CompensationSetting;
use App\Models\EmployeePayComponent;
use App\Models\PayComponent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The company's allowance and deduction catalogue, plus the overtime rates
 * used to price approved OT claims.
 *
 * Both sit behind hr.compensation: they define what people are paid.
 */
class PayComponents extends Component
{
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name           = '';
    public string $description    = '';
    public string $kind           = 'allowance';
    public string $calculation    = 'fixed';
    public string $default_amount = '';
    public bool   $is_taxable       = true;
    public bool   $epf_applicable   = true;
    public bool   $socso_applicable = true;
    public string $sort_order     = '0';
    public bool   $is_active      = true;

    // Overtime rates
    public string $ot_normal          = '';
    public string $ot_rest_day        = '';
    public string $ot_public_holiday  = '';
    public string $monthly_days       = '';
    public string $daily_hours        = '';
    /** Day the pay cycle opens. 1 = calendar months, 26 = 26th to the 25th. */
    public string $cycle_start_day    = '1';

    public function mount(): void
    {
        $s = CompensationSetting::forCompany(Auth::user()->company_id);

        $this->ot_normal         = (string) (float) $s->ot_normal_multiplier;
        $this->ot_rest_day       = (string) (float) $s->ot_rest_day_multiplier;
        $this->ot_public_holiday = (string) (float) $s->ot_public_holiday_multiplier;
        $this->monthly_days      = (string) $s->monthly_working_days;
        $this->daily_hours       = (string) (float) $s->daily_working_hours;
        $this->cycle_start_day   = (string) ($s->payroll_cycle_start_day ?: 1);
    }

    protected function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('pay_components', 'name')
                    ->where('company_id', Auth::user()->company_id)
                    ->ignore($this->editingId),
            ],
            'description'    => 'nullable|string|max:255',
            'kind'           => 'required|in:' . implode(',', array_keys(PayComponent::KINDS)),
            'calculation'    => 'required|in:' . implode(',', array_keys(PayComponent::CALCULATIONS)),
            'default_amount' => 'nullable|numeric|min:0',
            'sort_order'     => 'required|integer|min:0|max:9999',
        ];
    }

    protected function messages(): array
    {
        return ['name.unique' => 'A pay component with this name already exists.'];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $c = PayComponent::findOrFail($id);

        $this->editingId      = $c->id;
        $this->name           = $c->name;
        $this->description    = $c->description ?? '';
        $this->kind           = $c->kind;
        $this->calculation    = $c->calculation;
        $this->default_amount = $c->default_amount !== null ? (string) (float) $c->default_amount : '';
        $this->is_taxable       = $c->is_taxable;
        $this->epf_applicable   = (bool) $c->epf_applicable;
        $this->socso_applicable = (bool) $c->socso_applicable;
        $this->sort_order     = (string) $c->sort_order;
        $this->is_active      = $c->is_active;
        $this->showModal      = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name'           => $this->name,
            'description'    => $this->description ?: null,
            'kind'           => $this->kind,
            'calculation'    => $this->calculation,
            'default_amount' => $this->default_amount !== '' ? round((float) $this->default_amount, 2) : null,
            'is_taxable'       => $this->is_taxable,
            'epf_applicable'   => $this->epf_applicable,
            'socso_applicable' => $this->socso_applicable,
            'sort_order'     => (int) $this->sort_order,
            'is_active'      => $this->is_active,
        ];

        if ($this->editingId) {
            PayComponent::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Pay component updated.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            PayComponent::create($data);
            session()->flash('success', 'Pay component added.');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $c = $this->authorizedComponent($id);

        // Deleting would take every employee's assignment with it, and those
        // are what past months were calculated from. Deactivate instead.
        $used = EmployeePayComponent::where('pay_component_id', $c->id)->count();
        if ($used > 0) {
            session()->flash('error', "Cannot delete \"{$c->name}\" — it is assigned to {$used} " . ($used === 1 ? 'employee' : 'employees') . '. Deactivate it instead.');
            return;
        }

        $c->delete();
        session()->flash('success', 'Pay component deleted.');
    }

    public function toggleActive(int $id): void
    {
        $c = $this->authorizedComponent($id);
        $c->update(['is_active' => ! $c->is_active]);
    }

    private function authorizedComponent(int $id): PayComponent
    {
        return PayComponent::findOrFail($id);
    }

    public function saveRates(): void
    {
        $this->validate([
            'ot_normal'         => 'required|numeric|min:0|max:99.99',
            'ot_rest_day'       => 'required|numeric|min:0|max:99.99',
            'ot_public_holiday' => 'required|numeric|min:0|max:99.99',
            'monthly_days'      => 'required|integer|min:1|max:31',
            'daily_hours'       => 'required|numeric|min:1|max:24',
            // Capped at 28: a cycle starting on the 30th has no February.
            'cycle_start_day'   => 'required|integer|min:1|max:28',
        ]);

        CompensationSetting::updateOrCreate(
            ['company_id' => Auth::user()->company_id],
            [
                'ot_normal_multiplier'         => round((float) $this->ot_normal, 2),
                'ot_rest_day_multiplier'       => round((float) $this->ot_rest_day, 2),
                'ot_public_holiday_multiplier' => round((float) $this->ot_public_holiday, 2),
                'monthly_working_days'         => (int) $this->monthly_days,
                'daily_working_hours'          => round((float) $this->daily_hours, 2),
                'payroll_cycle_start_day'      => (int) $this->cycle_start_day,
            ]
        );

        session()->flash('success', 'Overtime rates and pay cycle saved.');
    }

    public function render()
    {
        $components = PayComponent::ordered()->get();

        $usage = EmployeePayComponent::selectRaw('pay_component_id, count(*) as total')
            ->groupBy('pay_component_id')
            ->pluck('total', 'pay_component_id');

        return view('livewire.settings.pay-components', compact('components', 'usage'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Pay Components']);
    }

    private function resetForm(): void
    {
        $this->editingId      = null;
        $this->name           = '';
        $this->description    = '';
        $this->kind           = 'allowance';
        $this->calculation    = 'fixed';
        $this->default_amount = '';
        $this->is_taxable       = true;
        $this->epf_applicable   = true;
        $this->socso_applicable = true;
        $this->sort_order     = '0';
        $this->is_active      = true;
        $this->resetValidation();
    }
}
