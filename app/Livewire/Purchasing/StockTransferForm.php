<?php

namespace App\Livewire\Purchasing;

use App\Models\Asset;
use App\Models\CentralPurchasingUnit;
use App\Models\FormTemplate;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\PurchaseOrder;
use App\Models\TaxRate;
use App\Models\UnitOfMeasure;
use App\Services\StockTransferService;
use App\Traits\ValidatesCompanyOutlet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class StockTransferForm extends Component
{
    use ValidatesCompanyOutlet;

    public ?int $stoId = null;

    /** Creating a transfer is gated; amending an existing one rides on order amendment. */
    private function authorizeWrite(): void
    {
        abort_unless(
            Auth::user()?->canDo($this->stoId ? 'purchasing.orders.edit' : 'purchasing.transfers.create'),
            403
        );
    }

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

    /** The "Load Template…" picker. Loads on change, then resets. */
    public string $selectedTemplateId = '';

    protected function rules(): array
    {
        return [
            /*
             * Both ends scoped to the company. The dropdowns already list only
             * this company's CPUs and outlets, but a <select> is not a control
             * — `exists:...` queries the table directly and so bypasses
             * CompanyScope, and this value is written to the transfer.
             */
            'cpu_id'                    => ['required', Rule::exists('central_purchasing_units', 'id')
                                                ->where('company_id', Auth::user()->company_id)
                                                ->whereNull('deleted_at')],
            'to_outlet_id'              => ['required', $this->outletExistsRule()],
            'transfer_date'             => 'required|date',
            'lines'                     => 'required|array|min:1',
            // One or the other, never neither — see everyLineNamesSomething().
            'lines.*.ingredient_id'     => 'nullable|exists:ingredients,id',
            'lines.*.asset_id'          => 'nullable|exists:assets,id',
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
            $po = PurchaseOrder::with('lines.ingredient.baseUom', 'lines.asset', 'lines.uom')->findOrFail($poId);
            $this->to_outlet_id = $po->delivery_outlet_id ?? $po->outlet_id;

            // An asset ordered through the CPU travels on as an asset line.
            // Before transfers could hold one it became a row with no
            // ingredient behind it, which the database refused on save.
            $this->lines = $po->lines->map(fn ($l) => [
                'ingredient_id'   => $l->asset_id ? null : $l->ingredient_id,
                'asset_id'        => $l->asset_id,
                'ingredient_name' => $l->asset?->name ?? $l->ingredient?->name ?? '—',
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
            'asset_id'        => null,
            'ingredient_name' => $ingredient->name,
            'quantity'        => '1',
            'uom_id'          => $ingredient->base_uom_id,
            'unit_cost'       => (string) floatval($ingredient->purchase_price),
        ];

        $this->ingredientSearch = '';
    }

    // ── Load from template ────────────────────────────────────────────────

    /**
     * Asset Count sheets are offered only to somebody who may open the asset
     * list at all — the same ability that gates the module, and the same gate
     * the purchase request and purchase order pickers use.
     */
    private function canRequestAssets(): bool
    {
        return (bool) Auth::user()?->canDo('assets.view');
    }

    /**
     * Forms this transfer can be loaded from: Asset Count sheets. A count
     * sheet is the list of what an outlet is meant to hold, which is exactly
     * what the central unit restocks it against. A sheet with no assets on it
     * would load as nothing, so it is not offered.
     */
    private function availableTemplates(): \Illuminate\Support\Collection
    {
        if (! $this->canRequestAssets()) {
            return collect();
        }

        return FormTemplate::ofType('asset_count')->active()->ordered()
            ->withCount(['lines as asset_lines_count' => fn ($q) => $q->where('item_type', 'asset')])
            ->get()
            ->filter(fn ($t) => $t->asset_lines_count > 0)
            ->values();
    }

    public function updatedSelectedTemplateId(): void
    {
        if ($this->selectedTemplateId) {
            $this->loadTemplate();
        }
    }

    /**
     * Copy a count sheet's assets onto this transfer, at the sheet's quantity.
     *
     * Cost comes off the asset itself and so does the unit — an asset is
     * moved in the unit it is counted in. Assets already on the transfer are
     * skipped, so loading twice is safe.
     */
    public function loadTemplate(): void
    {
        $templateId = (int) $this->selectedTemplateId;
        $this->selectedTemplateId = '';

        if (! $templateId || ! $this->canRequestAssets()) {
            return;
        }

        $template = FormTemplate::with('lines.asset')
            ->ofType('asset_count')
            ->find($templateId);

        if (! $template) {
            return;
        }

        $existing = collect($this->lines)->pluck('asset_id')->filter()->map(fn ($id) => (int) $id)->all();
        $added = 0;

        foreach ($template->lines as $tLine) {
            if ($tLine->item_type !== 'asset' || ! $tLine->asset) continue;
            if (in_array((int) $tLine->asset_id, $existing, true)) continue;

            $this->lines[] = $this->assetLine(
                $tLine->asset,
                (float) $tLine->default_quantity > 0 ? (float) $tLine->default_quantity : 1.0
            );

            $existing[] = (int) $tLine->asset_id;
            $added++;
        }

        if ($added === 0) {
            session()->flash('info', 'Every asset on that template is already on this transfer.');
        }
    }

    /** One transfer line for an asset, shaped like an ingredient line. */
    private function assetLine(Asset $asset, float $quantity): array
    {
        return [
            'ingredient_id'   => null,
            'asset_id'        => $asset->id,
            'ingredient_name' => $asset->name,
            'quantity'        => (string) round($quantity, 4),
            'uom_id'          => $asset->uom_id,
            'unit_cost'       => (string) floatval($asset->unit_cost),
        ];
    }

    /**
     * Every line must name something.
     *
     * Both id columns are nullable on their own — one holds an ingredient,
     * the other an asset — so a line naming neither would pass the rules and
     * save as a row pointing at nothing.
     */
    private function everyLineNamesSomething(): bool
    {
        $ok = true;

        foreach ($this->lines as $i => $line) {
            if (empty($line['ingredient_id']) && empty($line['asset_id'])) {
                $this->addError("lines.{$i}.ingredient_id", 'This line does not name an item.');
                $ok = false;
            }
        }

        return $ok;
    }

    public function removeLine(int $idx): void
    {
        unset($this->lines[$idx]);
        $this->lines = array_values($this->lines);
    }

    public function save(string $action = 'draft')
    {
        // Re-checked here, not just on the route: a Livewire action is its own request.
        $this->authorizeWrite();

        $this->validate();

        if (! $this->everyLineNamesSomething()) {
            return;
        }

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
        $outlets = Outlet::where('company_id', Auth::user()->company_id)
            ->selectable($this->to_outlet_id)->orderBy('name')->get();
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

        $availableTemplates = $this->availableTemplates();

        return view('livewire.purchasing.stock-transfer-form', compact(
            'cpus', 'outlets', 'taxRates', 'uoms', 'searchResults', 'subtotal', 'availableTemplates'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'New Stock Transfer Order']);
    }
}
