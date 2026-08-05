<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A certification or training course the company tracks against its staff.
 *
 * Managed under Settings → Certifications & Training and chosen on the employee
 * form. The three built-in documents (typhoid, food handler, halal) are columns
 * on `employees` rather than rows here — see the migration for why.
 */
class CertificationType extends Model
{
    protected $fillable = [
        'company_id', 'name', 'description', 'has_expiry', 'is_required', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'has_expiry'  => 'boolean',
        'is_required' => 'boolean',
        'is_active'   => 'boolean',
    ];

    /**
     * MySQL does not read column defaults back after an insert, so a freshly
     * created type would report has_expiry as null until reloaded.
     */
    protected $attributes = [
        'has_expiry'  => true,
        'is_required' => false,
        'sort_order'  => 0,
        'is_active'   => true,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employeeCertifications(): HasMany
    {
        return $this->hasMany(EmployeeCertification::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
