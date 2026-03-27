<?php

namespace App\Livewire\Purchasing;

use App\Models\CentralPurchasingUnit;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\PurchaseOrder;
use App\Models\TaxRate;
use App\Models\UnitOfMeasure;
use App\Services\StockTransferService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class StockTransferForm extends Component
{
    public ?int $stoId = null;

    public ?int   $cpu_id           = null;
    public ?int   $to_outlet_id     = null;
    public ?int   $purchase_order_id = null;
    public string $transfer_date    = '';
    public bool   $is_chargeable    = false;
    public ?int   $tax_rate_id      = null;
    public string $delivery_charges = '0';
    public string $notes            = '';

    public array  $lines            = [];
    public string $ingredientSearch = '';

    protected function rules(): array
    {
        return [
            'cpu_id'                    => 'required|exists:central_purchasing_units,id',
            'to_outlet_id'              => 'required|exists:outlets,id',
            'transfer_date'             => 'required|date',
            'lines'                     => 'required|array|min:1',
            'lines.*.ingredient_id'     => 'required|exists:ingredients,id',
            'lines.*.quantity'          => 'required|numeric|min:0.0001',
            'lines.*.uom_id'           => 'required|exists:units_of_measure,id',
            'lines.*.unit_cost'         => 'nullable|numeric|min:0',
        ];
    }

    public function mount(?int $poId = null): void
    {
        $this->transfer_date = now()->toDateString();

        $cpu = Auth::user()->company?->cpus()->where('is_active', true)->first();
        $this->cpu_id = $cpu?->id;

        if ($poId) {
            $this->purchase_order_id = $poId;
            $po = PurchaseOrder::with('lines.ingredient.baseUom', 'lines.uom')->findOrFail($poId);
            $this->to_outlet_id = $po->delivery_outlet_id ?? $po->outlet_id;

            $this->lines = $po->lines->map(fn ($l) => [
                'ingredient_id'   => $l->ingredient_id,
                'ingredient_name' => $l->ingredient?->name ?? '—',
                'quantity'        => (string) floatval($l->quantity),
                'uom_id'          => $l->uom_id,
                'unit_cost'       => (string) floatval($l->unit_cost),
            ])->toArray();
        }
    }

    public function addIngredient(int $ingredientId): void
    {
        foreach ($this->lines as $line) {
            if ((int) $line['ingredient_id'] === $ingredientId) {
                $this->ingredientSearch = '';
                return;
            }
        }

        $ingredient = Ingredient::with('baseUom')->find($ingredientId);
        if (! $ingredient) return;

        $this->lines[] = [
            'ingredient_id'   => $ingredient->id,
            'ingredient_name' => $ingredient->name,
            'quantity'        => '1',
            'uom_id'          => $ingredient->base_uom_id,
            'unit_cost'       => (string) floatval($ingredient->purchase_price),
        ];

        $this->ingredientSearch = '';
    }

    public function removeLine(int $idx): void
    {
        unset($this->lines[$idx]);
        $this->lines = array_values($this->lines);
    }

    public function save(string $action = 'draft')
    {
        $this->validate();

        $data = [
            'company_id'        => Auth::user()->company_id,
            'cpu_id'            => $this->cpu_id,
            'to_outlet_id'      => $this->to_outlet_id,
            'purchase_order_id' => $this->purchase_order_id,
            'transfer_date'     => $this->transfer_date,
            'is_chargeable'     => $this->is_chargeable,
            'tax_rate_id'       => $this->is_chargeable ? $this->tax_rate_id : null,
            'delivery_charges'  => $this->is_chargeable ? floatval($this->delivery_charges) : 0,
            'status'            => $action === 'send' ? 'sent' : 'draft',
            'notes'             => $this->notes ?: null,
        ];

        $sto = StockTransferService::create($data, $this->lines);

        $msg = $action === 'send'
            ? "STO {$sto->sto_number} sent to outlet." . ($this->is_chargeable ? ' Invoice auto-generated.' : '')
            : "STO {$sto->sto_number} saved as draft.";

        session()->flash('success', $msg);

        return $this->redirect(route('purchasing.index', ['tab' => 'sto']), navigate: true);
    }

    public function render()
    {
        $cpus = CentralPurchasingUnit::where('is_active', true)->get();
        $outlets = Outlet::where('company_id', Auth::user()->company_id)->where('is_active', true)->orderBy('name')->get();
        $taxRates = TaxRate::active()->orderBy('name')->get();
        $uoms = UnitOfMeasure::orderBy('name')->get();

        $searchResults = collect();
        if (strlen($this->ingredientSearch) >= 2) {
            $searchResults = Ingredient::with('baseUom')
                ->where('is_active', true)
                ->where(fn ($q) => $q->where('name', 'like', '%' . $this->ingredientSearch . '%')
                    ->orWhere('code', 'like', '%' . $this->ingredientSearch . '%'))
                ->limit(10)->get();
        }

        $subtotal = collect($this->lines)->sum(fn ($l) => floatval($l['quantity'] ?? 0) * floatval($l['unit_cost'] ?? 0));

        return view('livewire.purchasing.stock-transfer-form', compact(
            'cpus', 'outlets', 'taxRates', 'uoms', 'searchResults', 'subtotal'
        ))->layout('layouts.app', ['title' => 'New Stock Transfer Order']);
    }
}
