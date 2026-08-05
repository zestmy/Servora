<?php

namespace App\Livewire\Settings;

use App\Models\StatutorySetting;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * EPF, SOCSO, EIS and PCB rates.
 *
 * Everything here is editable on purpose. These are legislated numbers that
 * move by ministerial order, and a company must be able to correct one the
 * morning it changes rather than wait for a release. The seeded values are a
 * starting point; saving with "I have checked these" ticked is what stamps
 * rates_confirmed_at and clears the unconfirmed warning off the payroll
 * screens.
 */
class StatutoryRates extends Component
{
    public bool $epf_enabled   = true;
    public bool $socso_enabled = true;
    public bool $eis_enabled   = true;
    public bool $pcb_enabled   = false;
    public bool $hrdf_enabled  = false;

    public string $hrdf_employer_rate = '';
    public string $hrdf_ceiling       = '';

    // The employer side of every submission file. Not rates — these identify
    // the company on the return, and a listing without them is unfileable.
    public string $employer_hrdf_number  = '';
    public string $employer_epf_number   = '';
    public string $employer_socso_number = '';
    public string $employer_tax_number   = '';

    public string $epf_employee_rate         = '';
    public string $epf_employer_rate_low     = '';
    public string $epf_employer_rate_high    = '';
    public string $epf_wage_threshold        = '';
    public string $epf_employee_rate_senior  = '';
    public string $epf_employer_rate_senior  = '';
    public string $epf_employee_rate_foreign = '';
    public string $epf_employer_rate_foreign = '';
    public string $senior_age                = '';

    public string $socso_employee_rate        = '';
    public string $socso_employer_rate        = '';
    public string $socso_employer_rate_senior = '';
    public string $socso_ceiling              = '';

    public string $eis_employee_rate = '';
    public string $eis_employer_rate = '';
    public string $eis_ceiling       = '';
    public string $eis_max_age       = '';

    public string $pcb_relief_individual = '';
    public string $pcb_relief_epf_cap    = '';
    public string $pcb_relief_spouse     = '';
    public string $pcb_relief_child      = '';
    public string $pcb_rebate_amount     = '';
    public string $pcb_rebate_threshold  = '';

    /** Editable band table: [['up_to' => '100000', 'rate' => '25'], …]. */
    public array $bands = [];

    public bool $confirmed = false;

    public function mount(): void
    {
        $s = StatutorySetting::forCompany(Auth::user()->company_id);

        foreach ([
            'epf_enabled', 'socso_enabled', 'eis_enabled', 'pcb_enabled', 'hrdf_enabled',
        ] as $flag) {
            $this->{$flag} = (bool) $s->{$flag};
        }

        foreach (['employer_epf_number', 'employer_socso_number', 'employer_tax_number',
                  'employer_hrdf_number'] as $ref) {
            $this->{$ref} = (string) ($s->{$ref} ?? '');
        }

        $this->hrdf_employer_rate = (string) (float) ($s->hrdf_employer_rate ?? 1);
        // Blank means uncapped, which is the normal case for HRD Corp.
        $this->hrdf_ceiling = $s->hrdf_ceiling !== null ? (string) (float) $s->hrdf_ceiling : '';

        foreach ([
            'epf_employee_rate', 'epf_employer_rate_low', 'epf_employer_rate_high', 'epf_wage_threshold',
            'epf_employee_rate_senior', 'epf_employer_rate_senior',
            'epf_employee_rate_foreign', 'epf_employer_rate_foreign', 'senior_age',
            'socso_employee_rate', 'socso_employer_rate', 'socso_employer_rate_senior', 'socso_ceiling',
            'eis_employee_rate', 'eis_employer_rate', 'eis_ceiling', 'eis_max_age',
            'pcb_relief_individual', 'pcb_relief_epf_cap', 'pcb_relief_spouse', 'pcb_relief_child',
            'pcb_rebate_amount', 'pcb_rebate_threshold',
        ] as $field) {
            $this->{$field} = (string) (float) $s->{$field};
        }

        $this->bands = array_map(
            fn ($b) => ['up_to' => $b['up_to'] === null ? '' : (string) (float) $b['up_to'], 'rate' => (string) (float) $b['rate']],
            $s->taxBands()
        );

        $this->confirmed = $s->rates_confirmed_at !== null;
    }

    protected function rules(): array
    {
        $rate = 'required|numeric|min:0|max:100';

        return [
            'epf_employee_rate' => $rate, 'epf_employer_rate_low' => $rate,
            'epf_employer_rate_high' => $rate, 'epf_employee_rate_senior' => $rate,
            'epf_employer_rate_senior' => $rate, 'epf_employee_rate_foreign' => $rate,
            'epf_employer_rate_foreign' => $rate,
            'epf_wage_threshold' => 'required|numeric|min:0|max:1000000',
            'senior_age' => 'required|integer|min:40|max:100',

            'socso_employee_rate' => $rate, 'socso_employer_rate' => $rate,
            'socso_employer_rate_senior' => $rate,
            'socso_ceiling' => 'required|numeric|min:0|max:1000000',

            'eis_employee_rate' => $rate, 'eis_employer_rate' => $rate,
            'eis_ceiling' => 'required|numeric|min:0|max:1000000',
            'eis_max_age' => 'required|integer|min:40|max:100',

            'hrdf_employer_rate' => $rate,
            // Blank is uncapped, which is the normal HRD Corp case.
            'hrdf_ceiling'       => 'nullable|numeric|min:0|max:1000000',

            'pcb_relief_individual' => 'required|numeric|min:0|max:1000000',
            'pcb_relief_epf_cap'    => 'required|numeric|min:0|max:1000000',
            'pcb_relief_spouse'     => 'required|numeric|min:0|max:1000000',
            'pcb_relief_child'      => 'required|numeric|min:0|max:1000000',
            'pcb_rebate_amount'     => 'required|numeric|min:0|max:1000000',
            'pcb_rebate_threshold'  => 'required|numeric|min:0|max:10000000',

            'bands'          => 'required|array|min:1',
            'bands.*.up_to'  => 'nullable|numeric|min:0',
            'bands.*.rate'   => 'required|numeric|min:0|max:100',
        ];
    }

    public function addBand(): void
    {
        $this->bands[] = ['up_to' => '', 'rate' => '0'];
    }

    public function removeBand(int $index): void
    {
        unset($this->bands[$index]);
        $this->bands = array_values($this->bands);
    }

    public function resetBands(): void
    {
        $this->bands = array_map(
            fn ($b) => ['up_to' => $b['up_to'] === null ? '' : (string) $b['up_to'], 'rate' => (string) $b['rate']],
            StatutorySetting::DEFAULT_TAX_BANDS
        );
        session()->flash('success', 'Tax bands reset to the shipped defaults. Check them before saving.');
    }

    public function save(): void
    {
        $this->validate();

        // Bands are read in order and each one's ceiling must exceed the last,
        // or the progressive calculation silently skips a slice of income.
        $bands = [];
        $previous = null;
        foreach ($this->bands as $i => $band) {
            $upTo = $band['up_to'] === '' || $band['up_to'] === null ? null : round((float) $band['up_to'], 2);

            if ($upTo !== null && $previous !== null && $upTo <= $previous) {
                $this->addError("bands.{$i}.up_to", 'Each band must end above the one before it.');
                return;
            }
            if ($upTo === null && $i !== count($this->bands) - 1) {
                $this->addError("bands.{$i}.up_to", 'Only the last band may be open-ended.');
                return;
            }

            $bands[] = ['up_to' => $upTo, 'rate' => round((float) $band['rate'], 2)];
            $previous = $upTo;
        }

        if ($bands[count($bands) - 1]['up_to'] !== null) {
            $this->addError('bands.' . (count($bands) - 1) . '.up_to', 'The last band must be open-ended — leave its ceiling blank.');
            return;
        }

        $data = [
            'epf_enabled'   => $this->epf_enabled,
            'socso_enabled' => $this->socso_enabled,
            'eis_enabled'   => $this->eis_enabled,
            'pcb_enabled'   => $this->pcb_enabled,
            'pcb_tax_bands' => $bands,
        ];

        foreach ([
            'epf_employee_rate', 'epf_employer_rate_low', 'epf_employer_rate_high', 'epf_wage_threshold',
            'epf_employee_rate_senior', 'epf_employer_rate_senior',
            'epf_employee_rate_foreign', 'epf_employer_rate_foreign',
            'socso_employee_rate', 'socso_employer_rate', 'socso_employer_rate_senior', 'socso_ceiling',
            'eis_employee_rate', 'eis_employer_rate', 'eis_ceiling',
            'pcb_relief_individual', 'pcb_relief_epf_cap', 'pcb_relief_spouse', 'pcb_relief_child',
            'pcb_rebate_amount', 'pcb_rebate_threshold',
        ] as $field) {
            $data[$field] = round((float) $this->{$field}, 2);
        }

        $data['senior_age']  = (int) $this->senior_age;
        $data['eis_max_age'] = (int) $this->eis_max_age;

        foreach (['employer_epf_number', 'employer_socso_number', 'employer_tax_number',
                  'employer_hrdf_number'] as $ref) {
            $data[$ref] = trim($this->{$ref}) ?: null;
        }

        $data['hrdf_employer_rate'] = round((float) $this->hrdf_employer_rate, 2);
        $data['hrdf_ceiling'] = trim($this->hrdf_ceiling) !== ''
            ? round((float) $this->hrdf_ceiling, 2)
            : null;

        // Confirmation is a deliberate act and is re-stamped on every save, so
        // "checked" always means checked against what is currently stored.
        $data['rates_confirmed_at'] = $this->confirmed ? now() : null;
        $data['rates_confirmed_by'] = $this->confirmed ? Auth::id() : null;

        StatutorySetting::updateOrCreate(['company_id' => Auth::user()->company_id], $data);

        session()->flash('success', $this->confirmed
            ? 'Statutory rates saved and confirmed.'
            : 'Statutory rates saved. They stay marked unconfirmed until you tick the confirmation.');
    }

    public function render()
    {
        $settings = StatutorySetting::forCompany(Auth::user()->company_id);

        return view('livewire.settings.statutory-rates', compact('settings'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Statutory Rates']);
    }
}
