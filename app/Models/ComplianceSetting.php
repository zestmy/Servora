<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which built-in compliance documents expire for this company.
 *
 * A food handler certificate and halal awareness training are usually attended
 * once; the typhoid jab is the one that lapses. A company that renews the
 * others turns them on here, and they join the expiry card and the reminder
 * email. Documents that do not expire are simply not part of an expiry report
 * — their held/not-held state still shows on the Employees list.
 */
class ComplianceSetting extends Model
{
    protected $fillable = [
        'company_id', 'typhoid_expires', 'food_handler_expires', 'halal_training_expires',
    ];

    protected $casts = [
        'typhoid_expires'        => 'boolean',
        'food_handler_expires'   => 'boolean',
        'halal_training_expires' => 'boolean',
    ];

    /** MySQL does not read column defaults back after an insert. */
    protected $attributes = [
        'typhoid_expires'        => true,
        'food_handler_expires'   => false,
        'halal_training_expires' => false,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** The company's settings, or unsaved defaults. Never creates a row on read. */
    public static function forCompany(int $companyId): self
    {
        return static::withoutGlobalScope(CompanyScope::class)
            ->firstWhere('company_id', $companyId)
            ?? new static(['company_id' => $companyId]);
    }

    /** Whether a built-in document key expires for this company. */
    public function expires(string $documentKey): bool
    {
        return (bool) match ($documentKey) {
            'typhoid'      => $this->typhoid_expires,
            'food_handler' => $this->food_handler_expires,
            'halal'        => $this->halal_training_expires,
            default        => false,
        };
    }
}
