<?php

namespace App\Livewire\Marketing;

use App\Models\Plan;
use App\Services\Billing\CurrencyResolver;
use Livewire\Component;

class Pricing extends Component
{
    public string $cycle = 'monthly';

    /** Resolved once, on the first request: a Livewire round-trip has no visitor IP worth asking about. */
    public string $currency = 'MYR';
    public ?string $country = null;

    public function mount(CurrencyResolver $resolver): void
    {
        $this->country  = $resolver->countryFor(request());
        $this->currency = $resolver->forCountry($this->country);
    }

    public function render(CurrencyResolver $resolver)
    {
        $plans = Plan::publiclyVisible()->ordered()->get();

        return view('livewire.marketing.pricing', [
            'plans'   => $plans,
            'book'    => $resolver->book($this->currency),
            'country' => $this->country,
        ])->layout('layouts.marketing', ['title' => 'Pricing', 'flush' => true]);
    }
}
