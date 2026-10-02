<?php

namespace App\Livewire\Inventory;

use App\Models\FormTemplate;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\StockTake;
use App\Models\StockTakeLine;
use App\Traits\LocksLineUnitCost;
use App\Traits\PicksRecordOutlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class StockTakeForm extends Component
{
    use PicksRecordOutlet, LocksLineUnitCost;

    #[Locked]
    public ?int $recordId      = null;
    public ?int $department_id = null;

    public string $stock_take_date  = '';
    public string $reference_number = '';
    public string $notes            = '';
    #[Locked]
    public string $status           = 'draft';
    public string $method           = 'detailed'; // 'detailed' or 'summary'

    // Summary method: total amount keyed in directly
    public string $summary_amount   = '0';

    // Detailed method lines
    public array  $lines            = [];
    public string $ingredientSearch = '';
    // Counters work blind by default: seeing the expected figure invites
    // copying it down instead of counting.
    public bool   $hideSystemQty    = true;

    /**
     * ingredient id => recipe units per purchase unit, for lines that have a
     * Purchase UOM column. Server-authored and #[Locked] for the same reason
     * as the cost map: a factor from the browser would scale the stock saved.
     *
     * Each line is counted as pack_quantity (purchase UOM, full packs) plus
     * actual_quantity (recipe UOM, loose). In the FORM actual_quantity is the
     * loose part only; what is stored as actual_quantity is the total in the
     * recipe UOM (see countedTotal()), which is what every report reads.
     *
     * @var array<int, float>
     */
    #[Locked]
    public array  $packFactors      = [];

    // Template picker
    public string $selectedTemplateId = '';

    protected function rules(): array
    {
        $rules = [
            'outlet_id'        => 'required|integer',
            'stock_take_date'  => 'required|date',
            'reference_number' => 'nullable|string|max:100',
            'notes'            => 'nullable|string',
            'method'           => 'required|in:detailed,summary',
            'department_id'    => 'required|exists:departments,id',
        ];

        if ($this->method === 'summary') {
            $rules['summary_amount'] = 'required|numeric|min:0';
        } else {
            $rules['lines']                    = 'required|array|min:1';
            $rules['lines.*.actual_quantity']  = 'required|numeric|min:0';
            $rules['lines.*.pack_quantity']    = 'nullable|numeric|min:0';
            // unit_cost is derived, not entered — see LocksLineUnitCost.
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'outlet_id.required'               => 'Select an outlet for this stock take.',
            'department_id.required'           => 'Select a department for this stock take.',
            'summary_amount.required'          => 'Enter the total stock value.',
            'lines.required'                   => 'Add at least one ingredient.',
            'lines.min'                        => 'Add at least one ingredient.',
            'lines.*.actual_quantity.required' => 'Actual quantity is required.',
            'lines.*.actual_quantity.min'      => 'Quantity cannot be negative.',
            'lines.*.pack_quantity.numeric'    => 'Enter a number of packs.',
            'lines.*.pack_quantity.min'        => 'Quantity cannot be negative.',
        ];
    }

    public function mount(?int $id = null): void
    {
        $this->stock_take_date = now()->toDateString();

        if (! $id) {
            $this->initOutlet();
            return;
        }

        $record = StockTake::with([
            'lines.uom',
            'lines.ingredient.baseUom',
            'lines.ingredient.recipeUom',
            'lines.ingredient.uomConversions',
            'lines.ingredient.ingredientCategory.parent',
        ])->findOrFail($id);

        $this->recordId         = $record->id;
        $this->initOutlet($record->outlet_id);
        $this->department_id    = $record->department_id;
        $this->stock_take_date  = $record->stock_take_date->toDateString();
        $this->reference_number = $record->reference_number ?? '';
        $this->notes            = $record->notes ?? '';
        $this->status           = $record->status;
        $this->method           = $record->method ?? 'detailed';
        $this->summary_amount   = (string) floatval($record->total_stock_cost);

        $refreshCost = $record->status !== 'completed';
        $uomService  = app(\App\Services\UomService::class);

        $this->lines = $record->lines->map(function ($l) use ($refreshCost, $uomService) {
            // A draft re-prices off the ingredient's live cost, but it has to be
            // priced in the UOM the line is COUNTED in. current_cost is per BASE
            // UOM, so using it raw turns cost-per-pack into cost-per-piece the
            // moment a draft is reopened (RM2.845 becomes RM28.45 on a 1:10 base
            // to recipe conversion). Same call buildLine() uses when adding a row.
            $unitCost = floatval($l->unit_cost);

            if ($refreshCost && $l->ingredient) {
                $countUom = $l->uom ?: ($l->ingredient->recipeUom ?: $l->ingredient->baseUom);
                $unitCost = $countUom
                    ? $uomService->convertCost($l->ingredient, $countUom)
                    : floatval($l->ingredient->current_cost);
            }

            // Keep the variance in step with the cost we just refreshed, or the
            // row shows a variance value that no longer matches qty x unit cost.
            $varianceQty  = floatval($l->variance_quantity);
            $varianceCost = $refreshCost
                ? round($varianceQty * $unitCost, 4)
                : floatval($l->variance_cost);

            // Show the count the way it was typed: packs + loose when it was
            // split, otherwise the stored total as loose.
            $factor = StockTakeLine::packFactor($l->ingredient, $l->uom);
            $split  = $factor && $l->pack_quantity !== null;

            return $this->withPackFactor($this->rememberLineCost([
                'ingredient_id'        => $l->ingredient_id,
                'ingredient_name'      => $l->ingredient?->name ?? '(Deleted ingredient)',
                'is_prep'              => (bool) ($l->ingredient?->is_prep ?? false),
                'uom_id'               => $l->uom_id,
                'uom_abbr'             => $l->uom->abbreviation ?? '',
                'pack_uom_abbr'        => $factor ? ($l->ingredient->baseUom->abbreviation ?? '') : '',
                'system_quantity'      => (string) floatval($l->system_quantity),
                'pack_quantity'        => $split ? (string) floatval($l->pack_quantity) : '',
                'actual_quantity'      => (string) floatval($split ? $l->loose_quantity : $l->actual_quantity),
                'counted_quantity'     => floatval($l->actual_quantity),
                'variance_quantity'    => $varianceQty,
                'variance_cost'        => $varianceCost,
                ...$this->categoryFields($l->ingredient),
            ], $unitCost), $factor);
        })->toArray();

        // A draft re-derives totals off today's pack size.
        if ($refreshCost) {
            foreach (array_keys($this->lines) as $idx) {
                $this->recalcLine($idx);
            }
        }
    }

    /** Reorder lines by new sequence of indexes (from drag-drop). */
    public function reorderLines(array $orderedIndexes): void
    {
        // Every line exactly once, or leave the order alone.
        //
        // Counting the result was not enough: a list that names one row twice
        // and another not at all is the same length, so it passed — and stored
        // one count under two items while losing a third. The order arrives
        // from the browser and only ever reorders; it is not a place to accept
        // a set of rows different from the one we hold.
        if (! $this->lines) {
            return;
        }

        $wanted = array_map('intval', $orderedIndexes);

        if (array_keys($this->lines) !== range(0, count($this->lines) - 1)) {
            $this->lines = array_values($this->lines);
        }

        $existing = range(0, count($this->lines) - 1);

        sort($wanted);
        if ($wanted !== $existing) {
            return;
        }

        $ordered = [];
        foreach ($orderedIndexes as $idx) {
            $ordered[] = $this->lines[(int) $idx];
        }

        $this->lines = $ordered;
    }

    // ── Add ingredient from search ────────────────────────────────────────

    public function addIngredient(int $ingredientId): void
    {
        foreach ($this->lines as $line) {
            if ((int) $line['ingredient_id'] === $ingredientId) {
                $this->ingredientSearch = '';
                return;
            }
        }

        $ingredient = Ingredient::with(['baseUom', 'recipeUom', 'uomConversions', 'ingredientCategory.parent'])->findOrFail($ingredientId);
        $this->lines[] = $this->buildLine($ingredient);
        $this->ingredientSearch = '';
    }

    // ── Load all active ingredients ───────────────────────────────────────

    public function loadAll(): void
    {
        $existing = collect($this->lines)->pluck('ingredient_id')->map(fn ($id) => (int) $id)->toArray();

        $ingredients = Ingredient::with(['baseUom', 'recipeUom', 'uomConversions', 'ingredientCategory.parent'])
            ->where('is_active', true)
            ->when($existing, fn ($q) => $q->whereNotIn('id', $existing))
            ->orderBy('name')
            ->get();

        foreach ($ingredients as $ingredient) {
            $this->lines[] = $this->buildLine($ingredient);
        }
    }

    // ── Load from template ────────────────────────────────────────────────

    public function loadTemplate(): void
    {
        if (! $this->selectedTemplateId) return;

        $template = FormTemplate::with([
            'lines.ingredient.baseUom',
            'lines.ingredient.recipeUom',
            'lines.ingredient.uomConversions',
            'lines.ingredient.ingredientCategory.parent',
        ])->find((int) $this->selectedTemplateId);

        if (! $template) {
            $this->selectedTemplateId = '';
            return;
        }

        $existing = collect($this->lines)->pluck('ingredient_id')->map(fn ($id) => (int) $id)->toArray();
        $added = 0;

        foreach ($template->lines as $tLine) {
            if ($tLine->item_type !== 'ingredient' || ! $tLine->ingredient) continue;
            if (in_array($tLine->ingredient_id, $existing)) continue;

            $this->lines[] = $this->buildLine($tLine->ingredient);
            $existing[] = $tLine->ingredient_id;
            $added++;
        }

        $this->selectedTemplateId = '';

        if ($added === 0) {
            session()->flash('info', 'All items from that template are already in the form.');
        }
    }

    public function removeLine(int $idx): void
    {
        unset($this->lines[$idx]);
        $this->lines = array_values($this->lines);
    }

    public function updatedLines($value, $key): void
    {
        $parts = explode('.', $key);
        if (count($parts) === 2 && in_array($parts[1], ['actual_quantity', 'pack_quantity', 'system_quantity'])) {
            $this->recalcLine((int) $parts[0]);
        }
    }

    // ── Save (draft / complete) ───────────────────────────────────────────

    public function save(string $action = 'save'): void
    {
        // Re-checked here, not just on the route: a Livewire action is its own request.
        abort_unless(auth()->user()?->canDo('inventory.stock_takes.record'), 403);

        // The stored record, not the component's copy, decides what may be changed: a
        // completed count is final until someone with the reopen ability reopens it.
        $existing = $this->recordId ? StockTake::findOrFail($this->recordId) : null;
        if ($existing) {
            abort_unless(
                ! $existing->outlet_id || auth()->user()->canAccessOutlet((int) $existing->outlet_id),
                403
            );
            if ($existing->status === 'completed') {
                abort_unless(auth()->user()->canDo('inventory.stock_takes.reopen'), 403);
            }
        }

        $this->validate();

        $newStatus = ($action === 'complete') ? 'completed' : ($existing?->status ?? 'draft');

        // On completion, drop any detailed line left at 0 so the final record
        // only contains items that were actually counted. Drafts keep every
        // line so counting can continue.
        $lines = collect($this->lines);
        if ($action === 'complete') {
            $lines = $lines->filter(fn ($l) => $this->countedTotal($l) > 0)->values();

            if ($this->method === 'detailed' && $lines->isEmpty()) {
                $this->addError('lines', 'Enter a counted quantity for at least one item before completing.');
                return;
            }
        }

        if ($this->method === 'summary') {
            $totalStockCost    = floatval($this->summary_amount);
            $totalVarianceCost = 0;
        } else {
            $totalVarianceCost = $lines->sum(fn ($l) => round(
                ($this->countedTotal($l) - floatval($l['system_quantity'])) * $this->lockedLineCost($l), 4
            ));
            $totalStockCost    = $lines->sum(fn ($l) => $this->countedTotal($l) * $this->lockedLineCost($l));
        }

        $data = [
            'department_id'       => $this->department_id ?: null,
            'stock_take_date'     => $this->stock_take_date,
            'reference_number'    => $this->reference_number ?: null,
            'notes'               => $this->notes ?: null,
            'status'              => $newStatus,
            'method'              => $this->method,
            'total_variance_cost' => round($totalVarianceCost, 4),
            'total_stock_cost'    => round($totalStockCost, 4),
        ];

        if ($existing) {
            $record = $existing;
            $record->update($data);
        } else {
            $data['company_id'] = Auth::user()->company_id;
            $data['outlet_id']  = $this->resolveOutletId();
            $data['created_by'] = Auth::id();
            $record = StockTake::create($data);
        }

        // Sync lines (detailed method only)
        $record->lines()->delete();
        if ($this->method === 'detailed') {
            foreach ($lines as $line) {
                $actualQty    = $this->countedTotal($line);
                $systemQty    = floatval($line['system_quantity']);
                $hasPacks     = $this->packFactor($line) !== null && trim((string) ($line['pack_quantity'] ?? '')) !== '';
                $varianceQty  = $actualQty - $systemQty;
                $unitCost     = $this->lockedLineCost($line);
                $varianceCost = $varianceQty * $unitCost;

                $record->lines()->create([
                    'ingredient_id'     => $line['ingredient_id'],
                    'uom_id'            => $line['uom_id'],
                    'system_quantity'   => $systemQty,
                    'actual_quantity'   => $actualQty,
                    'pack_quantity'     => $hasPacks ? floatval($line['pack_quantity']) : null,
                    'loose_quantity'    => $hasPacks ? floatval($line['actual_quantity']) : null,
                    'variance_quantity' => round($varianceQty, 4),
                    'unit_cost'         => $unitCost,
                    'variance_cost'     => round($varianceCost, 4),
                ]);
            }
        }

        // On completion, record each counted variance once — the accountability
        // signal for a stock take (skipped on draft saves to avoid re-logging).
        if ($action === 'complete' && $this->method === 'detailed') {
            $ingIds = $lines->pluck('ingredient_id')->map('intval')->all();
            $names  = \App\Services\AuditLogService::itemLabels($ingIds);
            $uoms   = \App\Services\AuditLogService::uomLabels($lines->pluck('uom_id')->map('intval')->all());
            foreach ($lines as $line) {
                $variance = round($this->countedTotal($line) - floatval($line['system_quantity']), 4);
                if (abs($variance) < 0.0001) continue;
                $ingId = (int) $line['ingredient_id'];
                \App\Services\AuditLogService::log($record, 'line_variance', [
                    'item'     => $names['ing:' . $ingId] ?? ('#' . $ingId),
                    'variance' => $variance,
                    'unit'     => $uoms[(int) ($line['uom_id'] ?? 0)] ?? null,
                ]);
            }
        }

        if ($action === 'complete') {
            session()->flash('success', 'Stock take completed.');
            $this->redirectRoute('inventory.index', ['tab' => 'stock-takes']);
            return;
        }

        // Draft save — stay on page
        $this->recordId = $record->id;
        $this->status   = $record->status;
        session()->flash('success', 'Stock take saved as draft.');
    }

    /**
     * Reopens a completed stock take for editing. Its own ability, separate from
     * record and delete, so it can be granted independently of who can wipe a
     * completed count outright. Redirects back into itself so mount() re-runs
     * and lines re-price off live ingredient cost, exactly like any other draft.
     */
    public function reopen(): void
    {
        abort_unless(auth()->user()?->canDo('inventory.stock_takes.reopen'), 403);

        $record = StockTake::findOrFail($this->recordId);
        abort_unless($record->status === 'completed', 404);

        $record->update(['status' => 'in_progress']);

        session()->flash('success', 'Stock take reopened — make your changes and complete it again.');
        $this->redirectRoute('inventory.stock-takes.show', ['id' => $this->recordId]);
    }

    public function render()
    {
        $searchResults = collect();
        if (strlen($this->ingredientSearch) >= 2) {
            $existingIds = collect($this->lines)->pluck('ingredient_id')->map(fn ($id) => (int) $id)->toArray();
            $searchResults = Ingredient::with(['baseUom'])
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->ingredientSearch . '%')
                      ->orWhere('code', 'like', '%' . $this->ingredientSearch . '%');
                })
                ->when($existingIds, fn ($q) => $q->whereNotIn('id', $existingIds))
                ->orderBy('name')
                ->limit(8)
                ->get();
        }

        $totalVarianceCost = collect($this->lines)->sum(fn ($l) => floatval($l['variance_cost']));
        $totalStockCost    = collect($this->lines)->sum(fn ($l) => $this->countedTotal($l) * $this->lockedLineCost($l));
        $positiveVariance  = collect($this->lines)->where(fn ($l) => floatval($l['variance_quantity']) > 0)->count();
        $negativeVariance  = collect($this->lines)->where(fn ($l) => floatval($l['variance_quantity']) < 0)->count();

        $isCompleted = $this->status === 'completed';
        $pageTitle   = $this->recordId ? 'Stock Take' : 'New Stock Take';

        $availableTemplates = FormTemplate::ofType('stock_take')->active()->ordered()->get();
        $departments = \App\Models\Department::active()->ordered()->get();

        $outletOptions      = $this->outletOptions();
        $canChooseOutlet    = ! $this->recordId && $this->hasOutletChoice();
        $selectedOutletName = Outlet::where('company_id', Auth::user()->company_id)->find($this->outlet_id)?->name;

        return view('livewire.inventory.stock-take-form', compact(
            'searchResults', 'totalVarianceCost', 'totalStockCost',
            'positiveVariance', 'negativeVariance', 'isCompleted', 'availableTemplates', 'departments',
            'outletOptions', 'canChooseOutlet', 'selectedOutletName'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => $pageTitle]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function buildLine(Ingredient $ingredient): array
    {
        // Stock takes count loose (recipe) quantities — fall back to base UOM if no recipe UOM is set.
        $countUom = $ingredient->recipeUom ?: $ingredient->baseUom;
        $unitCost = $countUom
            ? app(\App\Services\UomService::class)->convertCost($ingredient, $countUom)
            : floatval($ingredient->current_cost);

        $factor = StockTakeLine::packFactor($ingredient, $countUom);

        return $this->withPackFactor($this->rememberLineCost([
            'ingredient_id'     => $ingredient->id,
            'ingredient_name'   => $ingredient->name,
            'is_prep'           => (bool) $ingredient->is_prep,
            'uom_id'            => $countUom?->id ?? $ingredient->base_uom_id,
            'uom_abbr'          => $countUom?->abbreviation ?? '',
            'pack_uom_abbr'     => $factor ? ($ingredient->baseUom->abbreviation ?? '') : '',
            'system_quantity'   => '0',
            'pack_quantity'     => '',
            'actual_quantity'   => '0',
            'counted_quantity'  => 0,
            'variance_quantity' => 0,
            'variance_cost'     => 0,
            ...$this->categoryFields($ingredient),
        ], $unitCost), $factor);
    }

    /** Record a line's pack factor server-side (none = no Purchase UOM column). */
    private function withPackFactor(array $line, ?float $factor): array
    {
        $id = (int) $line['ingredient_id'];

        if ($factor) {
            $this->packFactors[$id] = $factor;
        } else {
            unset($this->packFactors[$id]);
        }

        return $line;
    }

    private function packFactor(array $line): ?float
    {
        $factor = $this->packFactors[(int) ($line['ingredient_id'] ?? 0)] ?? null;

        return $factor && $factor > 0 ? (float) $factor : null;
    }

    /**
     * The count in the recipe UOM: packs x pack size + loose. Always worked
     * out here from the two inputs and the locked factor, never taken from a
     * total the browser sent.
     */
    private function countedTotal(array $line): float
    {
        $loose  = floatval($line['actual_quantity'] ?? 0);
        $factor = $this->packFactor($line);

        if (! $factor) {
            return $loose;
        }

        return round(floatval($line['pack_quantity'] ?? 0) * $factor + $loose, 4);
    }

    private function categoryFields(?Ingredient $ingredient): array
    {
        if (! $ingredient) {
            return [
                'category_group_id'    => null,
                'category_group_name'  => 'Uncategorized',
                'category_group_color' => '#6b7280',
                'category_sub_name'    => '',
            ];
        }

        $cat    = $ingredient->relationLoaded('ingredientCategory') ? $ingredient->ingredientCategory : null;
        $parent = $cat?->relationLoaded('parent') ? $cat->parent : null;

        $groupId    = $parent ? $parent->id : ($cat ? $cat->id : null);
        $groupName  = $parent ? $parent->name : ($cat ? $cat->name : 'Uncategorized');
        $groupColor = $parent ? $parent->color : ($cat ? $cat->color : '#6b7280');
        $subName    = $parent ? ($cat?->name ?? '') : '';

        return [
            'category_group_id'    => $groupId,
            'category_group_name'  => $groupName,
            'category_group_color' => $groupColor,
            'category_sub_name'    => $subName,
        ];
    }

    private function recalcLine(int $idx): void
    {
        if (! isset($this->lines[$idx])) return;

        $actual   = $this->countedTotal($this->lines[$idx]);
        $this->lines[$idx]['counted_quantity'] = $actual;
        $system   = floatval($this->lines[$idx]['system_quantity'] ?? 0);
        $unitCost = $this->lockedLineCost($this->lines[$idx]);

        $variance = $actual - $system;
        $this->lines[$idx]['variance_quantity'] = round($variance, 4);
        $this->lines[$idx]['variance_cost']     = round($variance * $unitCost, 4);
    }
}
