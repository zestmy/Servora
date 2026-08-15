<?php

namespace App\Livewire\Labels;

use App\Models\PrintAgent;
use App\Services\Labels\PrintAgentService;
use App\Traits\ValidatesCompanyOutlet;
use App\Models\Outlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Print agents — one row per outlet PC running the Servora Print Agent.
 *
 * The manager half of pairing: create the row (naming it, choosing its
 * outlet), read the code out to whoever is at the PC. The pairing code is
 * shown here; the TOKEN never is — it goes straight from the pair endpoint
 * into the agent's config file and no human sees it at all.
 */
class Agents extends Component
{
    use ValidatesCompanyOutlet;

    public bool $showModal = false;

    public ?int $outlet_id = null;

    public string $name = '';

    /**
     * The code being shown right now, keyed to its agent. Ephemeral on
     * purpose — it lives in this component's state for the minutes the
     * modal is open, not in any rendered list.
     */
    public ?int $pairingAgentId = null;

    public ?string $pairingCode = null;

    protected function rules(): array
    {
        return [
            'outlet_id' => ['required', 'integer', $this->outletExistsRule()],
            'name'      => 'required|string|max:100',
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->outlet_id = Auth::user()->activeOutletId();
        $this->showModal = true;
    }

    /** Create the agent and immediately show its first pairing code. */
    public function save(PrintAgentService $agents): void
    {
        $this->validate();

        $agent = PrintAgent::create([
            'company_id' => Auth::user()->company_id,
            'outlet_id'  => $this->outlet_id,
            'name'       => $this->name,
            'created_by' => Auth::id(),
        ]);

        $this->showModal = false;
        $this->resetForm();

        $this->showCode($agent->id, $agents);
    }

    /**
     * Issue (or re-issue) a pairing code. Always allowed on a live agent —
     * a regenerated code kills the old one, which is the correct behaviour
     * for "the ten minutes ran out while I walked to the counter".
     */
    public function showCode(int $id, PrintAgentService $agents): void
    {
        $agent = PrintAgent::findOrFail($id);

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

    /**
     * The token dies now; the row stays. Jobs point at the agent that
     * carried them, and a stolen PC is precisely when somebody asks which
     * labels went through it.
     */
    public function revoke(int $id, PrintAgentService $agents): void
    {
        $agents->revoke(PrintAgent::findOrFail($id), Auth::user());

        session()->flash('success', 'Agent revoked. Its token no longer works.');
    }

    /**
     * The base URL to type into the agent on first run.
     *
     * Built by hand, not with route(): these instructions are read on the
     * MAIN domain, where the subdomain route defaults aren't bound — the
     * same trap the staff-app QR links documented.
     */
    public function serverUrl(): string
    {
        $domain = config('app.domain');

        if ($domain) {
            $slug = Auth::user()->company?->slug;

            return 'https://' . $slug . '.' . $domain . '/agent';
        }

        return rtrim(url('/agent-api'), '/');
    }

    public function render()
    {
        return view('livewire.labels.agents', [
            'agents'  => PrintAgent::with('outlet')
                ->orderBy('revoked_at')->orderBy('outlet_id')->orderBy('name')->get(),
            'outlets' => Outlet::where('company_id', Auth::user()->company_id)
                ->orderBy('name')->get(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Label agents']);
    }

    private function resetForm(): void
    {
        $this->outlet_id = null;
        $this->name      = '';
        $this->resetValidation();
    }
}
