<?php

namespace App\Livewire\Admin;

use App\Models\Coupon;
use App\Models\Plan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Coupons extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $code        = '';
    public string $description = '';
    public ?int   $plan_id     = null;
    public string $grant_type  = 'months';
    public ?int   $grant_value = 3;
    public ?int   $max_redemptions = null;
    public ?string $expires_at = null;
    public bool   $is_active   = true;

    public string $search = '';

    protected function rules(): array
    {
        return [
            'code'            => ['required', 'string', 'max:64', Rule::unique('coupons', 'code')->ignore($this->editingId)],
            'description'     => 'nullable|string|max:255',
            'plan_id'         => 'nullable|exists:plans,id',
            'grant_type'      => 'required|in:days,months,lifetime',
            'grant_value'     => 'nullable|integer|min:1',
            'max_redemptions' => 'nullable|integer|min:1',
            'expires_at'      => 'nullable|date',
        ];
    }

    public function updatedSearch(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->code = strtoupper(Str::random(10));
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $c = Coupon::findOrFail($id);
        $this->editingId = $c->id;
        $this->code = $c->code;
        $this->description = $c->description ?? '';
        $this->plan_id = $c->plan_id;
        $this->grant_type = $c->grant_type;
        $this->grant_value = $c->grant_value;
        $this->max_redemptions = $c->max_redemptions;
        $this->expires_at = $c->expires_at?->format('Y-m-d');
        $this->is_active = $c->is_active;
        $this->showModal = true;
    }

    public function generateCode(): void
    {
        $this->code = strtoupper(Str::random(10));
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'code'            => strtoupper(trim($this->code)),
            'description'     => $this->description ?: null,
            'plan_id'         => $this->plan_id,
            'grant_type'      => $this->grant_type,
            'grant_value'     => $this->grant_type === 'lifetime' ? null : $this->grant_value,
            'max_redemptions' => $this->max_redemptions ?: null,
            'expires_at'      => $this->expires_at ?: null,
            'is_active'       => $this->is_active,
        ];

        if ($this->editingId) {
            Coupon::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Coupon updated.');
        } else {
            $data['created_by'] = Auth::id();
            Coupon::create($data);
            session()->flash('success', 'Coupon created.');
        }

        $this->closeModal();
    }

    public function toggleActive(int $id): void
    {
        $c = Coupon::findOrFail($id);
        $c->update(['is_active' => ! $c->is_active]);
    }

    public function delete(int $id): void
    {
        Coupon::findOrFail($id)->delete();
        session()->flash('success', 'Coupon deleted.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->code = '';
        $this->description = '';
        $this->plan_id = null;
        $this->grant_type = 'months';
        $this->grant_value = 3;
        $this->max_redemptions = null;
        $this->expires_at = null;
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render()
    {
        $coupons = Coupon::with(['plan', 'creator'])
            ->when($this->search, fn ($q) =>
                $q->where('code', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%')
            )
            ->orderByDesc('id')
            ->paginate(20);

        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();

        return view('livewire.admin.coupons', compact('coupons', 'plans'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Coupons']);
    }
}
