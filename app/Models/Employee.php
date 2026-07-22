<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    protected $fillable = [
        'company_id', 'outlet_id', 'section_id', 'staff_id',
        'name', 'designation',
        'email', 'phone', 'is_active',
        'join_date', 'food_handler_certified', 'food_handler_cert_no',
        'typhoid_card', 'typhoid_valid_from', 'typhoid_expired_on',
        'employment_status', 'employment_status_date', 'outsourcing_company',
        'halal_training', 'halal_training_date',
    ];

    /**
     * Phone dial codes for the form's country selector, keyed by ISO-2.
     * Dial values are unique so an edited number maps back to one entry.
     */
    public const PHONE_COUNTRY_CODES = [
        'MY' => '+60',  'SG' => '+65',  'ID' => '+62',  'TH' => '+66',
        'PH' => '+63',  'VN' => '+84',  'BN' => '+673', 'KH' => '+855',
        'MM' => '+95',  'LA' => '+856', 'CN' => '+86',  'HK' => '+852',
        'TW' => '+886', 'JP' => '+81',  'KR' => '+82',  'IN' => '+91',
        'BD' => '+880', 'PK' => '+92',  'NP' => '+977', 'LK' => '+94',
        'AU' => '+61',  'NZ' => '+64',  'GB' => '+44',  'US' => '+1',
        'AE' => '+971', 'SA' => '+966', 'QA' => '+974',
    ];

    public const EMPLOYMENT_STATUSES = [
        'probation'          => 'Probation',
        'confirmed'          => 'Confirmed',
        'extended_probation' => 'Extended Probation',
        'outsourcing'        => 'Outsourcing',
    ];

    protected $casts = [
        'is_active'              => 'boolean',
        'join_date'              => 'date',
        'employment_status_date' => 'date',
        'food_handler_certified' => 'boolean',
        'typhoid_card'           => 'boolean',
        'typhoid_valid_from'     => 'date',
        'typhoid_expired_on'     => 'date',
        'halal_training'         => 'boolean',
        'halal_training_date'    => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function employmentStatusLabel(): ?string
    {
        return static::EMPLOYMENT_STATUSES[$this->employment_status] ?? null;
    }

    /**
     * Secondary line for the employment status: the until/since date for
     * probation states, or the provider name for outsourcing.
     */
    public function employmentStatusDetail(): ?string
    {
        return match ($this->employment_status) {
            'probation', 'extended_probation' => $this->employment_status_date
                ? 'until ' . $this->employment_status_date->format('d M Y') : null,
            'confirmed' => $this->employment_status_date
                ? 'since ' . $this->employment_status_date->format('d M Y') : null,
            'outsourcing' => $this->outsourcing_company,
            default => null,
        };
    }
}
