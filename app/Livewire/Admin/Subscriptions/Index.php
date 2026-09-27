<?php

namespace App\Livewire\Admin\Subscriptions;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $planFilter = '';

    // Create / edit modal
    public bool    $showModal = false;
    public ?int    $editingId = null;
    public ?int    $sub_company_id = null;
    public ?int    $sub_plan_id = null;
    public string  $sub_status = Subscription::STATUS_TRIALING;
    public string  $sub_billing_cycle = 'monthly';
    public ?string $sub_trial_ends_at = null;
    public ?string $sub_period_start = null;
    public ?string $sub_period_end = null;

    /** Add-ons, keyed by module: ['on' => bool, 'quantity' => int, 'unit_price' => float]. */
    public array   $sub_addons = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetSubForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $sub = Subscription::findOrFail($id);

        $this->editingId         = $sub->id;
        $this->sub_company_id    = $sub->company_id;
        $this->sub_plan_id       = $sub->plan_id;
        $this->sub_status        = $sub->status;
        $this->sub_billing_cycle = $sub->billing_cycle ?? 'monthly';
        $this->sub_trial_ends_at = $sub->trial_ends_at?->format('Y-m-d');
        $this->sub_period_start  = $sub->current_period_start?->format('Y-m-d');
        $this->sub_period_end    = $sub->current_period_end?->format('Y-m-d');
        $this->sub_addons        = $this->blankAddons();
        foreach ($sub->addons()->current()->get() as $addon) {
            $this->sub_addons[$addon->module] = [
                'on' => true, 'quantity' => $addon->quantity, 'unit_price' => (float) $addon->unit_price,
            ];
        }
        $this->showModal = true;
    }

    public function saveSubscription(): void
    {
        $this->validate([
            'sub_company_id'    => ['required', Rule::exists('companies', 'id')],
            'sub_plan_id'       => ['required', Rule::exists('plans', 'id')],
            'sub_status'        => ['required', Rule::in([
                Subscription::STATUS_TRIALING, Subscription::STATUS_ACTIVE,
                Subscription::STATUS_PAST_DUE, Subscription::STATUS_CANCELLED,
                Subscription::STATUS_EXPIRED,
            ])],
            'sub_billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
            'sub_trial_ends_at' => ['nullable', 'date'],
            'sub_period_start'  => ['nullable', 'date'],
            'sub_period_end'    => ['nullable', 'date', 'after_or_equal:sub_period_start'],
        ]);

        // One live subscription per company — block a second on create
        if (! $this->editingId) {
            $hasLive = Subscription::where('company_id', $this->sub_company_id)
                ->whereIn('status', [Subscription::STATUS_TRIALING, Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE])
                ->exists();
            if ($hasLive && in_array($this->sub_status, [Subscription::STATUS_TRIALING, Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE])) {
                $this->addError('sub_company_id', 'This company already has a live subscription. Edit it instead.');
                return;
            }
        }

        $data = [
            'company_id'           => $this->sub_company_id,
            'plan_id'              => $this->sub_plan_id,
            'status'               => $this->sub_status,
            'billing_cycle'        => $this->sub_billing_cycle,
            'trial_ends_at'        => $this->sub_trial_ends_at ? Carbon::parse($this->sub_trial_ends_at)->endOfDay() : null,
            'current_period_start' => $this->sub_period_start ? Carbon::parse($this->sub_period_start)->startOfDay() : null,
            'current_period_end'   => $this->sub_period_end ? Carbon::parse($this->sub_period_end)->endOfDay() : null,
            'cancelled_at'         => $this->sub_status === Subscription::STATUS_CANCELLED ? now() : null,
        ];

        $addons = collect($this->sub_addons)->filter(fn ($row) => ! empty($row['on']))->all();

        try {
            $sub = DB::transaction(fn () => $this->persist($data, $addons));
        } catch (ValidationException $e) {
            $this->addError('sub_addons', $e->errors()['addons'][0] ?? $e->getMessage());
            return;
        }

        $msg = $this->editingId
            ? "Subscription updated for {$sub->companyName()}."
            : "Subscription created for {$sub->companyName()}.";

        $this->syncCompanyTrial($sub->fresh());

        $this->closeModal();
        session()->flash('success', $msg);
    }

    /** Save the subscription and its add-ons together; a refused add-on rolls both back. */
    private function persist(array $data, array $addons): Subscription
    {
        if ($this->editingId) {
            $sub = Subscription::findOrFail($this->editingId);
            // Keep an existing cancelled_at if it was already cancelled
            if ($sub->status === Subscription::STATUS_CANCELLED && $this->sub_status === Subscription::STATUS_CANCELLED) {
                $data['cancelled_at'] = $sub->cancelled_at;
            }
            $sub->update($data);
        } else {
            $sub = Subscription::create($data);
        }

        app(SubscriptionService::class)->syncAddons($sub->fresh('plan'), $addons);

        return $sub;
    }

    /** One row per sellable module, off, at list price. */
    private function blankAddons(): array
    {
        return collect(config('modules.catalogue'))
            ->filter(fn ($m) => in_array($m['kind'], ['addon', 'metered'], true))
            ->map(fn ($m) => ['on' => false, 'quantity' => $m['min_quantity'] ?? 1, 'unit_price' => (float) $m['price']])
            ->all();
    }

    public function activateSubscription(int $id): void
    {
        $sub = Subscription::findOrFail($id);

        if ($sub->status === Subscription::STATUS_ACTIVE) {
            session()->flash('error', 'Subscription is already active.');
            return;
        }

        app(SubscriptionService::class)->activate($sub);
        session()->flash('success', "Subscription activated for {$sub->companyName()} (period runs to {$sub->fresh()->current_period_end?->format('d M Y')}).");
    }

    public function deleteSubscription(int $id): void
    {
        $sub = Subscription::findOrFail($id);
        $companyName = $sub->companyName();

        $wasLive = in_array($sub->status, [Subscription::STATUS_TRIALING, Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE]);
        // forceDelete, not delete: subscriptions became soft-deletable so they
        // could follow a deleted company, but THIS button is the deliberate
        // "remove the record" action and its confirm says it cannot be undone.
        $sub->forceDelete();

        // No subscriptions left = grandfathered (unlimited); clear stale trial flag
        if ($wasLive) {
            $sub->company?->update(['trial_ends_at' => null]);
        }

        session()->flash('success', "Subscription deleted for {$companyName}.");
    }

    public function extendTrial(int $id, int $days = 7): void
    {
        $sub = Subscription::findOrFail($id);

        if (!$sub->isTrial()) {
            session()->flash('error', 'Only trial subscriptions can be extended.');
            return;
        }

        $newEnd = $sub->trial_ends_at->addDays($days);
        $sub->update([
            'trial_ends_at'      => $newEnd,
            'current_period_end' => $newEnd,
        ]);
        $sub->company?->update(['trial_ends_at' => $newEnd]);

        session()->flash('success', "Trial extended by {$days} days for {$sub->companyName()}.");
    }

    public function cancelSubscription(int $id): void
    {
        $sub = Subscription::findOrFail($id);
        app(SubscriptionService::class)->cancel($sub);
        session()->flash('success', "Subscription cancelled for {$sub->companyName()}.");
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetSubForm();
    }

    public function render()
    {
        $query = Subscription::with(['company', 'plan'])
            ->latest();

        if ($this->search) {
            $query->whereHas('company', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->planFilter) {
            $query->where('plan_id', $this->planFilter);
        }

        $subscriptions = $query->paginate(20);
        $plans = Plan::ordered()->get();

        // Companies eligible for a new subscription (no live one) — for the create modal
        $availableCompanies = Company::whereDoesntHave('subscriptions', fn ($q) =>
                $q->whereIn('status', [Subscription::STATUS_TRIALING, Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE]))
            ->orderBy('name')->get(['id', 'name']);

        return view('livewire.admin.subscriptions.index', compact('subscriptions', 'plans', 'availableCompanies'))
            ->layout('layouts.app', ['title' => 'Subscriptions']);
    }

    /** Company.trial_ends_at mirrors the subscription's trial state (see SubscriptionService). */
    private function syncCompanyTrial(Subscription $sub): void
    {
        $sub->company?->update([
            'trial_ends_at' => $sub->status === Subscription::STATUS_TRIALING ? $sub->trial_ends_at : null,
        ]);
    }

    private function resetSubForm(): void
    {
        $this->editingId = null;
        $this->sub_company_id = null;
        $this->sub_plan_id = null;
        $this->sub_status = Subscription::STATUS_TRIALING;
        $this->sub_billing_cycle = 'monthly';
        $this->sub_trial_ends_at = null;
        $this->sub_period_start = null;
        $this->sub_period_end = null;
        $this->sub_addons = $this->blankAddons();
        $this->resetValidation();
    }
}
