<?php

namespace App\Livewire\Purchasing;

use App\Models\Department;
use App\Models\FormTemplate;
use App\Models\Ingredient;
use App\Models\IngredientParLevel;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\UnitOfMeasure;
use App\Services\OrderAdjustmentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class OrderForm extends Component
{
    use \App\Traits\RequiresActiveOutlet;

    public ?int $orderId = null;

    /**
     * The outlet an EXISTING order belongs to.
     *
     * Par levels are per outlet, so suggesting quantities from whoever opened
     * the form rather than from the branch the order is for gives the wrong
     * numbers to anyone who works across more than one.
     */
    public ?int $orderOutletId = null;

    /**
     * The request this order is being raised from, when it was started from one.
     *
     * Kept so the order can record the link (`purchase_orders.purchase_request_id`
     * has always existed and this form never filled it) and so the order lands on
     * the branch that ASKED, not on whichever outlet the person converting happens
     * to be standing in.
     */
    public ?int $sourcePrId     = null;
    public ?int $sourcePrOutlet = null;

    /** Raising a new order and amending an existing one are separate abilities. */
    private function authorizeWrite(): void
    {
        abort_unless(
            Auth::user()?->canDo($this->orderId ? 'purchasing.orders.edit' : 'purchasing.orders.create'),
            403
        );
    }

    // Read-only header info
    public string $poNumber = '';
    public string $status   = 'draft';

    // Editable header
    public ?int   $supplier_id              = null;
    public string $order_date               = '';
    public string $expected_delivery_date   = '';
    public string $notes                    = '';
    public string $receiver_name            = '';
    public ?int   $department_id            = null;

    // Lines: [ingredient_id, ingredient_name, quantity, uom_id, unit_cost, total_cost]
    public array  $lines              = [];
    public string $ingredientSearch   = '';
    public string $selectedTemplateId = '';

    protected function rules(): array
    {
        return [
            'supplier_id'            => 'nullable|exists:suppliers,id',
            'order_date'             => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'notes'                  => 'nullable|string',
            'receiver_name'          => 'nullable|string|max:100',
            'department_id'          => 'nullable|exists:departments,id',
            'lines'                  => 'required|array|min:1',
            'lines.*.ingredient_id'  => 'nullable|exists:ingredients,id',
            'lines.*.asset_id'       => 'nullable|exists:assets,id',
            'lines.*.quantity'       => 'required|numeric|min:0.001',
            'lines.*.uom_id'         => 'required|exists:units_of_measure,id',
            'lines.*.unit_cost'      => 'required|numeric|min:0',
        ];
    }

    /**
     * Every line must name something.
     *
     * Both id columns are nullable on their own — one holds an ingredient,
     * the other an asset — so a line naming neither would pass the rules and
     * save as a row pointing at nothing. Checked here rather than through
     * withValidator(), which is a FormRequest hook that Livewire never calls.
     *
     * @return bool  true when every line names an item.
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

    protected function messages(): array
    {
        return [
            'supplier_id.required'          => 'Please select a supplier.',
            'lines.required'                => 'Add at least one ingredient.',
            'lines.min'                     => 'Add at least one ingredient.',
            'lines.*.quantity.min'          => 'Quantity must be greater than zero.',
            'lines.*.unit_cost.required'    => 'Unit cost is required.',
        ];
    }

    /**
     * Name what did not come across, and why.
     *
     * A purchase order goes to a supplier against an ingredient, so a request
     * line carrying an asset or a hand-typed name has nothing to become here.
     * Dropping those quietly is what made an asset-only request look like a
     * broken conversion: the form opened with no lines and no explanation.
     */
    /**
     * One order line for an asset.
     *
     * Shaped exactly like an ingredient line so the blade, the totals and the
     * save path do not each need to know which kind they are holding — the
     * ingredient-only fields are simply empty. Cost comes off the asset
     * itself: there is no supplier price list for a stand mixer.
     */
    private function assetLine(?\App\Models\Asset $asset, float $quantity, ?int $uomId): array
    {
        return [
            'ingredient_id'          => null,
            'asset_id'               => $asset?->id,
            'ingredient_name'        => $asset?->name ?? '—',
            'quantity'               => (string) $quantity,
            'uom_id'                 => $uomId ?: $asset?->uom_id,
            'unit_cost'              => (string) floatval($asset?->unit_cost ?? 0),
            'total_cost'             => 0,
            'tax_rate_id'            => null,
            'tax_label'              => null,
            'tax_rate_pct'           => 0,
            'tax_amount'             => 0,
            'pack_size'              => 1,
            'pack_info'              => '',
            'par_level'              => '0',
            'balance'                => '',
            'supplier_sku'           => null,
            'supplier_product_name'  => null,
            'supplier_id_override'   => null,
        ];
    }

    /**
     * A key that tells two lines apart whatever they point at.
     *
     * Keying on ingredient_id alone collapsed every asset line onto the same
     * null bucket, which would have made an order with two assets adjust and
     * audit as though it had one.
     */
    private static function lineKey(array|\App\Models\PurchaseOrderLine $line): string
    {
        $ingredient = is_array($line) ? ($line['ingredient_id'] ?? null) : $line->ingredient_id;
        $asset      = is_array($line) ? ($line['asset_id'] ?? null)      : $line->asset_id;

        return $asset ? 'asset:' . $asset : 'ingredient:' . (int) $ingredient;
    }

    private function announceSkippedLines(\App\Models\PurchaseRequest $pr): void
    {
        // Assets now become order lines of their own, so the only thing that
        // can still be left behind is a hand-typed name with nothing behind it.
        $custom = $pr->lines->filter(fn ($l) => $l->asset_id === null && ! $l->ingredient_id)->count();

        if (! $custom) {
            return;
        }

        $left = sprintf('%d hand-typed item%s', $custom, $custom === 1 ? '' : 's');

        session()->flash(
            count($this->lines) ? 'warning' : 'error',
            count($this->lines)
                ? "{$left} on {$pr->pr_number} could not be brought over — a hand-typed name has no ingredient or asset behind it to order. Add it to this order by hand."
                : "{$pr->pr_number} has {$left} and nothing else. A hand-typed name has no ingredient or asset behind it, so there is nothing to put on a purchase order."
        );
    }

    public function mount(?int $id = null): void
    {
        $this->order_date = now()->toDateString();

        if (! $id) {
            $this->poNumber = $this->generatePoNumber();

            // Pre-populate from Purchase Request if pr_id query param provided
            $prId = request()->query('pr_id');
            if ($prId) {
                $pr = \App\Models\PurchaseRequest::with([
                    'lines.ingredient.baseUom', 'lines.uom', 'lines.asset', 'lines.preferredSupplier',
                ])->find($prId);
                if ($pr) {
                    /*
                     * The same outlet check every other document in this module
                     * makes. CompanyScope stops a cross-company read on its own,
                     * but without it a request raised for a branch this user
                     * cannot open would still prefill an order here.
                     */
                    if ($pr->outlet_id && ! Auth::user()->canAccessOutlet($pr->outlet_id)) {
                        abort(403, 'You do not have access to this outlet.');
                    }

                    $this->sourcePrId     = $pr->id;
                    $this->sourcePrOutlet = $pr->outlet_id;

                    /*
                     * Carry the request's own details across.
                     *
                     * Only the notes used to come over, so every conversion began
                     * by retyping the department, the date the branch needs it by,
                     * and the supplier the request had already named on its lines
                     * — from a screen that no longer shows any of them. A
                     * needed-by date IS the delivery date being asked for.
                     */
                    $this->department_id          = $pr->department_id;
                    $this->expected_delivery_date = $pr->needed_by_date?->toDateString() ?? '';
                    $this->notes = "From PR {$pr->pr_number}" . ($pr->notes ? " — {$pr->notes}" : '');

                    // Only when the request agrees on one. A PR spanning two
                    // suppliers gets split later, and guessing here picks a side.
                    $named = $pr->lines->pluck('preferred_supplier_id')->filter()->unique();
                    if ($named->count() === 1) {
                        $this->supplier_id = (int) $named->first();
                    }
                    foreach ($pr->lines as $line) {
                        /*
                         * An asset line becomes an order line of its own.
                         *
                         * Its cost comes from the asset's own unit_cost rather
                         * than a supplier price list, because an asset has no
                         * supplier_ingredients row to look one up in — and its
                         * UOM is whatever the request asked in, which for an
                         * asset is the unit it is counted in.
                         */
                        if ($line->asset_id) {
                            $this->lines[] = $this->assetLine($line->asset, (float) $line->quantity, $line->uom_id);
                            continue;
                        }

                        if (! $line->ingredient_id) continue; // skip hand-typed items with nothing behind them
                        $taxRate = $line->ingredient?->effectiveTaxRate(Auth::user()->company);
                        $this->lines[] = [
                            'ingredient_id'          => $line->ingredient_id,
                            'ingredient_name'        => $line->ingredient?->name ?? $line->custom_name ?? '—',
                            'quantity'               => (string) floatval($line->quantity),
                            'uom_id'                 => $line->uom_id,
                            'unit_cost'              => (string) floatval($line->ingredient?->purchase_price ?? 0),
                            'total_cost'             => 0,
                            'tax_rate_id'            => $taxRate?->id,
                            'tax_label'              => $taxRate ? ($taxRate->name . ' ' . rtrim(rtrim(number_format($taxRate->rate, 2), '0'), '.') . '%') : null,
                            'tax_rate_pct'           => floatval($taxRate?->rate ?? 0),
                            'tax_amount'             => 0,
                            'pack_size'              => 1,
                            'pack_info'              => '',
                            'par_level'              => '0',
                            'balance'                => '',
                            'supplier_sku'           => null,
                            'supplier_product_name'  => null,
                            'supplier_id_override'   => null,
                        ];
                    }

                    /*
                     * A line that cannot become an order line is REPORTED, not
                     * dropped in silence. An asset-only request converted to a
                     * completely empty order with nothing said — which reads as
                     * the conversion being broken, rather than as the request
                     * holding nothing a supplier PO can carry.
                     */
                    $this->announceSkippedLines($pr);
                }
            }

            return;
        }

        $po = PurchaseOrder::with(['lines.ingredient.baseUom', 'lines.asset', 'lines.uom'])->findOrFail($id);

        if ($po->outlet_id && ! Auth::user()->canAccessOutlet($po->outlet_id)) {
            abort(403, 'You do not have access to this outlet.');
        }

        $this->orderId                = $po->id;
        $this->orderOutletId          = $po->outlet_id;
        $this->poNumber               = $po->po_number;
        $this->status                 = $po->status;
        $this->supplier_id            = $po->supplier_id;
        $this->order_date             = $po->order_date->toDateString();
        $this->expected_delivery_date = $po->expected_delivery_date?->toDateString() ?? '';
        $this->notes                  = $po->notes ?? '';
        $this->receiver_name          = $po->receiver_name ?? '';
        $this->department_id          = $po->department_id;

        $this->lines = $po->lines->map(function ($l) use ($po) {
            /*
             * An asset line reopens as it was saved.
             *
             * lookupSupplierInfo() reads supplier_ingredients, which an asset
             * has no row in — asking it about one returns the supplier's
             * defaults and would quietly overwrite the ordered cost and UOM
             * with them on the next save.
             */
            if ($l->isAssetItem()) {
                return array_merge(
                    $this->assetLine($l->asset, (float) $l->quantity, $l->uom_id),
                    [
                        'unit_cost'  => (string) floatval($l->unit_cost),
                        'total_cost' => round(floatval($l->quantity) * floatval($l->unit_cost), 4),
                    ]
                );
            }

            [$unitCost, $uomId, $packSize, $sSku, $sProdName] = $this->lookupSupplierInfo($l->ingredient_id, $po->supplier_id);
            $taxRate = $l->tax_rate_id ? TaxRate::find($l->tax_rate_id) : $l->ingredient?->effectiveTaxRate(Auth::user()->company);
            $totalCost = round(floatval($l->quantity) * floatval($l->unit_cost), 4);
            $taxPct = floatval($taxRate?->rate ?? 0);
            return [
                'ingredient_id'          => $l->ingredient_id,
                'ingredient_name'        => $l->ingredient?->name ?? '—',
                'quantity'               => (string) floatval($l->quantity),
                'uom_id'                 => $uomId,
                'unit_cost'              => (string) floatval($l->unit_cost),
                'total_cost'             => $totalCost,
                'tax_rate_id'            => $taxRate?->id,
                'tax_label'              => $taxRate ? ($taxRate->name . ' ' . rtrim(rtrim(number_format($taxRate->rate, 2), '0'), '.') . '%') : null,
                'tax_rate_pct'           => $taxPct,
                'tax_amount'             => $taxPct > 0 ? round($totalCost * ($taxPct / 100), 4) : 0,
                'pack_size'              => $packSize,
                'pack_info'              => $this->buildPackInfo($l->ingredient_id, $uomId, $packSize),
                'par_level'              => (string) $this->getParLevel($l->ingredient_id),
                'balance'                => '',
                'supplier_sku'           => $l->supplier_sku ?? $sSku,
                'supplier_product_name'  => $l->supplier_product_name ?? $sProdName,
                'supplier_id_override'   => $po->supplier_id,
            ];
        })->toArray();
    }

    public function updatedSelectedTemplateId(): void
    {
        if ($this->selectedTemplateId) {
            $this->loadTemplate();
        }
    }

    public function updatedSupplierId(): void
    {
        // Re-price existing lines from the newly selected supplier's catalog
        foreach ($this->lines as $idx => $line) {
            /*
             * An asset is not in anybody's catalogue.
             *
             * lookupSupplierInfo() with no ingredient returns the supplier's
             * defaults, so re-pricing an asset line here replaced its UOM
             * with null and its cost with zero — simply choosing a supplier
             * destroyed the line.
             */
            if (! empty($line['asset_id'])) {
                continue;
            }

            $ingredientId = (int) $line['ingredient_id'];
            [$unitCost, $supplierUomId, $packSize, $sSku, $sProdName] = $this->lookupSupplierInfo($ingredientId, $this->supplier_id);
            $this->lines[$idx]['unit_cost']  = (string) $unitCost;
            $this->lines[$idx]['uom_id']     = $supplierUomId;
            $this->lines[$idx]['pack_size']  = $packSize;
            $this->lines[$idx]['pack_info']  = $this->buildPackInfo($ingredientId, $supplierUomId, $packSize);
            $this->recalcLine($idx);
        }

        // Auto-load template linked to supplier (only on new PO with no lines)
        if ($this->supplier_id && empty($this->lines) && ! $this->orderId) {
            $template = FormTemplate::ofType('purchase_order')
                ->active()
                ->where('supplier_id', $this->supplier_id)
                ->first();

            if ($template) {
                $this->selectedTemplateId = (string) $template->id;
                $this->loadTemplate();
            }
        }
    }

    // ── Load from template ────────────────────────────────────────────────

    public function loadTemplate(): void
    {
        if (! $this->selectedTemplateId) return;

        $template = FormTemplate::with([
            'lines.ingredient.baseUom',
        ])->find((int) $this->selectedTemplateId);

        if (! $template) {
            $this->selectedTemplateId = '';
            return;
        }

        // Pre-fill header fields from template (only if not already set)
        if (! $this->supplier_id && $template->supplier_id) {
            $this->supplier_id = $template->supplier_id;
        }
        if (! $this->receiver_name && $template->receiver_name) {
            $this->receiver_name = $template->receiver_name;
        }
        if (! $this->department_id && $template->department_id) {
            $this->department_id = $template->department_id;
        }

        $existing = collect($this->lines)->pluck('ingredient_id')->map(fn ($id) => (int) $id)->toArray();
        $added = 0;

        foreach ($template->lines as $tLine) {
            if ($tLine->item_type !== 'ingredient' || ! $tLine->ingredient) continue;
            if (in_array($tLine->ingredient_id, $existing)) continue;

            [$unitCost, $supplierUomId, $packSize, $sSku, $sProdName] = $this->lookupSupplierInfo($tLine->ingredient_id, $this->supplier_id);
            $parLevel = $this->getParLevel($tLine->ingredient_id);
            $qty      = (int) ceil($parLevel > 0 ? $parLevel : max(0, $tLine->default_quantity));

            $taxRate = $tLine->ingredient->effectiveTaxRate(Auth::user()->company);
            $totalCost = round($qty * $unitCost, 4);
            $taxPct = floatval($taxRate?->rate ?? 0);

            $this->lines[] = [
                'ingredient_id'   => $tLine->ingredient_id,
                'ingredient_name' => $tLine->ingredient->name,
                'quantity'        => (string) $qty,
                'uom_id'          => $supplierUomId,
                'unit_cost'       => (string) $unitCost,
                'total_cost'      => $totalCost,
                'tax_rate_id'     => $taxRate?->id,
                'tax_label'       => $taxRate ? ($taxRate->name . ' ' . rtrim(rtrim(number_format($taxRate->rate, 2), '0'), '.') . '%') : null,
                'tax_rate_pct'    => $taxPct,
                'tax_amount'      => $taxPct > 0 ? round($totalCost * ($taxPct / 100), 4) : 0,
                'pack_size'       => $packSize,
                'pack_info'       => $this->buildPackInfo($tLine->ingredient_id, $supplierUomId, $packSize),
                'par_level'       => (string) $parLevel,
                'balance'         => '',
            ];

            $existing[] = $tLine->ingredient_id;
            $added++;
        }

        $this->selectedTemplateId = '';

        if ($added === 0) {
            session()->flash('info', 'All items from that template are already in the order.');
        }
    }

    public function addIngredient(int $ingredientId): void
    {
        $ingredient = Ingredient::find($ingredientId);
        if (! $ingredient) return;

        // Skip duplicates
        foreach ($this->lines as $line) {
            if ((int) $line['ingredient_id'] === $ingredientId) {
                $this->ingredientSearch = '';
                return;
            }
        }

        [$unitCost, $supplierUomId, $packSize, $sSku, $sProdName] = $this->lookupSupplierInfo($ingredientId, $this->supplier_id);

        $parLevel = $this->getParLevel($ingredientId);

        // Determine preferred supplier for this ingredient
        $preferredSupplierId = $this->supplier_id; // header supplier if set
        if (! $preferredSupplierId) {
            $preferredSupplierId = DB::table('supplier_ingredients')
                ->where('ingredient_id', $ingredientId)
                ->where('is_preferred', true)
                ->value('supplier_id');
        }

        $taxRate = $ingredient->effectiveTaxRate(Auth::user()->company);

        $this->lines[] = [
            'ingredient_id'          => $ingredientId,
            'ingredient_name'        => $ingredient->name,
            'quantity'               => $parLevel > 0 ? (string) (int) ceil($parLevel) : '1',
            'uom_id'                 => $supplierUomId,
            'unit_cost'              => (string) $unitCost,
            'total_cost'             => $parLevel > 0 ? round($parLevel * $unitCost, 4) : $unitCost,
            'tax_rate_id'            => $taxRate?->id,
            'tax_label'              => $taxRate ? ($taxRate->name . ' ' . rtrim(rtrim(number_format($taxRate->rate, 2), '0'), '.') . '%') : null,
            'tax_rate_pct'           => floatval($taxRate?->rate ?? 0),
            'tax_amount'             => 0,
            'pack_size'              => $packSize,
            'pack_info'              => $this->buildPackInfo($ingredientId, $supplierUomId, $packSize),
            'par_level'              => (string) $parLevel,
            'balance'                => '',
            'supplier_sku'           => $sSku,
            'supplier_product_name'  => $sProdName,
            'supplier_id_override'   => $preferredSupplierId,
        ];

        // Recalculate the last added line to set tax_amount
        $this->recalcLine(count($this->lines) - 1);

        $this->ingredientSearch = '';
    }

    public function removeLine(int $idx): void
    {
        unset($this->lines[$idx]);
        $this->lines = array_values($this->lines);
    }

    public function reorderLines(array $orderedIndexes): void
    {
        $ordered = [];
        foreach ($orderedIndexes as $idx) {
            $idx = (int) $idx;
            if (isset($this->lines[$idx])) {
                $ordered[] = $this->lines[$idx];
            }
        }
        if (count($ordered) === count($this->lines)) {
            $this->lines = $ordered;
        }
    }

    public function updatedLines($value, $key): void
    {
        $parts = explode('.', $key);
        if (count($parts) !== 2) return;

        $idx = (int) $parts[0];
        $field = $parts[1];

        if ($field === 'balance' && isset($this->lines[$idx])) {
            $parLevel = floatval($this->lines[$idx]['par_level'] ?? 0);
            $balance = floatval($value);
            if ($parLevel > 0) {
                $orderQty = (int) ceil(max(0, $parLevel - $balance));
                $this->lines[$idx]['quantity'] = (string) $orderQty;
            }
            $this->recalcLine($idx);
        } elseif (in_array($field, ['quantity', 'unit_cost'])) {
            $this->recalcLine($idx);
        }
    }

    public function save(string $action = 'save'): void
    {
        // Route middleware already gates reaching this screen, but a Livewire action is
        // its own request to /livewire/update — so the write is re-checked here rather
        // than trusted to how the component was first loaded. Raising and amending are
        // separate abilities: a clerk may be allowed to draft a new order without being
        // allowed to change one that already exists.
        $this->authorizeWrite();

        $this->validate();

        if (! $this->everyLineNamesSomething()) {
            return;
        }

        $user   = Auth::user();
        $taxPct = floatval($user->company?->tax_percent ?? 0);

        /*
         * Only the create path needs an outlet, and only the create path sets
         * one — an edit leaves outlet_id alone, which is correct, so deriving
         * it here would be asking a question nobody uses the answer to.
         */
        // A converted request belongs to the branch that raised it. Falling back
        // to the active outlet put the order on whoever happened to convert it —
        // and in Central Kitchen mode, where there is no active outlet at all,
        // it simply could not be converted.
        $outletId = $this->orderId
            ? null
            : ($this->sourcePrOutlet ?: $this->requireActiveOutlet());

        $requiresApproval = $user->company?->require_po_approval ?? true;

        if ($action === 'submit') {
            $status = $requiresApproval ? 'submitted' : 'approved';
        } else {
            $status = $this->status ?: 'draft';
        }

        // Remove zero-quantity lines
        $this->lines = array_values(array_filter($this->lines, fn ($l) => floatval($l['quantity']) > 0));

        // Detect if lines have multiple suppliers (for new POs only)
        if (! $this->orderId) {
            $supplierIds = collect($this->lines)
                ->pluck('supplier_id_override')
                ->filter()
                ->unique();

            // If no header supplier selected and lines have mixed suppliers, auto-split
            if (! $this->supplier_id && $supplierIds->count() > 1) {
                $splitLines = collect($this->lines)->map(function ($l) {
                    // Determine supplier: use per-line override, or lookup preferred
                    $sid = $l['supplier_id_override'] ?? null;
                    if (! $sid && $l['ingredient_id']) {
                        $preferred = DB::table('supplier_ingredients')
                            ->where('ingredient_id', $l['ingredient_id'])
                            ->where('is_preferred', true)
                            ->value('supplier_id');
                        $sid = $preferred;
                    }
                    /*
                     * An asset keeps its supplier in asset_suppliers, not in
                     * supplier_ingredients — and PoSplitService drops any line
                     * it cannot put under a supplier, so without this an asset
                     * simply vanished from a split order while its money
                     * stayed on the header.
                     */
                    if (! $sid && ! empty($l['asset_id'])) {
                        $sid = \App\Models\Asset::find($l['asset_id'])?->preferredSupplier()?->id;
                    }
                    return array_merge($l, ['supplier_id' => $sid]);
                })->toArray();

                $headerData = [
                    'company_id'            => $user->company_id,
                    'outlet_id'             => $outletId,
                    'order_date'            => $this->order_date,
                    'expected_delivery_date' => $this->expected_delivery_date ?: null,
                    'notes'                 => $this->notes ?: null,
                    'receiver_name'         => $this->receiver_name ?: null,
                    'department_id'         => $this->department_id ?: null,
                    'tax_percent'           => $taxPct,
                    'status'                => $status,
                    'created_by'            => Auth::id(),
                    'approved_by'           => ($action === 'submit' && ! $requiresApproval) ? Auth::id() : null,
                ];

                $poIds = \App\Services\PoSplitService::splitAndCreate($splitLines, $headerData);
                $count = count($poIds);

                session()->flash('success', "Order split into {$count} Purchase Order(s) by supplier.");
                $this->redirectRoute('purchasing.index', ['tab' => 'po']);
                return;
            }
        }

        // Single-supplier PO flow — per-line tax totals
        $subtotal = collect($this->lines)->sum(fn ($l) => floatval($l['quantity']) * floatval($l['unit_cost']));
        $taxAmt   = collect($this->lines)->sum(fn ($l) => floatval($l['tax_amount'] ?? 0));
        $total    = round($subtotal + $taxAmt, 4);

        $data = [
            'supplier_id'            => $this->supplier_id,
            'order_date'             => $this->order_date,
            'expected_delivery_date' => $this->expected_delivery_date ?: null,
            'notes'                  => $this->notes ?: null,
            'receiver_name'          => $this->receiver_name ?: null,
            'department_id'          => $this->department_id ?: null,
            'total_amount'           => $total,
            'subtotal'               => $subtotal,
            'tax_percent'            => $taxPct,
            'tax_amount'             => $taxAmt,
            'status'                 => $status,
        ];

        if ($action === 'submit' && ! $requiresApproval) {
            $data['approved_by'] = Auth::id();
        }

        if ($this->orderId) {
            $po = PurchaseOrder::findOrFail($this->orderId);
            $po->update($data);
        } else {
            $data['company_id'] = $user->company_id;
            $data['outlet_id']  = $outletId;
            $data['po_number']  = $this->poNumber;
            $data['created_by'] = Auth::id();
            // The column has always been here; this form never filled it, so a
            // converted order knew nothing about the request behind it.
            $data['purchase_request_id'] = $this->sourcePrId;
            $po = PurchaseOrder::create($data);
        }

        // Sync lines — remove items with zero quantity
        $this->lines = array_values(array_filter($this->lines, fn ($l) => floatval($l['quantity']) > 0));

        // Track adjustments on existing PO lines (for approved/sent POs being edited)
        if ($this->orderId && in_array($this->status, ['approved', 'sent', 'partial'])) {
            $existingLines = $po->lines()->get()->keyBy(fn ($l) => self::lineKey($l));
            foreach ($this->lines as $line) {
                $existing = $existingLines->get(self::lineKey($line));
                if ($existing) {
                    $newQty = floatval($line['quantity']);
                    $oldQty = floatval($existing->quantity);
                    if (abs($newQty - $oldQty) > 0.0001) {
                        OrderAdjustmentService::adjustQuantity($existing, $newQty, 'Adjusted during PO edit');
                    }
                    $newCost = floatval($line['unit_cost']);
                    $oldCost = floatval($existing->unit_cost);
                    if (abs($newCost - $oldCost) > 0.0001) {
                        OrderAdjustmentService::adjustUnitCost($existing, $newCost, 'Price adjusted during PO edit');
                    }
                }
            }
        }

        // Capture existing lines for the activity trail before replacing them.
        $auditBefore = [];
        foreach ($po->lines()->with(['ingredient', 'asset', 'uom'])->get() as $l) {
            $auditBefore[self::lineKey($l)] = [
                'item'     => $l->displayName(),
                'quantity' => (float) $l->quantity,
                'unit'     => $l->uom?->abbreviation ?? $l->uom?->code,
            ];
        }

        $po->lines()->delete();
        foreach ($this->lines as $line) {
            $qty  = floatval($line['quantity']);
            $cost = floatval($line['unit_cost']);
            $po->lines()->create([
                'ingredient_id'          => $line['ingredient_id'] ?: null,
                'asset_id'               => $line['asset_id'] ?? null,
                'supplier_sku'           => $line['supplier_sku'] ?? null,
                'supplier_product_name'  => $line['supplier_product_name'] ?? null,
                'quantity'               => $qty,
                'uom_id'                 => $line['uom_id'],
                'unit_cost'              => $cost,
                'total_cost'             => round($qty * $cost, 4),
                'tax_rate_id'            => $line['tax_rate_id'] ?? null,
                'tax_amount'             => floatval($line['tax_amount'] ?? 0),
                'received_quantity'      => 0,
            ]);
        }

        // Log item add / remove / quantity changes on edits.
        if ($this->orderId) {
            $ingIds   = array_filter(array_map(fn ($l) => (int) ($l['ingredient_id'] ?? 0), $this->lines));
            $assetIds = array_filter(array_map(fn ($l) => (int) ($l['asset_id'] ?? 0), $this->lines));
            $uomIds   = array_filter(array_map(fn ($l) => (int) ($l['uom_id'] ?? 0), $this->lines));
            $names      = \App\Models\Ingredient::whereIn('id', $ingIds)->pluck('name', 'id');
            $assetNames = \App\Models\Asset::whereIn('id', $assetIds)->pluck('name', 'id');
            $uoms       = UnitOfMeasure::whereIn('id', $uomIds)->pluck('abbreviation', 'id');
            $auditAfter = [];
            foreach ($this->lines as $l) {
                // An asset line is logged like any other. Skipping it here
                // while auditBefore records it would read as a deletion on
                // every save of an order that has one.
                $ingId   = (int) ($l['ingredient_id'] ?? 0);
                $assetId = (int) ($l['asset_id'] ?? 0);
                if (! $ingId && ! $assetId) continue;
                $auditAfter[self::lineKey($l)] = [
                    'item'     => $assetId
                        ? ($assetNames[$assetId] ?? ('#' . $assetId))
                        : ($names[$ingId] ?? ('#' . $ingId)),
                    'quantity' => (float) ($l['quantity'] ?? 0),
                    'unit'     => $uoms[(int) ($l['uom_id'] ?? 0)] ?? null,
                ];
            }
            \App\Services\AuditLogService::logLineChanges($po, $auditBefore, $auditAfter);
        }

        if ($action === 'submit') {
            $msg = $requiresApproval ? 'PO submitted for approval.' : 'PO approved and sent to purchasing team.';
        } else {
            $msg = 'Purchase order saved as draft.';
        }
        session()->flash('success', $msg);
        $this->redirectRoute('purchasing.index', ['tab' => 'po']);
    }

    public function render()
    {
        // selectable() keeps whichever this order already names, so a
        // retired supplier does not vanish from the order that used them.
        $suppliers   = Supplier::selectable($this->supplier_id)->orderBy('name')->get();
        $uoms        = UnitOfMeasure::orderBy('name')->get();
        $departments = Department::selectable($this->department_id)->ordered()->get();

        $searchResults = collect();
        if (strlen($this->ingredientSearch) >= 2) {
            $q = Ingredient::with(['baseUom'])
                ->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->ingredientSearch . '%')
                      ->orWhere('code', 'like', '%' . $this->ingredientSearch . '%');
                })
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(8);

            // Prioritise ingredients linked to the selected supplier
            if ($this->supplier_id) {
                $supplierIngIds = DB::table('supplier_ingredients')
                    ->where('supplier_id', $this->supplier_id)
                    ->pluck('ingredient_id')
                    ->toArray();
                if ($supplierIngIds) {
                    $ids = implode(',', $supplierIngIds);
                    $q->orderByRaw("CASE WHEN id IN ({$ids}) THEN 0 ELSE 1 END");
                }
            }

            $searchResults = $q->get();
        }

        $subtotal           = collect($this->lines)->sum(fn ($l) => floatval($l['quantity']) * floatval($l['unit_cost']));
        $company            = Auth::user()->company;

        // Per-line tax breakdown grouped by tax label
        $taxBreakdown = collect($this->lines)
            ->filter(fn ($l) => floatval($l['tax_amount'] ?? 0) > 0)
            ->groupBy('tax_label')
            ->map(fn ($group) => round($group->sum(fn ($l) => floatval($l['tax_amount'])), 2))
            ->toArray();
        $taxAmount          = collect($this->lines)->sum(fn ($l) => floatval($l['tax_amount'] ?? 0));
        $grandTotal         = round($subtotal + $taxAmount, 4);
        $availableTemplates = FormTemplate::ofType('purchase_order')->active()->ordered()->get();
        $isEditable         = ! $this->orderId || in_array($this->status, ['draft', 'submitted']);
        $requirePoApproval  = $company?->require_po_approval ?? true;

        $pageTitle = $this->orderId
            ? ($isEditable ? 'Edit PO: ' : 'View PO: ') . $this->poNumber
            : 'New Purchase Order';

        return view('livewire.purchasing.order-form', compact(
            'suppliers', 'uoms', 'departments', 'searchResults', 'subtotal', 'taxBreakdown', 'taxAmount', 'grandTotal', 'availableTemplates', 'isEditable', 'requirePoApproval'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => $pageTitle]);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /**
     * Look up the supplier's cost, UOM, pack_size, SKU, and product name for an ingredient.
     * Returns [unit_cost, uom_id, pack_size, supplier_sku, supplier_product_name].
     */
    private function lookupSupplierInfo(int $ingredientId, ?int $supplierId): array
    {
        $ingredient = Ingredient::find($ingredientId);
        $fallbackUom = $ingredient?->base_uom_id;
        $fallbackCost = floatval($ingredient?->purchase_price ?? 0);
        $fallbackPackSize = floatval($ingredient?->pack_size ?? 1) ?: 1;

        $unitCost = $fallbackCost;
        $uomId = $fallbackUom;
        $packSize = $fallbackPackSize;
        $supplierSku = null;
        $supplierProductName = null;

        if ($supplierId) {
            $pivot = DB::table('supplier_ingredients')
                ->where('supplier_id', $supplierId)
                ->where('ingredient_id', $ingredientId)
                ->first();
            if ($pivot) {
                $packSize = floatval($pivot->pack_size ?? 1) ?: $fallbackPackSize;
                $unitCost = floatval($pivot->last_cost) > 0 ? floatval($pivot->last_cost) : $fallbackCost;
                $uomId = $pivot->uom_id ?? $fallbackUom;
                $supplierSku = $pivot->supplier_sku;
            }

            // Check for mapped supplier product (richer data)
            $mapping = DB::table('supplier_product_mappings')
                ->join('supplier_products', 'supplier_products.id', '=', 'supplier_product_mappings.supplier_product_id')
                ->where('supplier_products.supplier_id', $supplierId)
                ->where('supplier_product_mappings.ingredient_id', $ingredientId)
                ->select('supplier_products.sku', 'supplier_products.name', 'supplier_products.pack_size as sp_pack_size', 'supplier_products.unit_price')
                ->first();

            if ($mapping) {
                $supplierSku = $mapping->sku ?? $supplierSku;
                $supplierProductName = $mapping->name;
                if (floatval($mapping->sp_pack_size) > 1) {
                    $packSize = floatval($mapping->sp_pack_size);
                }
            }
        }

        // When pack_size > 1, use "pack" as the ordering UOM
        if ($packSize > 1) {
            $packUom = UnitOfMeasure::where('abbreviation', 'pack')->first();
            if ($packUom) {
                $uomId = $packUom->id;
            }
        }

        return [$unitCost, $uomId, $packSize, $supplierSku, $supplierProductName];
    }

    private function recalcLine(int $idx): void
    {
        if (! isset($this->lines[$idx])) return;
        $qty  = floatval($this->lines[$idx]['quantity'] ?? 0);
        $cost = floatval($this->lines[$idx]['unit_cost'] ?? 0);
        $totalCost = round($qty * $cost, 4);
        $taxPct = floatval($this->lines[$idx]['tax_rate_pct'] ?? 0);
        $this->lines[$idx]['total_cost'] = $totalCost;
        $this->lines[$idx]['tax_amount'] = $taxPct > 0 ? round($totalCost * ($taxPct / 100), 4) : 0;
    }

    private function getParLevel(int $ingredientId): float
    {
        // No abort here — a missing par level is advisory, not a failure.
        $outletId = $this->orderOutletId ?? Auth::user()?->activeOutletId();

        if (!$outletId) return 0;

        return floatval(
            IngredientParLevel::withoutGlobalScopes()
                ->where('ingredient_id', $ingredientId)
                ->where('outlet_id', $outletId)
                ->value('par_level') ?? 0
        );
    }

    /**
     * Build a human-readable pack info string, e.g. "(1.2 KG/PACK)".
     */
    private function buildPackInfo(int $ingredientId, ?int $uomId, float $packSize): string
    {
        if ($packSize <= 1) return '';

        $ingredient = Ingredient::find($ingredientId);
        if (! $ingredient) return '';

        $baseUom = UnitOfMeasure::find($ingredient->base_uom_id);
        if (! $baseUom) return '';

        $formatted = rtrim(rtrim(number_format($packSize, 4, '.', ''), '0'), '.');
        return '(' . $formatted . ' ' . strtoupper($baseUom->abbreviation) . '/PACK)';
    }

    private function generatePoNumber(): string
    {
        $prefix = 'PO-' . now()->format('Ymd') . '-';
        $last   = PurchaseOrder::where('po_number', 'like', $prefix . '%')
            ->orderByDesc('po_number')
            ->value('po_number');
        $seq    = $last ? ((int) substr($last, -3) + 1) : 1;
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
