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
