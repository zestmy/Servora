<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A company's statutory rates for EPF, SOCSO, EIS and PCB.
 *
 * The seeded defaults reflect the published Malaysian rates at the time this
 * shipped (August 2026) and are a STARTING POINT, not an authority. They are
 * all editable, and `rates_confirmed_at` records that a human checked them
 * against KWSP / PERKESO / LHDN — until that is set, every screen that shows a
 * statutory figure says the rates are unconfirmed.
 */
class StatutorySetting extends Model
{
    /**
     * Resident individual income tax bands, YA2023 onwards, as [upper, rate%].
     * A null upper bound is the top band. Stored per company so a budget
     * change is an edit rather than a deploy.
     */
    public const DEFAULT_TAX_BANDS = [
        ['up_to' => 5000,    'rate' => 0],
        ['up_to' => 20000,   'rate' => 1],
        ['up_to' => 35000,   'rate' => 3],
        ['up_to' => 50000,   'rate' => 6],
        ['up_to' => 70000,   'rate' => 11],
        ['up_to' => 100000,  'rate' => 19],
        ['up_to' => 400000,  'rate' => 25],
        ['up_to' => 600000,  'rate' => 26],
        ['up_to' => 2000000, 'rate' => 28],
        ['up_to' => null,    'rate' => 30],
    ];

    public const PCB_CATEGORIES = [
        'single'             => 'Single',
        'spouse_not_working' => 'Married, spouse not working',
        'spouse_working'     => 'Married, spouse working',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'epf_enabled'   => 'boolean',
        'socso_enabled' => 'boolean',
        'eis_enabled'   => 'boolean',
        'pcb_enabled'   => 'boolean',
        'hrdf_enabled'  => 'boolean',
        'hrdf_employer_rate' => 'decimal:2',
        'hrdf_ceiling'       => 'decimal:2',
        'epf_employee_rate'          => 'decimal:2',
        'epf_employer_rate_low'      => 'decimal:2',
        'epf_employer_rate_high'     => 'decimal:2',
        'epf_wage_threshold'         => 'decimal:2',
        'epf_employee_rate_senior'   => 'decimal:2',
        'epf_employer_rate_senior'   => 'decimal:2',
        'epf_employee_rate_foreign'  => 'decimal:2',
        'epf_employer_rate_foreign'  => 'decimal:2',
        'socso_employee_rate'        => 'decimal:2',
        'socso_employer_rate'        => 'decimal:2',
        'socso_employer_rate_senior' => 'decimal:2',
        'socso_ceiling'              => 'decimal:2',
        'eis_employee_rate'          => 'decimal:2',
        'eis_employer_rate'          => 'decimal:2',
        'eis_ceiling'                => 'decimal:2',
        'pcb_tax_bands'              => 'array',
        'pcb_relief_individual'      => 'decimal:2',
        'pcb_relief_epf_cap'         => 'decimal:2',
        'pcb_relief_spouse'          => 'decimal:2',
        'pcb_relief_child'           => 'decimal:2',
        'pcb_rebate_amount'          => 'decimal:2',
        'pcb_rebate_threshold'       => 'decimal:2',
        'senior_age'                 => 'integer',
        'eis_max_age'                => 'integer',
        'rates_confirmed_at'         => 'datetime',
    ];

    /**
     * The seeded rates, readable without a row in the database.
     *
     * The public salary calculator needs them and has no company to read from.
     * Exposing the same array rather than letting a second copy exist means a
     * rate change lands in one place — and the copy nobody remembers to update
     * would be the one strangers are reading.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return (new self)->getAttributes();
    }

    /** MySQL does not read column defaults back after an insert. */
    protected $attributes = [
        'epf_enabled'   => true,
        'socso_enabled' => true,
        'eis_enabled'   => true,
        'pcb_enabled'   => false,
        'epf_employee_rate'          => 11.00,
        'epf_employer_rate_low'      => 13.00,
        'epf_employer_rate_high'     => 12.00,
        'epf_wage_threshold'         => 5000.00,
        'epf_employee_rate_senior'   => 0.00,
        'epf_employer_rate_senior'   => 4.00,
        'epf_employee_rate_foreign'  => 2.00,
        'epf_employer_rate_foreign'  => 2.00,
        'senior_age'                 => 60,
        'socso_employee_rate'        => 0.50,
        'socso_employer_rate'        => 1.75,
        'socso_employer_rate_senior' => 1.25,
        'socso_ceiling'              => 6000.00,
        'eis_employee_rate'          => 0.20,
        'eis_employer_rate'          => 0.20,
        'eis_ceiling'                => 6000.00,
        'eis_max_age'                => 60,
        // 1% is the mandatory tier (10+ employees); 0.5% the optional 5–9 one.
        'hrdf_employer_rate'         => 1.00,
        'pcb_relief_individual'      => 9000.00,
        'pcb_relief_epf_cap'         => 4000.00,
        'pcb_relief_spouse'          => 4000.00,
        'pcb_relief_child'           => 2000.00,
        'pcb_rebate_amount'          => 400.00,
        'pcb_rebate_threshold'       => 35000.00,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rates_confirmed_by');
    }

    /**
     * The company's settings, or an unsaved default set. Never creates a row
     * on read — a company that has not opened the screen still calculates,
     * and the row appears when someone saves.
     */
    public static function forCompany(int $companyId): self
    {
        return static::withoutGlobalScope(CompanyScope::class)
            ->firstWhere('company_id', $companyId)
            ?? new static(['company_id' => $companyId]);
    }

    public function taxBands(): array
    {
        return $this->pcb_tax_bands ?: self::DEFAULT_TAX_BANDS;
    }

    /** Whether anything statutory is switched on at all. */
    public function anyEnabled(): bool
    {
        return $this->epf_enabled || $this->socso_enabled || $this->eis_enabled
            || $this->pcb_enabled || $this->hrdf_enabled;
    }

    /**
     * True until someone has ticked the confirmation on the settings screen.
     * Every figure derived from these rates is labelled while this holds.
     */
    public function ratesUnconfirmed(): bool
    {
        return $this->anyEnabled() && $this->rates_confirmed_at === null;
    }
}
