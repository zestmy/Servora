<?php

namespace App\Livewire\Purchasing;

use App\Models\Department;
use App\Models\Ingredient;
use App\Models\IngredientParLevel;
use App\Models\Outlet;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Services\ProcurementRoutingService;
use App\Services\PurchaseRequestService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PurchaseRequestForm extends Component
{
    public ?int $requestId = null;

    /** Raising a request and amending an existing one are separate abilities. */
    private function authorizeWrite(): void
    {
        abort_unless(
            Auth::user()?->canDo($this->requestId ? 'purchasing.requests.edit' : 'purchasing.requests.create'),
            403
        );
    }

    public string $prNumber = '';
    public string $status   = 'draft';

    public ?int   $outlet_id      = null;
    public string $requested_date = '';
    public string $needed_by_date = '';
    public string $notes          = '';
    public ?int   $department_id  = null;

    public array  $lines            = [];
    public string $ingredientSearch = '';

    /**
     * Outlets this user may raise a request for. In Central Kitchen mode there
     * is no "active outlet" to fall back on, so the request has to name its
     * outlet explicitly — otherwise it silently lands on whichever outlet
     * happened to be first in the user's assignments.
     */
    protected function accessibleOutletIds(): array
    {
        return Auth::user()->accessibleOutletIds();
    }

    protected function rules(): array
    {
        return [
            'outlet_id'                       => ['required', 'integer', \Illuminate\Validation\Rule::in($this->accessibleOutletIds())],
            'requested_date'                  => 'required|date',
            'needed_by_date'                  => 'nullable|date|after_or_equal:requested_date',
            'notes'                           => 'nullable|string',
            'department_id'                   => 'nullable|exists:departments,id',
            'lines'                           => 'required|array|min:1',
            'lines.*.ingredient_id'           => 'nullable|exists:ingredients,id',
            'lines.*.custom_name'             => 'nullable|string|max:200',
            'lines.*.quantity'                => 'required|numeric|min:0.0001',
            'lines.*.uom_id'                 => 'required|exists:units_of_measure,id',
            'lines.*.preferred_supplier_id'   => 'nullable|exists:suppliers,id',
        ];
    }

    protected function messages(): array
    {
        return [
            'lines.required'           => 'Add at least one ingredient.',
            'lines.min'                => 'Add at least one ingredient.',
            'lines.*.quantity.min'     => 'Quantity must be greater than zero.',
            'outlet_id.required'       => 'Choose which outlet this request is for.',
            'outlet_id.in'             => 'You do not have access to the selected outlet.',
        ];
    }

    public function mount(?int $id = null): void
    {
        $this->requested_date = now()->toDateString();

        if (! $id) {
            $this->prNumber = PurchaseRequestService::generatePrNumber();
            // Default to the active outlet where there is one (outlet mode);
            // kitchen mode has none, so fall back to the only accessible
            // outlet and otherwise leave the picker for the user.
            $accessible = $this->accessibleOutletIds();
            $active     = Auth::user()->activeOutletId();
            $this->outlet_id = ($active && in_array((int) $active, $accessible, true))
                ? (int) $active
                : (count($accessible) === 1 ? $accessible[0] : null);
            return;
        }

        $pr = PurchaseRequest::with(['lines.ingredient.baseUom', 'lines.uom', 'lines.preferredSupplier'])->findOrFail($id);

        if ($pr->outlet_id && ! Auth::user()->canAccessOutlet($pr->outlet_id)) {
            abort(403, 'You do not have access to this outlet.');
        }

        $this->requestId      = $pr->id;
        $this->prNumber       = $pr->pr_number;
        $this->status         = $pr->status;
        $this->outlet_id      = $pr->outlet_id;
        $this->requested_date = $pr->requested_date->toDateString();
        $this->needed_by_date = $pr->needed_by_date?->toDateString() ?? '';
        $this->notes          = $pr->notes ?? '';
        $this->department_id  = $pr->department_id;

        foreach ($pr->lines as $line) {
            $taxRate = $line->tax_rate_id
                ? \App\Models\TaxRate::find($line->tax_rate_id)
                : $line->ingredient?->effectiveTaxRate(Auth::user()->company);
            $this->lines[] = [
                'ingredient_id'        => $line->ingredient_id,
                'ingredient_name'      => $line->ingredient?->name ?? $line->custom_name ?? '—',
                'custom_name'          => $line->custom_name,
                'quantity'             => (float) $line->quantity,
                'uom_id'              => $line->uom_id,
                'preferred_supplier_id' => $line->preferred_supplier_id,
                'supplier_name'        => $line->preferredSupplier?->name ?? '',
                'source'               => $line->source ?? 'supplier',
                'kitchen_id'           => $line->kitchen_id,
                'par_level'            => $line->ingredient_id ? $this->getParLevel($line->ingredient_id) : 0,
                'notes'                => $line->notes ?? '',
                'tax_rate_id'          => $taxRate?->id,
                'tax_label'            => $taxRate ? ($taxRate->name . ' ' . rtrim(rtrim(number_format($taxRate->rate, 2), '0'), '.') . '%') : null,
            ];
        }
    }

    /*
     * Loading a form template.
     *
     * A stock-take or order form IS the list somebody walks the store with, in
     * the order they walk it. Retyping one into a request is transcription, and
     * transcription is where items go missing.
     */
    public bool $showTemplateImport = false;

    public ?int $importTemplateId = null;

    /** Only a draft can still be changed — the one place that decides it. */
    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', ''], true);
    }

    public function openTemplateImport(): void
    {
        $this->importTemplateId  = null;
        $this->showTemplateImport = true;
    }

    public function closeTemplateImport(): void
    {
        $this->showTemplateImport = false;
    }

    /**
     * Copy a template's items onto this request.
     *
     * QUANTITIES DO carry here, unlike the label sets that import the same
     * templates: a form's default quantity is how much to order or count, which
     * is exactly what a request line is asking for. Only ingredient lines come
     * across — a request orders ingredients, and a template's recipe lines have
     * nothing to buy behind them.
     *
     * Items already on the request are skipped rather than duplicated, so
     * loading twice is safe and loading again after the form grew adds only
     * what is new.
     */
    public function importTemplate(): void
    {
        if (! $this->isEditable() || ! $this->importTemplateId) {
            return;
        }

        $template = \App\Models\FormTemplate::with('lines')->find($this->importTemplateId);

        if (! $template) {
            return;
        }

        $before  = count($this->lines);
        $skipped = 0;

        foreach ($template->lines as $line) {
            if ($line->item_type !== 'ingredient' || ! $line->ingredient_id) {
                $skipped++;
                continue;
            }

            $countBefore = count($this->lines);
            $this->addIngredient((int) $line->ingredient_id);

            // addIngredient() returns quietly on a duplicate, so the quantity is
            // written only when a line was actually appended.
            if (count($this->lines) > $countBefore && (float) $line->default_quantity > 0) {
                $this->lines[count($this->lines) - 1]['quantity'] = (float) $line->default_quantity;
            }
        }

        $added = count($this->lines) - $before;
        $this->showTemplateImport = false;
        $this->ingredientSearch   = '';

        session()->flash('success', $this->importSummary($template->name, $added, $skipped));
    }

    /** Says what happened to every line, including the ones that did nothing. */
    private function importSummary(string $template, int $added, int $skipped): string
    {
        $parts = [sprintf('%d item%s added from “%s”', $added, $added === 1 ? '' : 's', $template)];

        if ($skipped) {
            $parts[] = sprintf('%d skipped — recipes have nothing to order', $skipped);
        }

        return implode('. ', $parts) . '.';
    }

    public function addIngredient(int $ingredientId): void
    {
        // Prevent duplicate
        foreach ($this->lines as $line) {
            if ($line['ingredient_id'] === $ingredientId) {
                $this->ingredientSearch = '';
                return;
            }
        }

        $ingredient = Ingredient::with('baseUom')->find($ingredientId);
        if (! $ingredient) return;

        // Find preferred supplier
        $preferred = $ingredient->suppliers()
            ->wherePivot('is_preferred', true)
            ->first();

        // Auto-detect prep items → source from the central kitchen serving this outlet
        $isPrep = $ingredient->is_prep;
        $kitchenId = $isPrep ? $this->resolveKitchenForRequest() : null;

        $taxRate = $ingredient->effectiveTaxRate(Auth::user()->company);

        $this->lines[] = [
            'ingredient_id'        => $ingredient->id,
            'ingredient_name'      => $ingredient->name,
            'quantity'             => 0,
            'uom_id'              => $ingredient->base_uom_id,
            'preferred_supplier_id' => $isPrep ? null : $preferred?->id,
            'supplier_name'        => $isPrep ? '' : ($preferred?->name ?? ''),
            'source'               => $isPrep ? 'kitchen' : 'supplier',
            'kitchen_id'           => $kitchenId,
            'par_level'            => $this->getParLevel($ingredient->id),
            'notes'                => '',
            'tax_rate_id'          => $taxRate?->id,
            'tax_label'            => $taxRate ? ($taxRate->name . ' ' . rtrim(rtrim(number_format($taxRate->rate, 2), '0'), '.') . '%') : null,
        ];

        $this->ingredientSearch = '';
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    /**
     * Kitchen routing and par levels are both properties of the outlet, but the
     * lines capture them when the item is added — which is often before the
     * outlet is picked. Re-derive them so a line added first doesn't keep the
     * kitchen (or par level) of the outlet that merely happened to be selected
     * at the time.
     */
    public function updatedOutletId(): void
    {
        $kitchenId = $this->resolveKitchenForRequest();

        foreach ($this->lines as $i => $line) {
            if (($line['source'] ?? 'supplier') === 'kitchen') {
                $this->lines[$i]['kitchen_id'] = $kitchenId;
            }

            if (! empty($line['ingredient_id'])) {
                $this->lines[$i]['par_level'] = $this->getParLevel((int) $line['ingredient_id']);
            }
        }
    }

    public function save(string $action = 'save')
    {
        // Re-checked here, not just on the route: a Livewire action is its own request.
        $this->authorizeWrite();

        $this->validate();

        // A prep item must resolve to a central kitchen before it can be submitted,
        // otherwise it would be silently dropped during production-order creation.
        // Drafts may still be saved so the requester can fix the branch assignment.
        if ($action === 'submit') {
            $missingKitchen = false;
            foreach ($this->lines as $i => $line) {
                if (($line['source'] ?? 'supplier') === 'kitchen' && empty($line['kitchen_id'])) {
                    $this->addError("lines.$i.kitchen_id",
                        'No central kitchen serves this branch for prep items. Assign one in Settings ▸ Branches.');
                    $missingKitchen = true;
                }
            }
            if ($missingKitchen) {
                return;
            }
        }

        $user = Auth::user();
        $company = $user->company;
        // Explicitly chosen and validated against the user's accessible outlets.
        $outletId = $this->outlet_id;

        // Determine the CPU that consolidates this outlet's requests
        $cpuId = ProcurementRoutingService::resolveCpuId($outletId, $user);

        $data = [
            'company_id'     => $user->company_id,
            'outlet_id'      => $outletId,
            'cpu_id'         => $cpuId,
            'pr_number'      => $this->prNumber,
            'requested_date' => $this->requested_date,
            'needed_by_date' => $this->needed_by_date ?: null,
            'notes'          => $this->notes ?: null,
            'department_id'  => $this->department_id,
            'created_by'     => $user->id,
        ];

        if ($action === 'submit') {
            $requiresApproval = $company->require_pr_approval ?? false;
            $data['status'] = $requiresApproval ? 'submitted' : 'approved';
            if (! $requiresApproval) {
                $data['approved_by'] = $user->id;
                $data['approved_at'] = now();
            }
        } else {
            $data['status'] = 'draft';
        }

        if ($this->requestId) {
            $pr = PurchaseRequest::findOrFail($this->requestId);
            $pr->update($data);
            $pr->lines()->delete();
        } else {
            $pr = PurchaseRequest::create($data);
            $this->requestId = $pr->id;
        }

        foreach ($this->lines as $line) {
            $pr->lines()->create([
                'ingredient_id'        => $line['ingredient_id'] ?: null,
                'custom_name'          => $line['custom_name'] ?? null,
                'quantity'             => $line['quantity'],
                'uom_id'              => $line['uom_id'],
                'preferred_supplier_id' => $line['preferred_supplier_id'] ?: null,
                'source'               => $line['source'] ?? 'supplier',
                'kitchen_id'           => $line['kitchen_id'] ?? null,
                'notes'                => $line['notes'] ?? null,
                'tax_rate_id'          => $line['tax_rate_id'] ?? null,
            ]);
        }

        $this->status = $pr->status;

        $message = $action === 'submit'
            ? ($data['status'] === 'submitted' ? 'Purchase request submitted for approval.' : 'Purchase request approved.')
            : 'Purchase request saved as draft.';

        session()->flash('success', $message);

        return $this->redirect(route('purchasing.index', ['tab' => 'pr']), navigate: true);
    }

    /**
     * The outlet this request is being raised for. The picker wins; the active
     * outlet is only a fallback for outlet-mode users who never see the picker.
     * In Central Kitchen mode there is no active outlet at all, so reading it
     * directly would route the request off whatever the fallbacks guessed.
     */
    private function requestOutletId(): ?int
    {
        return $this->outlet_id ?: Auth::user()->activeOutletId();
    }

    private function resolveKitchenForRequest(): ?int
    {
        return ProcurementRoutingService::resolveKitchenId(
            $this->requestOutletId(),
            Auth::user()
        );
    }

    private function getParLevel(int $ingredientId): float
    {
        // Par levels belong to the outlet the request is being raised for,
        // not whichever outlet the user happens to be assigned to.
        $outletId = $this->requestOutletId();
        if (! $outletId) return 0;

        return (float) (IngredientParLevel::where('ingredient_id', $ingredientId)
            ->where('outlet_id', $outletId)
            ->value('par_level') ?? 0);
    }

    public function render()
    {
        $isEditable = $this->isEditable();

        $searchResults = [];
        if (strlen($this->ingredientSearch) >= 2) {
            $searchResults = Ingredient::where('is_active', true)
                ->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->ingredientSearch . '%')
                      ->orWhere('code', 'like', '%' . $this->ingredientSearch . '%');
                })
                ->with('baseUom')
                ->orderBy('name')
                ->limit(15)
                ->get();
        }

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $departments = Department::where('is_active', true)->orderBy('sort_order')->get();
        $uoms = UnitOfMeasure::orderBy('name')->get();

        $outlets = Outlet::whereIn('id', $this->accessibleOutletIds() ?: [0])
            ->selectable($this->outlet_id)
            ->orderBy('name')
            ->get();

        return view('livewire.purchasing.purchase-request-form', [
            'searchResults' => $searchResults,
            'suppliers'     => $suppliers,
            'departments'   => $departments,
            'uoms'          => $uoms,
            'isEditable'    => $isEditable,
            'outlets'       => $outlets,
            // Only forms with something to order on them: a template of nothing
            // but recipes would load as an empty request.
            'formTemplates' => \App\Models\FormTemplate::active()->ordered()
                ->withCount(['lines' => fn ($q) => $q->where('item_type', 'ingredient')])
                ->get()
                ->filter(fn ($t) => $t->lines_count > 0),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => $this->requestId ? 'Edit Purchase Request' : 'New Purchase Request']);
    }
}
