<?php

namespace App\Services\Labels;

use App\Models\Ingredient;
use App\Models\ProductionRecipe;
use App\Models\Recipe;
use App\Scopes\CompanyScope;

/**
 * Item lookup for every screen that adds something to a label or a set.
 *
 * One implementation, because there were two — the manager set editor had its
 * own copy of the query and the staff screens had another, and they had
 * already drifted on group names.
 *
 * Groups are deliberately not merged: someone searching "chicken" needs to
 * see whether they are picking the raw ingredient, the prep item or the
 * finished recipe, and a flat list hides that. Ingredients with is_prep are
 * excluded from Market List on purpose — a prep item is a Recipe plus a
 * synced Ingredient, so including both would list every prep item twice, and
 * the Recipe is the one that carries the shelf-life rule.
 *
 * TWO THINGS THIS FIXES over what it replaces:
 *
 *  1. The old limit was 6 per group and nothing said so. Search a catalogue
 *     with twenty matching ingredients and you saw six, chosen alphabetically
 *     — the rest were simply absent, and would "appear" later only because
 *     typing more characters shrank the match set until they fell inside the
 *     six. That is the reported bug. The limit is now 25 and, more
 *     importantly, truncation is REPORTED rather than silent.
 *  2. Results were ordered by name, so "chi" put BAKED CHICKEN above CHICKEN.
 *     Ranking now runs exact, then starts-with, then contains, then name, so
 *     what you typed comes first.
 */
class LabelItemSearch
{
    /** Enough to scroll, few enough not to become the whole catalogue. */
    public const LIMIT = 25;

    public const MIN_CHARS = 2;

    /**
     * @return array<string, array{items: array<int, array{type: string, id: int, name: string}>, total: int, truncated: bool}>
     */
    public function groups(?int $companyId, string $term, int $limit = self::LIMIT): array
    {
        $term = trim($term);

        if (! $companyId || mb_strlen($term) < self::MIN_CHARS) {
            return [];
        }

        // Four groups, in the order a kitchen thinks about them. Prep items
        // have their own: they are Recipe rows with is_prep, and while they
        // were always findable, sharing a group with every other recipe meant
        // a capped list buried them behind whatever sorted earlier — and prep
        // items are the things most likely to need a label. Their own group
        // gives them their own count and their own share of the limit.
        //
        // is_prep is NOT NULL DEFAULT 0 on both tables, so splitting on it
        // cannot drop a row into neither group.
        $groups = [
            'Market List'        => Ingredient::query()->where('is_prep', false),
            'Prep Items'         => Recipe::query()->where('is_prep', true),
            'Recipes'            => Recipe::query()->where('is_prep', false),
            'Production Recipes' => ProductionRecipe::query(),
        ];

        // Prep items are Recipes, so they keep the 'recipe' key — everything
        // downstream resolves them the same way it always did.
        $types = [
            'Market List'        => 'ingredient',
            'Prep Items'         => 'recipe',
            'Recipes'            => 'recipe',
            'Production Recipes' => 'production',
        ];

        $out = [];

        foreach ($groups as $label => $query) {
            // Scoped by hand: the staff app runs with no authenticated web
            // user, so CompanyScope resolves to nothing there.
            $query->withoutGlobalScope(CompanyScope::class)->where('company_id', $companyId);

            $this->constrain($query, $term);

            // Counted before the limit — you cannot report what you dropped
            // if you never asked how much there was.
            $total = (clone $query)->toBase()->getCountForPagination();

            $items = $query->limit($limit)->get()
                ->map(fn ($m) => ['type' => $types[$label], 'id' => $m->id, 'name' => $m->name])
                ->all();

            if (! $items) {
                continue;
            }

            $out[$label] = [
                'items'     => $items,
                'total'     => $total,
                'truncated' => $total > count($items),
            ];
        }

        return $out;
    }

    /** Flat list of just the items, for callers that don't show groups. */
    public function flat(?int $companyId, string $term, int $limit = self::LIMIT): array
    {
        return collect($this->groups($companyId, $term, $limit))
            ->flatMap(fn ($g) => $g['items'])->all();
    }

    /** Company-scoped lookup of one item by its short type key. */
    public function find(?int $companyId, ?string $type, ?int $id)
    {
        if (! $companyId || ! $type || ! $id) {
            return null;
        }

        $query = match ($type) {
            'ingredient' => Ingredient::query(),
            'recipe'     => Recipe::query(),
            'production' => ProductionRecipe::query(),
            default      => null,
        };

        return $query?->withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)->find($id);
    }

    /**
     * Match on the term and rank by how well it matched.
     *
     * LIKE wildcards in the term itself are escaped: a name containing an
     * underscore is common enough, and unescaped it would match any single
     * character and quietly widen the search.
     */
    private function constrain($query, string $term): void
    {
        $escaped = addcslashes($term, '%_\\');

        $query->where('name', 'like', '%' . $escaped . '%')
            ->orderByRaw('CASE WHEN name = ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END', [
                $term,
                $escaped . '%',
            ])
            ->orderBy('name');
    }
}
