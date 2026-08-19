<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\LabelSetting;
use App\Models\ProductionRecipe;
use App\Models\Recipe;
use App\Models\ShelfLifeRule;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves how long a thing keeps in a given storage state, and turns that
 * into a use-by timestamp.
 *
 * Resolution walks a chain of candidates rather than a flat item→category
 * lookup, because the data model has two wrinkles:
 *
 *  - A prep item is BOTH a Recipe (where shelf life is edited today) and a
 *    mirrored Ingredient. A label printed against the mirror must still find
 *    the rule that was set on the recipe.
 *  - ProductionRecipe.category is a plain string with no FK, so it has no
 *    category of its own. It borrows its linked Ingredient's category.
 *
 * Falling back to the legacy single shelf_life_value is deliberately narrow:
 * it only applies when the state being asked about matches the item's own
 * storage instruction. A 3-day chill life says nothing about frozen, and
 * guessing would put a wrong date on a food-safety label.
 */
class ShelfLifeService
{
    /**
     * Resolve a shelf life for an item in a storage state.
     *
     * @return array{value: float, unit: string, source: string}|null
     *         source is one of: item, prep_recipe, category, legacy,
     *         legacy_prep_recipe — shown in the UI so a chef can see whether
     *         a life is inherited or set on the item itself.
     */
    public function resolve(?Model $item, string $storageState): ?array
    {
        if (! $item) {
            return null;
        }

        $candidates = $this->candidates($item);

        // One query for every candidate, then pick by candidate priority.
        $rules = ShelfLifeRule::where('storage_state', $storageState)
            ->where(function ($query) use ($candidates) {
                foreach ($candidates as ['model' => $model]) {
                    $query->orWhere(function ($inner) use ($model) {
                        $inner->where('ruleable_type', $model::class)
                              ->where('ruleable_id', $model->getKey());
                    });
                }
            })
            ->get();

        foreach ($candidates as ['model' => $model, 'source' => $source]) {
            $match = $rules->first(fn ($rule) => $rule->ruleable_type === $model::class
                && (int) $rule->ruleable_id === (int) $model->getKey());

            if ($match) {
                return [
                    'value'  => (float) $match->value,
                    'unit'   => $match->unit,
                    'source' => $source,
                ];
            }
        }

        return $this->legacyFallback($item, $storageState);
    }

    /**
     * Compute a use-by from a start time.
     *
     * End-of-day rounding is NEVER applied to sub-day units. Rounding a
     * 4-hour life up to 23:59 would extend it, which on a food-safety label
     * is a real hazard rather than a formatting nicety.
     */
    public function useBy(
        CarbonInterface $start,
        float $value,
        string $unit,
        string $rounding = 'eod'
    ): CarbonInterface {
        $end = match ($unit) {
            'minutes' => $start->copy()->addMinutes($value),
            'hours'   => $start->copy()->addHours($value),
            'days'    => $start->copy()->addDays($value),
            'weeks'   => $start->copy()->addWeeks($value),
            'months'  => $start->copy()->addMonths($value),
            default   => $start->copy()->addDays($value),
        };

        $isSubDay = in_array($unit, ShelfLifeRule::SUB_DAY_UNITS, true);

        if ($rounding === 'eod' && ! $isSubDay) {
            return $end->endOfDay();
        }

        return $end;
    }

    /**
     * Resolve and compute in one step — what the print screen actually calls.
     *
     * $override is a life the caller already holds (a print set line's own
     * shelf life). It outranks the rules chain because it is more specific
     * than anything the chain could find, and it works for freeform items
     * the chain cannot see at all.
     *
     * @param  array{value: float, unit: string, source: string}|null  $override
     * @return array{end_at: CarbonInterface|null, shelf_life: array|null, manual: bool}
     *         manual = true means nothing resolved and staff must type the
     *         date. That flag is also a report of which categories still
     *         need rules.
     */
    public function computeUseBy(
        ?Model $item,
        string $storageState,
        CarbonInterface $start,
        ?int $companyId = null,
        ?array $override = null
    ): array {
        $shelfLife = $override ?: $this->resolve($item, $storageState);

        if (! $shelfLife) {
            return ['end_at' => null, 'shelf_life' => null, 'manual' => true];
        }

        // Coalesce: a settings row written before use_by_rounding existed
        // would otherwise hand a null straight into useBy().
        $rounding = ($companyId
            ? LabelSetting::forCompany($companyId)->use_by_rounding
            : null) ?: 'eod';

        return [
            'end_at'     => $this->useBy($start, $shelfLife['value'], $shelfLife['unit'], $rounding),
            'shelf_life' => $shelfLife,
            'manual'     => false,
        ];
    }

    /**
     * The storage state a label defaults to for an item.
     *
     * Most label types pin a state outright. 'received' is the exception —
     * goods get labelled into whatever storage they actually go to, so it
     * follows the item's own storage instruction.
     */
    public function defaultStateFor(?Model $item, string $labelType): string
    {
        if ($labelType === 'received') {
            return $this->storageInstruction($item) ?: 'chill';
        }

        return \App\Models\LabelTemplate::DEFAULT_STORAGE_STATE[$labelType] ?? 'chill';
    }

    /**
     * Ordered lookup chain for an item, most specific first.
     *
     * @return array<int, array{model: Model, source: string}>
     */
    private function candidates(Model $item): array
    {
        $chain = [['model' => $item, 'source' => 'item']];

        // A prep Ingredient mirrors a Recipe — inherit the recipe's rules.
        $prepRecipe = $this->linkedPrepRecipe($item);
        if ($prepRecipe) {
            $chain[] = ['model' => $prepRecipe, 'source' => 'prep_recipe'];
        }

        $category = $this->categoryFor($item);
        if ($category) {
            $chain[] = ['model' => $category, 'source' => 'category'];
        }

        return $chain;
    }

    /** The Recipe behind a prep Ingredient, if this item is one. */
    private function linkedPrepRecipe(Model $item): ?Recipe
    {
        if ($item instanceof Ingredient && $item->is_prep && $item->prep_recipe_id) {
            return Recipe::find($item->prep_recipe_id);
        }

        return null;
    }

    /**
     * Category a rule may hang off. Ingredients and Recipes both use
     * IngredientCategory; a ProductionRecipe borrows its linked Ingredient's,
     * since its own `category` is a bare string with no FK.
     */
    private function categoryFor(Model $item): ?Model
    {
        return match (true) {
            $item instanceof Ingredient       => $item->ingredientCategory,
            $item instanceof Recipe           => $item->ingredientCategory,
            $item instanceof ProductionRecipe => $item->ingredient?->ingredientCategory,
            default                           => null,
        };
    }

    /**
     * Legacy single shelf life, used only when the requested state matches
     * the item's own storage instruction.
     */
    private function legacyFallback(Model $item, string $storageState): ?array
    {
        $sources = [
            ['model' => $item, 'source' => 'legacy'],
        ];

        $prepRecipe = $this->linkedPrepRecipe($item);
        if ($prepRecipe) {
            $sources[] = ['model' => $prepRecipe, 'source' => 'legacy_prep_recipe'];
        }

        foreach ($sources as ['model' => $model, 'source' => $source]) {
            if (! $this->hasLegacyShelfLife($model)) {
                continue;
            }

            // An unset storage instruction is treated as chill, matching how
            // the backfill migration read the same columns.
            $itemState = $model->storage_instruction ?: 'chill';

            if ($itemState !== $storageState) {
                continue;
            }

            return [
                'value'  => (float) $model->shelf_life_value,
                'unit'   => $model->shelf_life_unit,
                'source' => $source,
            ];
        }

        return null;
    }

    private function hasLegacyShelfLife(Model $model): bool
    {
        return ($model instanceof Recipe || $model instanceof ProductionRecipe)
            && $model->shelf_life_value > 0
            && ! empty($model->shelf_life_unit);
    }

    private function storageInstruction(?Model $item): ?string
    {
        if ($item instanceof Recipe || $item instanceof ProductionRecipe) {
            return $item->storage_instruction;
        }

        if ($item instanceof Ingredient) {
            return $this->linkedPrepRecipe($item)?->storage_instruction;
        }

        return null;
    }
}
