<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class TaxRate extends Model
{
    protected $fillable = [
        'company_id', 'country_code', 'name', 'rate',
        'is_inclusive', 'is_default', 'is_active',
    ];

    protected $casts = [
        'rate'         => 'decimal:4',
        'is_inclusive' => 'boolean',
        'is_default'   => 'boolean',
        'is_active'    => 'boolean',
    ];

    protected static function booted(): void
    {
        // Custom scope: show company-specific + system defaults (company_id IS NULL)
        static::addGlobalScope('company_or_system', function (Builder $builder) {
            $user = Auth::check() ? Auth::user() : null;
            if ($user && $user->company_id) {
                $builder->where(function ($q) use ($user) {
                    $q->where('company_id', $user->company_id)
                      ->orWhereNull('company_id');
                });
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCountry(Builder $query, string $code): Builder
    {
        return $query->where('country_code', $code);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Get the default tax rate for a company's country.
     */
    public static function defaultForCompany(?Company $company): ?self
    {
        if (! $company || ! $company->default_tax_country) return null;

        return static::where('country_code', $company->default_tax_country)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }
}
