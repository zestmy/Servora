<?php

namespace App\Livewire\Kitchen;

use App\Models\CentralKitchen;
use App\Models\Outlet;
use App\Models\ProductionOrder;
use App\Models\ProductionRecipe;
use App\Models\Recipe;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ProductionOrderForm extends Component
{
    public ?int $orderId = null;

    public string $orderNumber = '';
    public string $status = 'draft';

    public ?int   $kitchen_id      = null;
    public string $production_date = '';
    public string $needed_by_date  = '';
    public string $notes           = '';

    /**
     * Lines carry a source: 'production' (a Central Kitchen production recipe)
     * or 'prep' (an outlet prep item). Both are producible in the kitchen and
     * both stock the kitchen on completion, via different tables.
     * [source, recipe_id, production_recipe_id, recipe_name, planned_quantity,
     *  uom_id, uom_name, unit_cost, to_outlet_id]
     */
    public array  $lines        = [];
    public string $recipeSearch = '';

    /**
     * Kitchens of the active company. A bare `exists:central_kitchens,id` rule
     * queries the table directly and so bypasses CompanyScope — a crafted
     * payload could name another tenant's kitchen.
     */
    protected function selectableKitchenIds(): array
    {
        return CentralKitchen::where('company_id', Auth::user()->company_id)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    protected function rules(): array
    {
        return [
            'kitchen_id'               => ['required', 'integer', Rule::in($this->selectableKitchenIds())],
            'production_date'          => 'required|date',
            'needed_by_date'           => 'nullable|date|after_or_equal:production_date',
            'notes'                    => 'nullable|string',
            'lines'                    => 'required|array|min:1',
            'lines.*.source'           => 'required|in:production,prep',
            'lines.*.recipe_id'            => 'nullable|exists:recipes,id',
            'lines.*.production_recipe_id' => 'nullable|exists:production_recipes,id',
            'lines.*.planned_quantity' => 'required|numeric|min:0.0001',
            'lines.*.uom_id'          => 'required|exists:units_of_measure,id',
            // Destination outlets are limited to ones this user can reach.
            'lines.*.to_outlet_id'    => ['nullable', 'integer', Rule::in(Auth::user()->accessibleOutletIds())],
        ];
    }

    protected function messages(): array
    {
        return [
            'kitchen_id.required'              => 'Please select a kitchen.',
            'kitchen_id.in'                    => 'You do not have access to the selected kitchen.',
            'lines.*.to_outlet_id.in'          => 'You do not have access to the selected destination outlet.',
            'lines.required'                   => 'Add at least one recipe line.',
            'lines.min'                        => 'Add at least one recipe line.',
            'lines.*.planned_quantity.min'     => 'Quantity must be greater than zero.',
        ];
    }

    public function mount(?int $id = null): void
    {
        $this->production_date = now()->toDateString();

        if (! $id) {
            $this->orderNumber = ProductionOrder::generateNumber();

            // "Produce" from the Production Recipes list lands here with the
            // recipe preselected, so the order starts one click from done.
            if ($recipeId = (int) request('recipe')) {
                $recipe = ProductionRecipe::find($recipeId);
                if ($recipe) {
                    $this->kitchen_id = $recipe->kitchen_id;
                    $this->addProductionRecipe($recipeId);
                }
            }
            return;
        }

        $order = ProductionOrder::with([
            'lines.recipe.yieldUom', 'lines.productionRecipe.yieldUom', 'lines.uom', 'lines.toOutlet',
        ])->findOrFail($id);

        $this->orderId         = $order->id;
        $this->orderNumber     = $order->order_number;
        $this->status          = $order->status;
        $this->kitchen_id      = $order->kitchen_id;
        $this->production_date = $order->production_date->toDateString();
        $this->needed_by_date  = $order->needed_by_date?->toDateString() ?? '';
        $this->notes           = $order->notes ?? '';

        $this->lines = $order->lines->map(fn ($l) => [
            'source'               => $l->production_recipe_id ? 'production' : 'prep',
            'recipe_id'            => $l->recipe_id,
            'production_recipe_id' => $l->production_recipe_id,
            'recipe_name'          => $l->productionRecipe?->name ?? $l->recipe?->name ?? '-',
            'planned_quantity'     => (string) floatval($l->planned_quantity),
            'uom_id'               => $l->uom_id,
            'uom_name'             => $l->uom?->abbreviation ?? '-',
            'unit_cost'            => (string) floatval($l->unit_cost),
            'to_outlet_id'         => $l->to_outlet_id,
        ])->toArray();
    }

    /** Add a Central Kitchen production recipe. */
    public function addProductionRecipe(int $id): void
    {
        $recipe = ProductionRecipe::with('yieldUom')->find($id);
        if (! $recipe) return;

        foreach ($this->lines as $line) {
            if (($line['source'] ?? '') === 'production' && (int) $line['production_recipe_id'] === $id) {
                $this->recipeSearch = '';
                return;
            }
        }

        $this->lines[] = [
            'source'               => 'production',
            'recipe_id'            => null,
            'production_recipe_id' => $id,
            'recipe_name'          => $recipe->name,
            // Default to one batch — a production recipe is defined per batch
            // yield, so 1 x yield_quantity is the meaningful starting point.
            'planned_quantity'     => (string) floatval($recipe->yield_quantity ?: 1),
            'uom_id'               => $recipe->yield_uom_id,
            'uom_name'             => $recipe->yieldUom?->abbreviation ?? '-',
            'unit_cost'            => (string) floatval($recipe->total_cost_per_unit),
            'to_outlet_id'         => null,
        ];

        $this->recipeSearch = '';
    }

    /** Add an outlet prep item. */
    public function addRecipe(int $recipeId): void
    {
        $recipe = Recipe::with('yieldUom')->find($recipeId);
        if (! $recipe) return;

        // Skip duplicates
        foreach ($this->lines as $line) {
            if (($line['source'] ?? 'prep') === 'prep' && (int) $line['recipe_id'] === $recipeId) {
                $this->recipeSearch = '';
                return;
            }
        }

        $this->lines[] = [
            'source'               => 'prep',
            'recipe_id'            => $recipeId,
            'production_recipe_id' => null,
            'recipe_name'          => $recipe->name,
            'planned_quantity'     => '1',
            'uom_id'               => $recipe->yield_uom_id,
            'uom_name'             => $recipe->yieldUom?->abbreviation ?? '-',
            'unit_cost'            => (string) floatval($recipe->cost_per_yield_unit),
            'to_outlet_id'         => null,
        ];

        $this->recipeSearch = '';
    }

    public function removeLine(int $idx): void
    {
        unset($this->lines[$idx]);
        $this->lines = array_values($this->lines);
    }

    public function save(string $action = 'draft'): void
    {
        $this->validate();

        $user = Auth::user();

        DB::transaction(function () use ($user, $action) {
            $status = $action === 'schedule' ? 'scheduled' : 'draft';

            $data = [
                'kitchen_id'      => $this->kitchen_id,
                'production_date' => $this->production_date,
                'needed_by_date'  => $this->needed_by_date ?: null,
                'notes'           => $this->notes ?: null,
                'status'          => $status,
            ];

            if ($this->orderId) {
                $order = ProductionOrder::findOrFail($this->orderId);
                $order->update($data);
            } else {
                $data['company_id']   = $user->company_id;
                $data['order_number'] = $this->orderNumber;
                $data['created_by']   = Auth::id();
                $order = ProductionOrder::create($data);
            }

            // Sync lines
            $order->lines()->delete();
            foreach ($this->lines as $line) {
                $order->lines()->create([
                    'recipe_id'            => ($line['source'] ?? 'prep') === 'prep' ? $line['recipe_id'] : null,
                    'production_recipe_id' => ($line['source'] ?? 'prep') === 'production' ? $line['production_recipe_id'] : null,
                    'planned_quantity' => floatval($line['planned_quantity']),
                    'uom_id'           => $line['uom_id'],
                    'unit_cost'        => floatval($line['unit_cost']),
                    'to_outlet_id'     => $line['to_outlet_id'] ?: null,
                    'status'           => 'pending',
                ]);
            }
        });

        $msg = $action === 'schedule' ? 'Production order scheduled.' : 'Production order saved as draft.';
        session()->flash('success', $msg);
        $this->redirectRoute('kitchen.index');
    }

    public function render()
    {
        $kitchens = CentralKitchen::active()->orderBy('name')->get();
        $outlets  = Auth::user()->accessibleOutlets()
            ->where('is_active', true)->orderBy('name')->get();
        $uoms     = UnitOfMeasure::orderBy('name')->get();

        // Both sources are searchable. Production recipes lead — they're the
        // kitchen's own products; prep items are the outlet-side recipes the
        // kitchen also produces.
        $productionResults = collect();
        $searchResults     = collect();
        if (strlen($this->recipeSearch) >= 2) {
            $term = '%' . $this->recipeSearch . '%';

            $productionResults = ProductionRecipe::with('yieldUom')
                ->where('is_active', true)
                ->when($this->kitchen_id, fn ($q) => $q->where('kitchen_id', $this->kitchen_id))
                ->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('code', 'like', $term))
                ->orderBy('name')
                ->limit(8)
                ->get();

            $searchResults = Recipe::with('yieldUom')
                ->where('is_prep', true)
                ->where('is_active', true)
                ->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('code', 'like', $term))
                ->orderBy('name')
                ->limit(8)
                ->get();
        }

        $isEditable = ! $this->orderId || in_array($this->status, ['draft']);

        $pageTitle = $this->orderId
            ? ($isEditable ? 'Edit: ' : 'View: ') . $this->orderNumber
            : 'New Production Order';

        return view('livewire.kitchen.production-order-form', compact(
            'kitchens', 'outlets', 'uoms', 'searchResults', 'productionResults', 'isEditable'
        ))->layout('layouts.kitchen', ['title' => $pageTitle]);
    }
}
