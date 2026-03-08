<?php

namespace App\Livewire\Inventory;

use App\Models\FormTemplate;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\StockTake;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class StockTakeForm extends Component
{
    public ?int $recordId = null;

    public string $stock_take_date  = '';
    public string $reference_number = '';
    public string $notes            = '';
    public string $status           = 'draft';

    // Lines: [ingredient_id, ingredient_name, is_prep, uom_id, uom_abbr,
    //         system_quantity, actual_quantity, variance_quantity,
    //         unit_cost, variance_cost,
    //         category_group_id, category_group_name, category_group_color, category_sub_name]
    public array  $lines            = [];
    public string $ingredientSearch = '';

    // Template picker
    public string $selectedTemplateId = '';

    protected function rules(): array
    {
        return [
            'stock_take_date'          => 'required|date',
            'reference_number'         => 'nullable|string|max:100',
            'notes'                    => 'nullable|string',
            'lines'                    => 'required|array|min:1',
            'lines.*.actual_quantity'  => 'required|numeric|min:0',
        ];
    }

    protected function messages(): array
    {
        return [
            'lines.required'                   => 'Add at least one ingredient.',
            'lines.min'                        => 'Add at least one ingredient.',
            'lines.*.actual_quantity.required' => 'Actual quantity is required.',
            'lines.*.actual_quantity.min'      => 'Quantity cannot be negative.',
        ];
    }

    public function mount(?int $id = null): void
    {
        $this->stock_take_date = now()->toDateString();

        if (! $id) return;

        $record = StockTake::with([
            'lines.ingredient.baseUom',
            'lines.ingredient.ingredientCategory.parent',
        ])->findOrFail($id);

        $this->recordId         = $record->id;
        $this->stock_take_date  = $record->stock_take_date->toDateString();
        $this->reference_number = $record->reference_number ?? '';
        $this->notes            = $record->notes ?? '';
        $this->status           = $record->status;

        $this->lines = $record->lines->map(fn ($l) => [
            'ingredient_id'        => $l->ingredient_id,
            'ingredient_name'      => $l->ingredient->name,
            'is_prep'              => (bool) ($l->ingredient->is_prep ?? false),
            'uom_id'               => $l->uom_id,
            'uom_abbr'             => $l->uom->abbreviation ?? '',
            'system_quantity'      => (string) floatval($l->system_quantity),
            'actual_quantity'      => (string) floatval($l->actual_quantity),
            'variance_quantity'    => floatval($l->variance_quantity),
            'unit_cost'            => (string) floatval($l->unit_cost),
            'variance_cost'        => floatval($l->variance_cost),
            ...$this->categoryFields($l->ingredient),
        ])->toArray();
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

        $ingredient = Ingredient::with(['baseUom', 'ingredientCategory.parent'])->findOrFail($ingredientId);
        $this->lines[] = $this->buildLine($ingredient);
        $this->ingredientSearch = '';
    }

    // ── Load all active ingredients ───────────────────────────────────────

    public function loadAll(): void
    {
        $existing = collect($this->lines)->pluck('ingredient_id')->map(fn ($id) => (int) $id)->toArray();

        $ingredients = Ingredient::with(['baseUom', 'ingredientCategory.parent'])
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
        if (count($parts) === 2 && in_array($parts[1], ['actual_quantity', 'unit_cost', 'system_quantity'])) {
            $this->recalcLine((int) $parts[0]);
        }
    }

    // ── Save (draft / complete) ───────────────────────────────────────────

    public function save(string $action = 'save'): void
    {
        $this->validate();

        $totalVarianceCost = collect($this->lines)->sum(fn ($l) => floatval($l['variance_cost']));
        $totalStockCost    = collect($this->lines)->sum(fn ($l) => floatval($l['actual_quantity']) * floatval($l['unit_cost']));
        $outletId = Outlet::where('company_id', Auth::user()->company_id)->value('id');

        $newStatus = ($action === 'complete') ? 'completed' : $this->status;

        $data = [
            'stock_take_date'     => $this->stock_take_date,
            'reference_number'    => $this->reference_number ?: null,
            'notes'               => $this->notes ?: null,
            'status'              => $newStatus,
            'total_variance_cost' => round($totalVarianceCost, 4),
            'total_stock_cost'    => round($totalStockCost, 4),
        ];

        if ($this->recordId) {
            $record = StockTake::findOrFail($this->recordId);
            $record->update($data);
        } else {
            $data['company_id'] = Auth::user()->company_id;
            $data['outlet_id']  = $outletId;
            $data['created_by'] = Auth::id();
            $record = StockTake::create($data);
        }

        // Sync lines
        $record->lines()->delete();
        foreach ($this->lines as $line) {
            $actualQty    = floatval($line['actual_quantity']);
            $systemQty    = floatval($line['system_quantity']);
            $varianceQty  = $actualQty - $systemQty;
            $unitCost     = floatval($line['unit_cost']);
            $varianceCost = $varianceQty * $unitCost;

            $record->lines()->create([
                'ingredient_id'     => $line['ingredient_id'],
                'uom_id'            => $line['uom_id'],
                'system_quantity'   => $systemQty,
                'actual_quantity'   => $actualQty,
                'variance_quantity' => round($varianceQty, 4),
                'unit_cost'         => $unitCost,
                'variance_cost'     => round($varianceCost, 4),
            ]);
        }

        $verb = $action === 'complete' ? 'completed' : ($this->recordId ? 'updated' : 'created');
        session()->flash('success', "Stock take {$verb}.");
        $this->redirectRoute('inventory.index', navigate: true);
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
        $totalStockCost    = collect($this->lines)->sum(fn ($l) => floatval($l['actual_quantity']) * floatval($l['unit_cost']));
        $positiveVariance  = collect($this->lines)->where(fn ($l) => floatval($l['variance_quantity']) > 0)->count();
        $negativeVariance  = collect($this->lines)->where(fn ($l) => floatval($l['variance_quantity']) < 0)->count();

        $isCompleted = $this->status === 'completed';
        $pageTitle   = $this->recordId ? 'Stock Take' : 'New Stock Take';

        $availableTemplates = FormTemplate::ofType('stock_take')->active()->ordered()->get();

        return view('livewire.inventory.stock-take-form', compact(
            'searchResults', 'totalVarianceCost', 'totalStockCost',
            'positiveVariance', 'negativeVariance', 'isCompleted', 'availableTemplates'
        ))->layout('layouts.app', ['title' => $pageTitle]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function buildLine(Ingredient $ingredient): array
    {
        $unitCost = $ingredient->is_prep
            ? floatval($ingredient->current_cost)
            : floatval($ingredient->purchase_price);

        return [
            'ingredient_id'     => $ingredient->id,
            'ingredient_name'   => $ingredient->name,
            'is_prep'           => (bool) $ingredient->is_prep,
            'uom_id'            => $ingredient->base_uom_id,
            'uom_abbr'          => $ingredient->baseUom->abbreviation ?? '',
            'system_quantity'   => '0',
            'actual_quantity'   => '0',
            'variance_quantity' => 0,
            'unit_cost'         => (string) $unitCost,
            'variance_cost'     => 0,
            ...$this->categoryFields($ingredient),
        ];
    }

    private function categoryFields(Ingredient $ingredient): array
    {
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

        $actual   = floatval($this->lines[$idx]['actual_quantity'] ?? 0);
        $system   = floatval($this->lines[$idx]['system_quantity'] ?? 0);
        $unitCost = floatval($this->lines[$idx]['unit_cost'] ?? 0);

        $variance = $actual - $system;
        $this->lines[$idx]['variance_quantity'] = round($variance, 4);
        $this->lines[$idx]['variance_cost']     = round($variance * $unitCost, 4);
    }
}
