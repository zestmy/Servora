<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-company label printing settings. One row per company.
 */
class LabelSetting extends Model
{
    public const ROUNDING_OPTIONS = [
        'eod'   => 'End of day (23:59)',
        'exact' => 'Exact time',
    ];

    protected $fillable = [
        'company_id', 'use_by_rounding', 'default_template_id',
        'footer_text', 'printnode_api_key', 'storage_temperatures',
    ];

    protected $casts = [
        // Never store this in plain text. Unused under the browser driver.
        'printnode_api_key'    => 'encrypted',
        'storage_temperatures' => 'array',
    ];

    /**
     * Mirrors the column defaults. Without this, a row created by
     * forCompany() reads back with a null use_by_rounding — Eloquent does
     * not refresh DB-side defaults after an insert.
     */
    protected $attributes = [
        'use_by_rounding' => 'eod',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function defaultTemplate(): BelongsTo
    {
        return $this->belongsTo(LabelTemplate::class, 'default_template_id');
    }

    /**
     * Settings for a company, creating the row on first access so callers
     * never have to null-check. Companies that have never opened the label
     * settings screen still get the documented defaults.
     */
    public static function forCompany(int $companyId): self
    {
        return static::withoutGlobalScope(CompanyScope::class)
            ->firstOrCreate(['company_id' => $companyId]);
    }

    /**
     * Storage temperature ranges for this company: the standard HACCP
     * figures with any of this company's own wording layered on top.
     *
     * Only real overrides are stored, so a company that never touched
     * chilled still picks up a change to the default. A blank override is
     * treated as "no override" rather than "no temperature" — clearing the
     * field in settings is how you go back to the default.
     *
     * @return array<string, string>
     */
    public function temperatures(): array
    {
        $overrides = collect($this->storage_temperatures ?? [])
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => trim((string) $value))
            ->all();

        return array_merge(ShelfLifeRule::STORAGE_TEMPERATURES, $overrides);
    }

    /** Convenience for callers that only have a company id. */
    public static function temperaturesFor(?int $companyId): array
    {
        if (! $companyId) {
            return ShelfLifeRule::STORAGE_TEMPERATURES;
        }

        return static::forCompany($companyId)->temperatures();
    }
}
