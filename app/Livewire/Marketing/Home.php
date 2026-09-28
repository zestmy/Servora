<?php

namespace App\Livewire\Marketing;

use App\Models\LandingPage;
use App\Models\Plan;
use App\Services\Billing\CurrencyResolver;
use App\Support\Marketing\HomeCopy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Livewire\Component;

/**
 * The marketing home page, and every country landing page — the same page
 * in another language (App\Models\LandingPage, Admin › Country Pages).
 *
 *   /         English. A guest whose country has a published page set to
 *             auto-redirect is sent to it, unless they chose English
 *             (?lang=en, remembered in a cookie for a year).
 *   /{slug}   That country's page. Unpublished pages are visible to system
 *             admins only, as a preview.
 *
 * Prices are in the visitor's own currency either way (CurrencyResolver):
 * the language follows the page, the money follows the visitor.
 */
class Home extends Component
{
    public ?int $landingId = null;
    public string $currency = 'MYR';

    public function mount(CurrencyResolver $resolver, ?string $landing = null): void
    {
        $request = request();
        $country = $resolver->countryFor($request);
        $this->currency = $resolver->forCountry($country);

        if ($landing !== null) {
            $page = LandingPage::where('slug', $landing)->first();
            abort_unless($page && ($page->is_published || Auth::user()?->isSystemRole()), 404);
            $this->landingId = $page->id;

            return;
        }

        if (Auth::check()) {
            $this->redirect(route('dashboard'), navigate: true);
            return;
        }

        if ($request->query('lang') === 'en') {
            Cookie::queue('mk_lang', 'en', 60 * 24 * 365);
            return;
        }

        if ($request->cookie('mk_lang') !== 'en'
            && ($page = LandingPage::forCountry($country)) && $page->auto_redirect) {
            $this->redirect(route('marketing.landing', $page->slug));
        }
    }

    public function render(CurrencyResolver $resolver)
    {
        $trialDays = Plan::active()->ordered()->value('trial_days') ?? 30;

        // Cached for an hour inside the service: a marketing page is the most
        // requested URL in the product and this is the only query on it that
        // touches every company's price history.
        $tickerItems = app(\App\Services\Marketing\MarketPriceTicker::class)->items();

        // The same rows the pricing page and checkout read, so the teaser on
        // the home page cannot quote a price checkout will not charge.
        $plans = Plan::publiclyVisible()->ordered()->get();

        $landing = $this->landingId ? LandingPage::find($this->landingId) : null;
        $copy    = $landing ? $landing->copy() : HomeCopy::defaults();
        $book    = $resolver->book($this->currency);

        return view('livewire.marketing.home', compact('trialDays', 'tickerItems', 'plans', 'copy', 'book', 'landing'))
            ->layout('layouts.marketing', [
                'title'       => $copy['meta.title'],
                'description' => $copy['meta.description'],
                'flush'       => true,
                'copy'        => $landing ? $copy : null,
                'landing'     => $landing,
            ]);
    }
}
