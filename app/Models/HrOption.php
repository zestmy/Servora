<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;

/**
 * A value on one of the employee particulars pick-lists — sex, nationality,
 * race, religion, marital status, education.
 *
 * ONE table for all of them rather than six near-identical ones. They differ
 * only in what they are called: same shape, same CRUD, same seeding, same
 * "somebody is already recorded as this" rule. A seventh list is an entry in
 * TYPES plus a nullable column, not another model, screen and migration.
 *
 * Like `bank_name`, the employee stores the VALUE, not a key — see the
 * migration. The settings screen carries employees across a rename so the two
 * cannot drift apart.
 */
class HrOption extends Model
{
    protected $fillable = ['company_id', 'type', 'name', 'code', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * The lists, the employee column each fills, and what a company starts with.
     *
     * `column` is the point of the map: it lets the settings screen cascade a
     * rename onto the employees holding the old value without knowing which
     * list it is looking at.
     *
     * Defaults are a STARTING POINT, not a standard — every company edits them.
     * They lean Malaysian because the statutory forms this feeds do.
     */
    public const TYPES = [
        'gender' => [
            'label'    => 'Sex',
            'singular' => 'sex',
            'column'   => 'gender',
            'note'     => 'Asked for by SOCSO and the EA form.',
            'defaults' => ['Male', 'Female'],
        ],
        'nationality' => [
            'label'    => 'Nationality',
            'singular' => 'nationality',
            'column'   => 'nationality',
            'note'     => 'Drives whether someone is treated as a local or a foreign worker for EPF and SOCSO.',
            'defaults' => [
                'Malaysian', 'Bangladeshi', 'Nepalese', 'Myanmar', 'Indonesian',
                'Indian', 'Pakistani', 'Filipino', 'Vietnamese', 'Thai',
                'Sri Lankan', 'Cambodian', 'Laotian', 'Chinese', 'Singaporean', 'Other',
            ],
        ],
        'race' => [
            'label'    => 'Race',
            'singular' => 'race',
            'column'   => 'race',
            'note'     => 'Reported on HRD Corp and some statutory returns.',
            'defaults' => ['Malay', 'Chinese', 'Indian', 'Bumiputera Sabah', 'Bumiputera Sarawak', 'Orang Asli', 'Other'],
        ],
        'religion' => [
            'label'    => 'Religion',
            'singular' => 'religion',
            'column'   => 'religion',
            'note'     => 'Used for zakat handling and for rostering around religious holidays.',
            'defaults' => ['Islam', 'Buddhist', 'Christian', 'Hindu', 'Sikh', 'Taoist', 'Other', 'None'],
        ],
        'marital_status' => [
            'label'    => 'Marital Status',
            'singular' => 'marital status',
            'column'   => 'marital_status',
            'note'     => 'Separate from the PCB category on the Statutory tab, which is what actually sets the tax relief.',
            'defaults' => ['Single', 'Married', 'Divorced', 'Widowed'],
        ],
        'relationship' => [
            'label'    => 'Relationship',
            'singular' => 'relationship',
            'column'   => 'emergency_contact_relationship',
            'note'     => 'Who the emergency contact is to the employee.',
            'defaults' => ['Spouse', 'Parent', 'Sibling', 'Child', 'Relative', 'Friend', 'Guardian', 'Other'],
        ],
        'education_level' => [
            'label'    => 'Education Level',
            'singular' => 'education level',
            'column'   => 'education_level',
            'note'     => 'Highest qualification held. Reported on HRD Corp training claims.',
            'defaults' => [
                'No formal education', 'Primary', 'PMR / SRP', 'SPM', 'STPM',
                'Certificate', 'Diploma', 'Degree', "Master's", 'Doctorate', 'Other',
            ],
        ],
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** The employees column a type fills, e.g. 'nationality'. */
    public static function columnFor(string $type): string
    {
        return self::TYPES[$type]['column'];
    }

    public static function labelFor(string $type): string
    {
        return self::TYPES[$type]['label'];
    }

    /**
     * Seed any list this company has none of.
     *
     * Per TYPE rather than per company, so a list added to TYPES later seeds
     * itself for companies that already have the others — without touching a
     * list somebody has deliberately emptied down to two entries.
     */
    public static function seedDefaults(int $companyId): void
    {
        $have = static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->distinct()
            ->pluck('type')
            ->all();

        foreach (self::TYPES as $type => $meta) {
            if (in_array($type, $have, true)) {
                continue;
            }

            foreach ($meta['defaults'] as $i => $name) {
                static::withoutGlobalScopes()->create([
                    'company_id' => $companyId,
                    'type'       => $type,
                    'name'       => $name,
                    'sort_order' => $i,
                    'is_active'  => true,
                ]);
            }
        }
    }

    /**
     * The active values of one list, plus $keep if it is no longer among them.
     *
     * The keep is what stops an ordinary edit wiping a particular whose value
     * was since retired from the list — the same rule the bank picker follows.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public static function namesFor(string $type, ?string $keep = null): \Illuminate\Support\Collection
    {
        $names = static::ofType($type)->active()->ordered()->pluck('name');

        if ($keep !== null && $keep !== '' && ! $names->contains($keep)) {
            $names->push($keep);
        }

        return $names;
    }

    /** How many employees hold this value. Counted by value, not by key. */
    public function employeeCount(): int
    {
        return Employee::withoutGlobalScopes()
            ->where('company_id', $this->company_id)
            ->where(self::columnFor($this->type), $this->name)
            ->count();
    }
}
