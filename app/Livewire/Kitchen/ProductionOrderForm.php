<?php

namespace App\Livewire\Kitchen;

use App\Models\CentralKitchen;
use App\Models\Outlet;
use App\Models\ProductionOrder;
use App\Models\Recipe;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    // Lines: [recipe_id, recipe_name, planned_quantity, uom_id, uom_name, unit_cost, to_outlet_id]
    public array  $lines        = [];
    public string $recipeSearch = '';

    protected function rules(): array
    {
        return [
            'kitchen_id'               => 'required|exists:central_kitchens,id',
            'production_date'          => 'required|date',
            'needed_by_date'           => 'nullable|date|after_or_equal:production_date',
            'notes'                    => 'nullable|string',
            'lines'                    => 'required|array|min:1',
            'lines.*.recipe_id'        => 'required|exists:recipes,id',
            'lines.*.planned_quantity' => 'required|numeric|min:0.0001',
            'lines.*.uom_id'          => 'required|exists:units_of_measure,id',
            'lines.*.to_outlet_id'    => 'nullable|exists:outlets,id',
        ];
    }

    protected function messages(): array
    {
        return [
            'kitchen_id.required'              => 'Please select a kitchen.',
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
            return;
        }

        $order = ProductionOrder::with(['lines.recipe.yieldUom', 'lines.uom', 'lines.toOutlet'])->findOrFail($id);

        $this->orderId         = $order->id;
        $this->orderNumber     = $order->order_number;
        $this->status          = $order->status;
        $this->kitchen_id      = $order->kitchen_id;
        $this->production_date = $order->production_date->toDateString();
        $this->needed_by_date  = $order->needed_by_date?->toDateString() ?? '';
        $this->notes           = $order->notes ?? '';

        $this->lines = $order->lines->map(fn ($l) => [
            'recipe_id'        => $l->recipe_id,
            'recipe_name'      => $l->recipe?->name ?? '-',
            'planned_quantity' => (string) floatval($l->planned_quantity),
            'uom_id'           => $l->uom_id,
            'uom_name'         => $l->uom?->abbreviation ?? '-',
            'unit_cost'        => (string) floatval($l->unit_cost),
            'to_outlet_id'     => $l->to_outlet_id,
        ])->toArray();
    }

    public function addRecipe(int $recipeId): void
    {
        $recipe = Recipe::with('yieldUom')->find($recipeId);
        if (! $recipe) return;

        // Skip duplicates
        foreach ($this->lines as $line) {
            if ((int) $line['recipe_id'] === $recipeId) {
                $this->recipeSearch = '';
                return;
            }
        }

        $this->lines[] = [
            'recipe_id'        => $recipeId,
            'recipe_name'      => $recipe->name,
            'planned_quantity' => '1',
            'uom_id'           => $recipe->yield_uom_id,
            'uom_name'         => $recipe->yieldUom?->abbreviation ?? '-',
            'unit_cost'        => (string) floatval($recipe->cost_per_yield_unit),
            'to_outlet_id'     => null,
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
                    'recipe_id'        => $line['recipe_id'],
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
        $outlets  = Outlet::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)->orderBy('name')->get();
        $uoms     = UnitOfMeasure::orderBy('name')->get();

        $searchResults = collect();
        if (strlen($this->recipeSearch) >= 2) {
            $searchResults = Recipe::with('yieldUom')
                ->where('is_prep', true)
                ->where('is_active', true)
                ->where(fn ($q) => $q->where('name', 'like', '%' . $this->recipeSearch . '%')
                    ->orWhere('code', 'like', '%' . $this->recipeSearch . '%'))
                ->orderBy('name')
                ->limit(8)
                ->get();
        }

        $isEditable = ! $this->orderId || in_array($this->status, ['draft']);

        $pageTitle = $this->orderId
            ? ($isEditable ? 'Edit: ' : 'View: ') . $this->orderNumber
            : 'New Production Order';

        return view('livewire.kitchen.production-order-form', compact(
            'kitchens', 'outlets', 'uoms', 'searchResults', 'isEditable'
        ))->layout('layouts.app', ['title' => $pageTitle]);
    }
}
