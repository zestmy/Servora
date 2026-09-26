<?php

namespace App\Models;

use App\Models\Concerns\PurgesStoredFiles;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * One line of the asset catalogue: a TYPE of thing the company owns.
 *
 * Deliberately not one row per physical unit — see the create migration. The
 * quantity an outlet holds is never stored here; ask AssetOnHandService, which
 * derives it from the last completed count plus the movements since.
 *
 * Names are upper-cased on save exactly as Ingredient does, so "Chef Knife"
 * typed at one outlet and "CHEF KNIFE" at another are visibly the same row in a
 * list somebody is scanning for duplicates.
 */
class Asset extends Model
{
    use HasFactory, SoftDeletes, PurgesStoredFiles;

    protected $fillable = [
        'company_id', 'name', 'code', 'asset_category_id', 'uom_id',
        'unit_cost', 'tax_rate_id', 'brand', 'model', 'image_path', 'is_active', 'remark',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'unit_cost' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::saving(function ($model) {
            if ($model->name) {
                $model->name = strtoupper($model->name);
            }
        });

        /*
         * A soft delete leaves the row, so it leaves the picture: restoring an
         * asset to a broken image would be worse than keeping a few kilobytes.
         * This fires on a force delete — a tenant purge, a cleanup — where
         * nothing else is watching the file.
         */
        static::forceDeleted(fn (self $asset) => $asset->purgeOwnedFile('image_path'));
    }

    /** Where the photograph is served from, or null when there is none. */
    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /**
     * The rate an order line for this asset is taxed at: its own, or the
     * company default, or none. The same rule Ingredient uses, so an asset and
     * an ingredient on one purchase order are taxed by the same logic.
     */
    public function effectiveTaxRate(?Company $company = null): ?TaxRate
    {
        if ($this->tax_rate_id) {
            return $this->taxRate;
        }

        return TaxRate::defaultForCompany($company);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'asset_suppliers')
            ->withPivot(['supplier_sku', 'last_cost', 'is_preferred'])
            ->withTimestamps();
    }

    public function supplierLinks(): HasMany
    {
        return $this->hasMany(AssetSupplier::class);
    }

    public function movementLines(): HasMany
    {
        return $this->hasMany(AssetMovementLine::class);
    }

    public function countLines(): HasMany
    {
        return $this->hasMany(AssetCountLine::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /**
     * Active assets, plus whichever ids a form is already holding.
     *
     * Same contract as Supplier::selectable() — a document that already names a
     * retired asset must keep naming it, or reopening last quarter's count
     * would quietly drop the rows for everything since discontinued.
     */
    public function scopeSelectable(Builder $q, $keep = []): Builder
    {
        $keep = collect(is_array($keep) ? $keep : [$keep])
            ->filter()->map(fn ($id) => (int) $id)->all();

        return $q->where(function ($sub) use ($keep) {
            $sub->where('is_active', true);

            if ($keep) {
                $sub->orWhereIn('id', $keep);
            }
        });
    }

    /** The supplier a purchase request should reach for first, if one is marked. */
    public function preferredSupplier(): ?Supplier
    {
        return $this->suppliers()->wherePivot('is_preferred', true)->first();
    }
}
