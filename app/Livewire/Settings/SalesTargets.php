<?php

namespace App\Livewire\Settings;

use App\Models\Outlet;
use App\Models\SalesTarget;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class SalesTargets extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $period         = '';
    public ?int   $outlet_id      = null;
    public string $target_revenue = '';
    public string $target_pax     = '';
    public string $notes          = '';

    protected function rules(): array
    {
        return [
            'period'         => 'required|string|size:7|regex:/^\d{4}-\d{2}$/',
            'outlet_id'      => 'nullable|exists:outlets,id',
            'target_revenue' => 'required|numeric|min:0',
            'target_pax'     => 'nullable|integer|min:0',
            'notes'          => 'nullable|string|max:500',
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->period = now()->format('Y-m');
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $target = SalesTarget::findOrFail($id);

        $this->editingId      = $target->id;
        $this->period         = $target->period;
        $this->outlet_id      = $target->outlet_id;
        $this->target_revenue = $target->target_revenue;
        $this->target_pax     = $target->target_pax ?? '';
        $this->notes          = $target->notes ?? '';

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $editing = $this->editingId ? SalesTarget::findOrFail($this->editingId) : null;

        // One target per outlet (or company-wide) per period — the table has a
        // unique index on (company, outlet, period, type), so a blind insert
        // 500s. Pre-check and show a friendly error instead. NULL outlets slip
        // past the unique index (MySQL allows repeated NULLs) so the same rule
        // is enforced here for company-wide targets too.
        $duplicate = SalesTarget::where('period', $this->period)
            ->where('type', $editing->type ?? 'monthly')
            ->when($this->outlet_id,
                fn ($q) => $q->where('outlet_id', $this->outlet_id),
                fn ($q) => $q->whereNull('outlet_id'))
            ->when($editing, fn ($q) => $q->where('id', '!=', $editing->id))
            ->exists();
        if ($duplicate) {
            $this->addError('period', $this->outlet_id
                ? 'A sales target for this outlet and month already exists.'
                : 'A company-wide sales target for this month already exists.');
            return;
        }

        $data = [
            'period'         => $this->period,
            'outlet_id'      => $this->outlet_id ?: null,
            'target_revenue' => $this->target_revenue,
            'target_pax'     => $this->target_pax ?: null,
            'notes'          => $this->notes ?: null,
        ];

        try {
            if ($editing) {
                $editing->update($data);
                session()->flash('success', 'Sales target updated.');
            } else {
                $data['company_id'] = Auth::user()->company_id;
                $data['created_by'] = Auth::id();
                $data['type']       = 'monthly';
                SalesTarget::create($data);
                session()->flash('success', 'Sales target created.');
            }
        } catch (\Illuminate\Database\QueryException $e) {
            // Race or schema drift: surface the unique-index hit as the same
            // friendly error rather than a 500.
            if (($e->errorInfo[0] ?? null) === '23000') {
                $this->addError('period', 'A sales target for this outlet and month already exists.');
                return;
            }
            throw $e;
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        SalesTarget::findOrFail($id)->delete();
        session()->flash('success', 'Sales target deleted.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $targets = SalesTarget::with('outlet')
            ->orderByDesc('period')
            ->paginate(15);

        $outlets = Outlet::where('company_id', Auth::user()->company_id)->orderBy('name')->get();

        return view('livewire.settings.sales-targets', compact('targets', 'outlets'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Sales Targets']);
    }

    private function resetForm(): void
    {
        $this->editingId      = null;
        $this->period         = '';
        $this->outlet_id      = null;
        $this->target_revenue = '';
        $this->target_pax     = '';
        $this->notes          = '';
        $this->resetValidation();
    }
}
