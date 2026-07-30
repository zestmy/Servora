<?php

namespace App\Livewire\Labels;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\ProductionRecipe;
use App\Models\Recipe;
use App\Models\ShelfLifeRule;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The shelf life matrix: how long each thing keeps, per storage state.
 *
 * Categories are the intended place to work — one row there covers every
 * item in it, which is the whole reason the matrix defaults at category
 * level. The item scopes exist for the exceptions.
 *
 * In an item scope, a cell left empty shows the value it would inherit from
 * its category as placeholder text, so it is obvious at a glance whether a
 * blank means "not covered" or "covered by the category".
 */
class ShelfLifeGrid extends Component
{
    use WithPagination;

    /** category | ingredient | prep | production */
    public string $scope = 'category';

    public string $search = '';

    /** Only show rows with no rule at all — the gaps worth filling. */
    public bool $gapsOnly = false;

    /** [row_id][storage_state] => ['value' => string, 'unit' => string] */
    public array $cells = [];

    /** Bulk apply, per storage state. */
    public array $bulk = [];

    public const SCOPES = [
        'category'   => 'Categories',
        'ingredient' => 'Market List items',
        'prep'       => 'Prep items',
        'production' => 'Production recipes',
    ];

    private const MODELS = [
        'category'   => IngredientCategory::class,
        'ingredient' => Ingredient::class,
        'prep'       => Recipe::class,
        'production' => ProductionRecipe::class,
    ];

    public function updatedScope(): void
    {
        $this->resetPage();
        $this->cells = [];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedGapsOnly(): void
    {
        $this->resetPage();
    }

    /**
     * Persist the moment a cell changes.
     *
     * $key arrives as "<row id>.<storage state>.<value|unit>". Editing the
     * unit alone does nothing until there is a value to go with it, so a
     * half-filled cell never writes a meaningless rule.
     */
    public function updatedCells($value, string $key): void
    {
        [$rowId, $state] = array_pad(explode('.', $key), 2, null);

        if (! $rowId || ! $state) {
            return;
        }

        $this->persist((int) $rowId, $state);
    }

    /** Apply one value + unit to every row currently on screen. */
    public function bulkApply(string $state): void
    {
        $value = $this->bulk[$state]['value'] ?? '';
        $unit  = $this->bulk[$state]['unit'] ?? 'days';

        if (! is_numeric($value) || (float) $value <= 0) {
            $this->addError('bulk', 'Enter a value greater than zero before applying.');

            return;
        }

        foreach ($this->rowsForPage() as $row) {
            $this->cells[$row->id][$state] = ['value' => (string) $value, 'unit' => $unit];
            $this->persist($row->id, $state);
        }

        session()->flash('success', sprintf(
            'Applied %s %s to %s on this page.',
            $value,
            $unit,
            ShelfLifeRule::stateLabel($state)
        ));
    }

    /** Remove a rule outright. */
    public function clearCell(int $rowId, string $state): void
    {
        $this->cells[$rowId][$state] = ['value' => '', 'unit' => 'days'];
        $this->persist($rowId, $state);
    }

    /**
     * Write or delete one cell.
     *
     * An empty or non-positive value deletes the rule rather than storing a
     * zero — a zero-length shelf life would print a use-by equal to the prep
     * time, which is worse than having no rule at all.
     */
    private function persist(int $rowId, string $state): void
    {
        $cell  = $this->cells[$rowId][$state] ?? [];
        $value = $cell['value'] ?? '';
        $unit  = $cell['unit'] ?? 'days';

        $identity = [
            'ruleable_type' => self::MODELS[$this->scope],
            'ruleable_id'   => $rowId,
            'storage_state' => $state,
        ];

        if (! is_numeric($value) || (float) $value <= 0) {
            ShelfLifeRule::where($identity)->delete();

            return;
        }

        if (! array_key_exists($unit, Recipe::SHELF_LIFE_UNITS)) {
            $unit = 'days';
        }

        ShelfLifeRule::updateOrCreate($identity, [
            'company_id' => Auth::user()->company_id,
            'value'      => (float) $value,
            'unit'       => $unit,
        ]);
    }

    public function render()
    {
        $rows = $this->rowsForPage();

        $this->loadCells($rows);

        return view('livewire.labels.shelf-life-grid', [
            'rows'      => $rows,
            'scopes'    => self::SCOPES,
            'states'    => ShelfLifeRule::STORAGE_STATES,
            'units'     => Recipe::SHELF_LIFE_UNITS,
            'inherited' => $this->inheritedMap($rows),
            'coverage'  => $this->coverage(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Shelf life']);
    }

    /** The paginated rows for the current scope. */
    private function rowsForPage()
    {
        return $this->baseQuery()->paginate(25);
    }

    private function baseQuery()
    {
        $query = match ($this->scope) {
            'ingredient' => Ingredient::query()->where('is_prep', false),
            'prep'       => Recipe::query()->where('is_prep', true),
            'production' => ProductionRecipe::query()->with('ingredient.ingredientCategory'),
            default      => IngredientCategory::query(),
        };

        if ($this->search !== '') {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        if ($this->gapsOnly) {
            // Subquery rather than a morphMany on four core models — the
            // label module stays self-contained for the sake of one filter.
            $query->whereNotIn('id', ShelfLifeRule::query()
                ->where('ruleable_type', self::MODELS[$this->scope])
                ->select('ruleable_id'));
        }

        return $query->orderBy('name');
    }

    /** Load existing rules for the visible rows into component state. */
    private function loadCells($rows): void
    {
        $existing = ShelfLifeRule::where('ruleable_type', self::MODELS[$this->scope])
            ->whereIn('ruleable_id', $rows->pluck('id'))
            ->get();

        foreach ($rows as $row) {
            foreach (array_keys(ShelfLifeRule::STORAGE_STATES) as $state) {
                if (isset($this->cells[$row->id][$state])) {
                    continue;
                }

                $match = $existing->first(fn ($r) => (int) $r->ruleable_id === (int) $row->id
                    && $r->storage_state === $state);

                $this->cells[$row->id][$state] = [
                    'value' => $match ? rtrim(rtrim((string) $match->value, '0'), '.') : '',
                    'unit'  => $match->unit ?? 'days',
                ];
            }
        }
    }

    /**
     * What each item row would inherit from its category, shown as
     * placeholder text.
     *
     * Built from one query over the visible rows' categories rather than
     * calling ShelfLifeService per cell — that would be 150 queries a page.
     *
     * @return array<int, array<string, string>> [row_id][state] => "5 days"
     */
    private function inheritedMap($rows): array
    {
        if ($this->scope === 'category') {
            return [];
        }

        $categoryByRow = [];

        foreach ($rows as $row) {
            $categoryByRow[$row->id] = $this->scope === 'production'
                ? $row->ingredient?->ingredient_category_id
                : $row->ingredient_category_id;
        }

        $categoryIds = array_filter(array_unique($categoryByRow));

        if (! $categoryIds) {
            return [];
        }

        $categoryRules = ShelfLifeRule::where('ruleable_type', IngredientCategory::class)
            ->whereIn('ruleable_id', $categoryIds)
            ->get()
            ->groupBy('ruleable_id');

        $map = [];

        foreach ($categoryByRow as $rowId => $categoryId) {
            foreach ($categoryRules[$categoryId] ?? [] as $rule) {
                $value = rtrim(rtrim((string) $rule->value, '0'), '.');
                $map[$rowId][$rule->storage_state] = $value . ' ' . $rule->unit;
            }
        }

        return $map;
    }

    /**
     * How many categories still have no chill rule.
     *
     * Chill is the state behind the most-used label type, so a gap here is
     * the one that actually makes chefs type dates by hand.
     */
    private function coverage(): array
    {
        $total = IngredientCategory::count();

        $covered = ShelfLifeRule::where('ruleable_type', IngredientCategory::class)
            ->where('storage_state', 'chill')
            ->distinct()
            ->count('ruleable_id');

        return ['total' => $total, 'covered' => $covered];
    }
}
