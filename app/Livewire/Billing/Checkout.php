<?php

namespace App\Livewire\Billing;

use App\Models\CentralKitchen;
use App\Models\Employee;
use App\Models\Plan;
use App\Services\Billing\CheckoutService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Build and pay for a per-outlet plan (docs/pricing-model.md): the suite,
 * how many outlets, the add-ons, and the cycle. Prices come from
 * Billing\PriceCalculator; this screen only collects the choices and shows
 * the quote. The payment carries the configuration, and the CHIP-IN webhook
 * applies it — nothing here changes the subscription directly.
 */
class Checkout extends Component
{
    public ?Plan $selectedPlan = null;

    public string $billing_cycle = 'monthly';
    public int $outlets = 1;

    /** Flat add-ons, module => ticked. */
    public array $addons = [];

    public bool $hr = false;
    public int $hrEmployees = 10;

    public bool $kitchen = false;
    public int $kitchens = 1;

    /** A locked link's module, preticked (?unlock=labels). */
    #[Url]
    public ?string $unlock = null;

    public function mount(string $planSlug): void
    {
        $plan = Plan::where('slug', $planSlug)->active()->first();

        // Only the suites are bought here; Free is the absence of a purchase,
        // and the flat legacy plans are no longer sold.
        if (! $plan || ! in_array('basic', (array) $plan->modules, true)) {
            $this->redirect(route('billing.index'), navigate: true);
            return;
        }

        $this->selectedPlan = $plan;
        $company = Auth::user()->company;
        $service = app(CheckoutService::class);

        $this->outlets = $service->minimumOutlets($company);
        $this->hrEmployees = max(10, Employee::where('company_id', $company->id)->where('is_active', true)->count());
        $this->kitchens = max(1, CentralKitchen::where('company_id', $company->id)->count());

        foreach (config('modules.catalogue') as $key => $m) {
            if ($m['kind'] === 'addon') {
                $this->addons[$key] = false;
            }
        }

        // Keep what the company already has, so a change starts from today.
        $live = app(SubscriptionService::class)->getActiveSubscription($company);
        if ($live && ! $live->isTrial()) {
            $this->billing_cycle = $live->billing_cycle ?: 'monthly';
            $this->outlets = max($this->outlets, (int) $live->outlet_quantity);
            foreach ($live->addons()->current()->get() as $addon) {
                match (true) {
                    $addon->module === 'hr'              => [$this->hr, $this->hrEmployees] = [true, $addon->quantity],
                    $addon->module === 'central_kitchen' => [$this->kitchen, $this->kitchens] = [true, $addon->quantity],
                    array_key_exists($addon->module, $this->addons) => $this->addons[$addon->module] = true,
                    default => null,
                };
            }
        }

        match ($this->unlock) {
            'hr'              => $this->hr = true,
            'central_kitchen' => $this->kitchen = true,
            default           => $this->unlock && array_key_exists($this->unlock, $this->addons)
                ? $this->addons[$this->unlock] = true : null,
        };
    }

    /** @return array<string, int> module => quantity, what PriceCalculator takes */
    private function chosen(): array
    {
        $chosen = collect($this->addons)->filter()->map(fn () => 1)->all();

        if ($this->hr) {
            $chosen['hr'] = max(1, $this->hrEmployees);
        }
        if ($this->kitchen) {
            $chosen['central_kitchen'] = max(1, $this->kitchens);
        }

        return $chosen;
    }

    public function pay(): void
    {
        // Buying and redeeming change what the whole company pays for. The route
        // carries can:users.manage too; this keeps the action safe on its own.
        abort_unless(Auth::user()?->canDo('users.manage'), 403);

        $this->validate([
            'billing_cycle' => 'required|in:monthly,yearly',
            'outlets'       => 'required|integer|min:1',
            'hrEmployees'   => 'integer|min:1',
            'kitchens'      => 'integer|min:1',
        ]);

        $result = app(CheckoutService::class)->start(
            Auth::user()->company, $this->selectedPlan, $this->outlets, $this->chosen(), $this->billing_cycle,
        );

        if (isset($result['redirect'])) {
            $this->redirect($result['redirect']);
            return;
        }

        if (isset($result['scheduled'])) {
            session()->flash('success', 'Done. Your new plan starts on '.$result['scheduled']->format('d M Y')
                .', when the period you have paid for ends.');
            $this->redirect(route('billing.index'), navigate: true);
            return;
        }

        $this->addError('checkout', $result['error']);
    }

    public function render()
    {
        if (! $this->selectedPlan) {
            return '<div></div>'; // mount() is redirecting
        }

        $company = Auth::user()->company;
        $service = app(CheckoutService::class);
        $book    = $service->book($company);
        $quote   = $service->quote($company, $this->selectedPlan, $this->outlets, $this->chosen(), $this->billing_cycle, $book);

        return view('livewire.billing.checkout', [
            'quote'      => $quote,
            'book'       => $book,
            'fx'         => $quote->ok() ? $service->charge($quote, $book->fx) : null,
            'minOutlets' => $service->minimumOutlets($company),
            'blocked'    => $service->blockedReason($company),
            'catalogue'  => config('modules.catalogue'),
            'isFull'     => in_array('labels', (array) $this->selectedPlan->modules, true),
            'cap'        => (int) config('modules.addon_cap_on_basic'),
        ])->layout('layouts.app', ['title' => 'Checkout — '.$this->selectedPlan->name]);
    }
}
