<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierItemAlias extends Model
{
    protected $fillable = [
        'company_id', 'supplier_id', 'ingredient_id',
        'extracted_description', 'normalized_description',
        'times_used',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }

    /**
     * Normalize a description for consistent matching.
     */
    public static function normalize(string $description): string
    {
        $text = strtoupper(trim($description));
        $text = preg_replace('/[^A-Z0-9\s]/', '', $text);
        return preg_replace('/\s+/', ' ', $text);
    }

    /**
     * Look up an alias for a given supplier + description.
     */
    public static function findMatch(int $supplierId, string $description): ?self
    {
        $normalized = self::normalize($description);
        if (! $normalized) return null;

        return static::where('supplier_id', $supplierId)
            ->where('normalized_description', $normalized)
            ->first();
    }

    /**
     * Learn a new alias or reinforce an existing one.
     */
    public static function learn(int $companyId, int $supplierId, string $description, int $ingredientId): void
    {
        $normalized = self::normalize($description);
        if (! $normalized) return;

        $alias = static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('supplier_id', $supplierId)
            ->where('normalized_description', $normalized)
            ->first();

        if ($alias) {
            if ($alias->ingredient_id === $ingredientId) {
                $alias->increment('times_used');
            } else {
                // User corrected — update to new ingredient
                $alias->update([
                    'ingredient_id'          => $ingredientId,
                    'extracted_description'  => $description,
                    'times_used'             => $alias->times_used + 1,
                ]);
            }
        } else {
            static::withoutGlobalScopes()->create([
                'company_id'              => $companyId,
                'supplier_id'             => $supplierId,
                'ingredient_id'           => $ingredientId,
                'extracted_description'   => $description,
                'normalized_description'  => $normalized,
            ]);
        }
    }
}
