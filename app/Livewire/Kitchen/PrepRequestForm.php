<?php

namespace App\Livewire\Kitchen;

use App\Models\CentralKitchen;
use App\Models\Outlet;
use App\Models\OutletPrepRequest;
use App\Models\Recipe;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class PrepRequestForm extends Component
{
    public ?int $requestId = null;

    public string $requestNumber = '';
    public string $status = 'draft';

    public ?int   $kitchen_id  = null;
    public string $needed_date = '';
    public string $notes       = '';

    // Lines: [recipe_id, recipe_name, requested_quantity, uom_id, uom_name, ingredient_id]
    public array  $lines        = [];
    public string $recipeSearch = '';

    protected function rules(): array
    {
        return [
            'kitchen_id'                  => 'required|exists:central_kitchens,id',
            'needed_date'                 => 'required|date',
            'notes'                       => 'nullable|string',
            'lines'                       => 'required|array|min:1',
            'lines.*.recipe_id'           => 'required|exists:recipes,id',
            'lines.*.requested_quantity'  => 'required|numeric|min:0.0001',
            'lines.*.uom_id'             => 'required|exists:units_of_measure,id',
        ];
    }

    protected function messages(): array
    {
        return [
            'kitchen_id.required'                 => 'Please select a kitchen.',
            'lines.required'                      => 'Add at least one recipe line.',
            'lines.min'                           => 'Add at least one recipe line.',
            'lines.*.requested_quantity.min'      => 'Quantity must be greater than zero.',
        ];
    }

    public function mount(?int $id = null): void
    {
        $this->needed_date = now()->addDay()->toDateString();

        if (! $id) {
            $this->requestNumber = OutletPrepRequest::generateNumber();
            return;
        }

        $request = OutletPrepRequest::with(['lines.recipe.yieldUom', 'lines.uom', 'lines.ingredient'])->findOrFail($id);

        $this->requestId     = $request->id;
        $this->requestNumber = $request->request_number;
        $this->status        = $request->status;
        $this->kitchen_id    = $request->kitchen_id;
        $this->needed_date   = $request->needed_date->toDateString();
        $this->notes         = $request->notes ?? '';

        $this->lines = $request->lines->map(fn ($l) => [
            'recipe_id'          => $l->recipe_id,
            'recipe_name'        => $l->recipe?->name ?? '-',
            'requested_quantity' => (string) floatval($l->requested_quantity),
            'uom_id'             => $l->uom_id,
            'uom_name'           => $l->uom?->abbreviation ?? '-',
            'ingredient_id'      => $l->ingredient_id,
        ])->toArray();
    }

    public function addRecipe(int $recipeId): void
    {
        $recipe = Recipe::with(['yieldUom', 'ingredient'])->find($recipeId);
        if (! $recipe) return;

        // Skip duplicates
        foreach ($this->lines as $line) {
            if ((int) $line['recipe_id'] === $recipeId) {
                $this->recipeSearch = '';
                return;
            }
        }

        $this->lines[] = [
            'recipe_id'          => $recipeId,
            'recipe_name'        => $recipe->name,
            'requested_quantity' => '1',
            'uom_id'             => $recipe->yield_uom_id,
            'uom_name'           => $recipe->yieldUom?->abbreviation ?? '-',
            'ingredient_id'      => $recipe->ingredient?->id,
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
            $status = $action === 'submit' ? 'submitted' : 'draft';

            $data = [
                'kitchen_id'  => $this->kitchen_id,
                'needed_date' => $this->needed_date,
                'notes'       => $this->notes ?: null,
                'status'      => $status,
            ];

            if ($this->requestId) {
                $request = OutletPrepRequest::findOrFail($this->requestId);
                $request->update($data);
            } else {
                $outletId = $user->activeOutletId() ?? Outlet::where('company_id', $user->company_id)->value('id');
                $data['company_id']     = $user->company_id;
                $data['outlet_id']      = $outletId;
                $data['request_number'] = $this->requestNumber;
                $data['created_by']     = Auth::id();
                $request = OutletPrepRequest::create($data);
            }

            // Sync lines
            $request->lines()->delete();
            foreach ($this->lines as $line) {
                $request->lines()->create([
                    'recipe_id'          => $line['recipe_id'],
                    'ingredient_id'      => $line['ingredient_id'] ?: null,
                    'requested_quantity'  => floatval($line['requested_quantity']),
                    'uom_id'             => $line['uom_id'],
                ]);
            }
        });

        $msg = $action === 'submit' ? 'Prep request submitted.' : 'Prep request saved as draft.';
        session()->flash('success', $msg);
        $this->redirectRoute('kitchen.index', ['tab' => 'requests']);
    }

    public function render()
    {
        $kitchens = CentralKitchen::active()->orderBy('name')->get();
        $uoms     = UnitOfMeasure::orderBy('name')->get();

        $searchResults = collect();
        if (strlen($this->recipeSearch) >= 2) {
            $searchResults = Recipe::with(['yieldUom', 'ingredient'])
                ->where('is_prep', true)
                ->where('is_active', true)
                ->where(fn ($q) => $q->where('name', 'like', '%' . $this->recipeSearch . '%')
                    ->orWhere('code', 'like', '%' . $this->recipeSearch . '%'))
                ->orderBy('name')
                ->limit(8)
                ->get();
        }

        $isEditable = ! $this->requestId || in_array($this->status, ['draft']);

        $pageTitle = $this->requestId
            ? ($isEditable ? 'Edit: ' : 'View: ') . $this->requestNumber
            : 'New Prep Request';

        return view('livewire.kitchen.prep-request-form', compact(
            'kitchens', 'uoms', 'searchResults', 'isEditable'
        ))->layout('layouts.app', ['title' => $pageTitle]);
    }
}
