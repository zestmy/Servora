<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;

/**
 * A bank salaries can be paid into.
 *
 * Seeded from the IBG participating list and editable per company — see the
 * migration for why this is data rather than an enum.
 */
class Bank extends Model
{
    protected $fillable = ['company_id', 'name', 'bic', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * The IBG participating banks, seeded for a company that has none.
     *
     * Names are kept VERBATIM from the published list, mixed capitalisation and
     * all — payroll staff match these against the bank's own paperwork, so
     * tidying the casing would cost more than it gains. Ordered
     * case-insensitively so the picker reads alphabetically regardless.
     *
     * Editing this list only affects companies seeded after the change. An
     * existing company's list is its own; correct it in Settings › Banks.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    public const IBG_BANKS = [
        ['Affin Bank',                               'PHBMMYKL'],
        ['AFFIN ISLAMIC BANK BHD',                   'AIBBMYKL'],
        ['AGRO BANK',                                'AGOBMYKL'],
        ['AL-RAJHI BANKING & INVESTMENT CO',         'RJHIMYKL'],
        ['Alliance Bank',                            'MFBBMYKL'],
        ['ALLIANCE ISLAMIC BANK BHD',                'ALSRMYKL'],
        ['Ambank',                                   'ARBKMYKL'],
        ['AMISLAMIC BANK BHD',                       'AISLMYKL'],
        ['Bank Islam',                               'BIMBMYKL'],
        ['Bank Muamalat',                            'BMMBMYKL'],
        ['Bank of America (Malaysia) Berhad',        'BOFAMY2X'],
        ['BANK OF CHINA (MALAYSIA) BERHAD',          'BKCHMYKL'],
        ['BANK OF TOKYO',                            'BOTKMYKX'],
        ['Bank Rakyat',                              'BKRMMYKL'],
        ['Bank Simpanan Nasional',                   'BSNAMYK1'],
        ['BANK SIMPANAN NASIONAL - SPI',             'BSNAMY21'],
        ['BNP Paribas Malaysia Berhad',              'BNPAMYKL'],
        ['CIMB Bank Berhad',                         'CIBBMYKL'],
        ['CIMB ISLAMIC BANK BHD',                    'CTBBMYKL'],
        ['CITIBANK',                                 'CITIMYKL'],
        ['CITIBANK SPI',                             'CITIMYM1'],
        ['Deutsche Bank (Malaysia) Berhad',          'DEUTMYKL'],
        ['DEUTSCHE BANK - SPI',                      'DEUTMY21'],
        ['Hong Leong Bank',                          'HLBBMYKL'],
        ['HONG LEONG ISLAMIC BANK BHD',              'HLIBMYKL'],
        ['HSBC AMANAH MALAYSIA BHD',                 'HMABMYKL'],
        ['HSBC Bank',                                'HBMBMYKL'],
        ['INDUSTRIAL & COMMERCIAL BANK OF CHINA',    'ICBKMYKL'],
        ['J.P.MORGAN CHASE BANK BERHAD',             'CHASMYKX'],
        ['Kuwait Finance House (Malaysia) Berhad',   'KFHOMYKL'],
        ['Maybank',                                  'MBBEMYKL'],
        ['MAYBANK ISLAMIC BHD',                      'MBISMYKL'],
        ['MIZUHO CORPORATE BANK (MALAYSIA) BERHAD',  'MHCBMYKA'],
        ['OCBC AL-AMIN BANK BHD',                    'OABBMYKL'],
        ['OCBC Bank',                                'OCBCMYKL'],
        ['Public Bank',                              'PBBEMYKL'],
        ['PUBLIC ISLAMIC BANK BHD',                  'PIBEMYK1'],
        ['Standard Chartered Bank',                  'SCBLMYKX'],
        ['STANDARD CHARTERED SAADIQ BHD',            'SCSRMYKK'],
        ['Sumitomo Mitsui Banking Corp Msia Berhad', 'SMBCMYKL'],
        ['United Overseas Bank Berhad',              'UOVBMYKL'],
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** Create the IBG list for a company that has none. */
    public static function seedDefaults(int $companyId): void
    {
        if (static::withoutGlobalScopes()->where('company_id', $companyId)->exists()) {
            return;
        }

        foreach (self::IBG_BANKS as $i => [$name, $bic]) {
            static::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'name'       => $name,
                'bic'        => $bic,
                'sort_order' => $i,
                'is_active'  => true,
            ]);
        }
    }

    /**
     * BIC for a stored bank name, or null when the name predates the picker or
     * names a bank since removed.
     *
     * Never guesses: an unrecognised name means that employee's bank needs
     * re-picking, not a fuzzy match onto somebody else's bank.
     */
    public static function bicFor(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        return static::where('name', $name)->value('bic');
    }

    /**
     * How many employees are paid into this bank.
     *
     * Counted by NAME, not by key — `employees.bank_name` is free text of
     * historical necessity, so a plain relation would miss the very rows that
     * make a delete unsafe.
     */
    public function employeeCount(): int
    {
        return Employee::withoutGlobalScopes()
            ->where('company_id', $this->company_id)
            ->where('bank_name', $this->name)
            ->count();
    }
}
