<?php

namespace App\Livewire\Marketing;

use App\Mail\Marketing\RecipeCostPdfMail;
use App\Services\Marketing\PublicIngredientPrices;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * A free recipe costing tool, open to anyone.
 *
 * The point is to be useful before asking for anything: build a recipe, see
 * what it costs and what it should sell for, with no account. The email is
 * asked for only at the PDF, which is the moment a visitor has decided the
 * thing is worth keeping.
 *
 * Prices come from PublicIngredientPrices, which is the single place deciding
 * what may leave the tenant boundary — see that class for the bargain being
 * struck. Nothing here queries ingredients directly.
 */
class RecipeCostCalculator extends Component
{
    public string $recipeName = '';

    public string $search = '';

    /** @var array<int, array{slug: string, name: string, unit: ?string, price: float, qty: float}> */
    public array $lines = [];
    /*
     * Untyped on purpose, all through this form.
     *
     * A `public float` on a property bound to a number input is a promise the
     * BROWSER cannot keep: clearing the box to retype it sends "", Livewire
     * assigns that to the typed property, PHP refuses, and Livewire's recovery
     * is to UNSET the property — so the next read inside figures() lands on
     * __get and throws PropertyNotFoundException. A 500, in the middle of
     * filling in a form, from the most ordinary edit there is.
     *
     * Reported on the salary calculator on 2026-08-11 and reproduced on every
     * float field in these tools. Ints survive it (Livewire casts "" to 0);
     * floats do not. Every read below already casts with (float), so the type
     * was buying nothing that the casts were not already providing.
     */

    public $portions = 1;

    /** What share of the menu price the food is meant to be. */
    public $targetFoodCostPct = 30;

    public string $email = '';

    public string $notice = '';

    public string $error = '';

    /** PDFs allowed per address and per network in a day. */
    private const PDFS_PER_DAY = 3;

    /**
     * A day, in seconds, written out.
     *
     * It was `now()->addDay()->diffInSeconds(now())`, which is NEGATIVE — the
     * difference is measured from the future date back to now — so every hit
     * expired the moment it was written and the daily limit never applied. A
     * test caught it; nothing on the screen would have.
     */
    private const WINDOW_SECONDS = 86400;

    public function addLine(string $slug): void
    {
        $item = app(PublicIngredientPrices::class)->find($slug);

        if (! $item) {
            return;
        }

        foreach ($this->lines as $line) {
            if ($line['slug'] === $slug) {
                $this->search = '';

                return;
            }
        }

        $this->lines[] = $item + ['qty' => 1.0];
        $this->search  = '';
        $this->error   = '';
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    /** @return array<string, float> */
    public function totals(): array
    {
        $cost = collect($this->lines)->sum(fn (array $l) => (float) $l['price'] * (float) $l['qty']);

        $portions = max(1.0, (float) $this->portions);
        $target   = min(99.0, max(1.0, (float) $this->targetFoodCostPct));

        $perPortion = $cost / $portions;

        return [
            'cost'        => round($cost, 2),
            'per_portion' => round($perPortion, 2),
            // What the dish has to sell for to hit the food cost they asked for.
            'menu_price'  => round($perPortion / ($target / 100), 2),
        ];
    }

    /**
     * Email the costing as a PDF.
     *
     * Limited per ADDRESS and per NETWORK. The address alone is trivially
     * changed; the IP alone punishes a whole office for one person's third
     * download. Both, and the tighter of the two wins — which is the point of a
     * free tool's limit: it is about cost, not enforcement.
     */
    public function emailPdf(): void
    {
        $this->error  = '';
        $this->notice = '';

        if (! $this->lines) {
            $this->error = 'Add an ingredient or two first.';

            return;
        }

        if (! filter_var(trim($this->email), FILTER_VALIDATE_EMAIL)) {
            $this->error = 'Enter an email address we can send it to.';

            return;
        }

        $email = mb_strtolower(trim($this->email));
        $keys  = [
            'recipe-pdf:email:' . $email,
            'recipe-pdf:ip:' . (request()->ip() ?: 'unknown'),
        ];

        foreach ($keys as $key) {
            if (RateLimiter::tooManyAttempts($key, self::PDFS_PER_DAY)) {
                $this->error = 'That is ' . self::PDFS_PER_DAY . ' costings today. Try again tomorrow, '
                    . 'or start a free trial for as many as you like.';

                return;
            }
        }

        foreach ($keys as $key) {
            RateLimiter::hit($key, self::WINDOW_SECONDS);
        }

        Mail::to($email)->send(new RecipeCostPdfMail(
            $this->recipeName ?: 'Untitled recipe',
            $this->lines,
            $this->totals(),
            (float) $this->portions,
            (float) $this->targetFoodCostPct,
        ));

        $this->notice = 'Sent to ' . $email . '. Check your inbox in a moment.';
    }

    public function render()
    {
        return view('livewire.marketing.recipe-cost-calculator', [
            'results' => app(PublicIngredientPrices::class)->search($this->search),
            'totals'  => $this->totals(),
        ])->layout('layouts.marketing', [
            'title' => 'Free Recipe Cost Calculator',
        ]);
    }
}
