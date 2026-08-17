<?php

namespace App\Livewire\Sales;

use App\Models\Outlet;
use App\Models\PosAgent;
use App\Models\PosSalesBatch;
use App\Models\SalesCategory;
use App\Models\ZeoniqDepartmentMapping;
use App\Services\Sales\PosAgentService;
use App\Services\Sales\PosSalesBatchProcessor;
use App\Traits\ValidatesCompanyOutlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Sales › POS Sync — the manager half of the POS sales sync agent.
 *
 * Two tabs. Agents: pair a terminal exactly the way Labels › Agents pairs a
 * print agent (the code is shown here; the token never is). Batches: what
 * each terminal uploaded and what became of it — and the actions for the
 * three parked states: map new departments, retry a failure, or overrule a
 * conflict with hand-typed records.
 *
 * Every action guards itself with sales.import — the route carries the same
 * gate, but a Livewire action is its own request and must refuse on its own
 * merits.
 */
class PosSync extends Component
{
    use ValidatesCompanyOutlet, WithPagination;

    #[Url(as: 'tab', except: 'agents')]
    public string $tab = 'agents';

    // ── Agent creation ──────────────────────────────────────────────────
    public bool $showModal = false;
    public ?int $outlet_id = null;
    public string $name = '';

    // ── Pairing code (ephemeral, modal-lifetime only) ───────────────────
    public ?int $pairingAgentId = null;
    public ?string $pairingCode = null;

    // ── Mapping panel ───────────────────────────────────────────────────
    public ?int $mappingBatchId = null;
    /** @var array<string, int|string|null> dept name => sales_category_id */
    public array $mappingSelections = [];

    protected function rules(): array
    {
        return [
            'outlet_id' => ['required', 'integer', $this->outletExistsRule()],
            'name'      => 'required|string|max:100',
        ];
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['agents', 'batches'], true) ? $tab : 'agents';
        $this->resetPage();
    }

    // ── Agents ──────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        abort_unless(Auth::user()->canDo('sales.import'), 403);

        $this->resetForm();
        $this->outlet_id = Auth::user()->activeOutletId();
        $this->showModal = true;
    }

    /** Create the agent and immediately show its first pairing code. */
    public function save(PosAgentService $agents): void
    {
        abort_unless(Auth::user()->canDo('sales.import'), 403);

        $this->validate();

        $agent = PosAgent::create([
            'company_id' => Auth::user()->company_id,
            'outlet_id'  => $this->outlet_id,
            'name'       => $this->name,
            'created_by' => Auth::id(),
        ]);

        $this->showModal = false;
        $this->resetForm();

        $this->showCode($agent->id, $agents);
    }

    public function showCode(int $id, PosAgentService $agents): void
    {
        abort_unless(Auth::user()->canDo('sales.import'), 403);

        $agent = PosAgent::findOrFail($id);

        if ($agent->isRevoked()) {
            session()->flash('success', 'This agent is revoked — add a new one instead.');

            return;
        }

        $this->pairingAgentId = $agent->id;
        $this->pairingCode    = $agents->issuePairingCode($agent);
    }

    public function closeCode(): void
    {
        $this->pairingAgentId = null;
        $this->pairingCode    = null;
    }

    public function revoke(int $id, PosAgentService $agents): void
    {
        abort_unless(Auth::user()->canDo('sales.import'), 403);

        $agents->revoke(PosAgent::findOrFail($id), Auth::user());

        session()->flash('success', 'Agent revoked. Its token no longer works.');
    }

    /** Built by hand, not route() — read on the main domain. See Labels\Agents. */
    public function serverUrl(): string
    {
        $domain = config('app.domain');

        if ($domain) {
            $slug = Auth::user()->company?->slug;

            return 'https://' . $slug . '.' . $domain . '/pos-agent';
        }

        return rtrim(url('/pos-agent-api'), '/');
    }

    // ── Batches ─────────────────────────────────────────────────────────

    /** Re-run a failed batch — same inputs, fresh attempt. */
    public function retry(int $id, PosSalesBatchProcessor $processor): void
    {
        abort_unless(Auth::user()->canDo('sales.import'), 403);

        $processor->reapply($this->batch($id), Auth::user());

        session()->flash('success', 'Batch re-processed.');
    }

    /** A human decides the POS figures replace the hand-typed records. */
    public function applyAnyway(int $id, PosSalesBatchProcessor $processor): void
    {
        abort_unless(Auth::user()->canDo('sales.import'), 403);

        $processor->reapply($this->batch($id), Auth::user(), overrideConflicts: true);

        session()->flash('success', 'Batch applied over the existing records.');
    }

    public function openMapping(int $id): void
    {
        abort_unless(Auth::user()->canDo('sales.import'), 403);

        $batch = $this->batch($id);

        $this->mappingBatchId    = $batch->id;
        $this->mappingSelections = [];

        $suggestions = collect($batch->parsed_summary['suggestions'] ?? [])
            ->keyBy('zeoniq_department');

        foreach ($batch->parsed_summary['unmapped_departments'] ?? [] as $dept) {
            $this->mappingSelections[$dept] = $suggestions[$dept]['suggested_category_id'] ?? null;
        }
    }

    public function closeMapping(): void
    {
        $this->mappingBatchId    = null;
        $this->mappingSelections = [];
    }

    /**
     * Persist the chosen mappings — the same rows the Excel import screen
     * writes, so both paths learn together — then re-apply the batch.
     */
    public function saveMappings(PosSalesBatchProcessor $processor): void
    {
        abort_unless(Auth::user()->canDo('sales.import'), 403);

        $batch = $this->batch($this->mappingBatchId);

        $validIds = SalesCategory::pluck('id')->all();

        $missing = collect($this->mappingSelections)->filter(fn ($v) => ! $v)->keys();
        if ($missing->isNotEmpty()) {
            $this->addError('mapping', 'Choose a Sales Category for: ' . $missing->implode(', '));

            return;
        }

        foreach ($this->mappingSelections as $dept => $categoryId) {
            if (! in_array((int) $categoryId, $validIds, true)) {
                $this->addError('mapping', 'Unknown Sales Category selected.');

                return;
            }

            ZeoniqDepartmentMapping::updateOrCreate(
                ['company_id' => Auth::user()->company_id, 'zeoniq_department_name' => $dept],
                ['sales_category_id' => (int) $categoryId, 'created_by' => Auth::id()],
            );
        }

        $this->closeMapping();

        $processor->reapply($batch, Auth::user());

        session()->flash('success', 'Departments mapped and the batch re-applied.');
    }

    private function batch(?int $id): PosSalesBatch
    {
        // Company-scoped by the global scope (web request, auth user) — a
        // forged id from another tenant 404s here.
        return PosSalesBatch::findOrFail($id);
    }

    public function render()
    {
        $needsAttention = PosSalesBatch::query()->needsAttention()->count();

        return view('livewire.sales.pos-sync', [
            'agents' => PosAgent::with('outlet')
                ->orderBy('revoked_at')->orderBy('outlet_id')->orderBy('name')->get(),
            'batches' => $this->tab === 'batches'
                ? PosSalesBatch::with(['agent', 'outlet'])
                    ->orderByRaw("case when status in ('needs_mapping','needs_review','failed') then 0 else 1 end")
                    ->latest('id')
                    ->paginate(20)
                : null,
            'needsAttention' => $needsAttention,
            'outlets' => Outlet::where('company_id', Auth::user()->company_id)
                ->orderBy('name')->get(),
            'salesCategories' => SalesCategory::active()->ordered()->get(),
            'mappingBatch' => $this->mappingBatchId ? $this->batch($this->mappingBatchId) : null,
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'POS Sync']);
    }

    private function resetForm(): void
    {
        $this->outlet_id = null;
        $this->name      = '';
        $this->resetValidation();
    }
}
