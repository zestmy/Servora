<?php

namespace Tests\Feature;

use App\Livewire\Marketing\RecipeCostCalculator;
use App\Mail\Marketing\RecipeCostPdfMail;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\UnitOfMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The free public recipe costing tool.
 *
 * It is open to anyone, so the tests are mostly about the edges a public tool
 * has and an internal screen does not: the arithmetic a visitor will check
 * against their own invoice, and the limit that stops one visitor generating
 * PDFs all afternoon.
 */
class RecipeCostCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Mail::fake();

        $kg = UnitOfMeasure::firstOrCreate(['abbreviation' => 'kg'], ['name' => 'Kilogram', 'type' => 'weight']);

        $company = Company::create([
            'name' => 'Price Co', 'slug' => Str::slug('Price Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        foreach ([['CHICKEN BREAST', 18.00], ['ONION', 4.00]] as [$name, $cost]) {
            $ing = Ingredient::create([
                'company_id' => $company->id, 'name' => $name,
                'base_uom_id' => $kg->id, 'recipe_uom_id' => $kg->id,
            ]);

            DB::table('ingredient_price_history')->insert([
                'ingredient_id' => $ing->id, 'cost' => $cost, 'uom_id' => $kg->id,
                'effective_date' => '2026-08-01', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function slugFor(string $name): string
    {
        return app(\App\Services\Marketing\PublicIngredientPrices::class)
            ->search($name)->first()['slug'];
    }

    public function test_it_costs_a_recipe_and_suggests_a_menu_price(): void
    {
        $screen = Livewire::test(RecipeCostCalculator::class)
            ->call('addLine', $this->slugFor('CHICKEN'))
            ->set('lines.0.qty', 0.5)         // RM 9.00
            ->call('addLine', $this->slugFor('ONION'))
            ->set('lines.1.qty', 0.25)        // RM 1.00
            ->set('portions', 2)
            ->set('targetFoodCostPct', 25);

        $totals = $screen->get('lines');

        $this->assertCount(2, $totals);

        $screen->assertSee('RM 10.00')   // cost to make
            ->assertSee('RM 5.00')       // per portion
            ->assertSee('RM 20.00');     // 5.00 at 25% food cost
    }

    public function test_the_same_ingredient_is_not_added_twice(): void
    {
        $slug = $this->slugFor('ONION');

        $screen = Livewire::test(RecipeCostCalculator::class)
            ->call('addLine', $slug)
            ->call('addLine', $slug);

        $this->assertCount(1, $screen->get('lines'));
    }

    public function test_a_pdf_needs_an_email_and_at_least_one_ingredient(): void
    {
        Livewire::test(RecipeCostCalculator::class)
            ->set('email', 'chef@example.test')
            ->call('emailPdf')
            ->assertSet('error', 'Add an ingredient or two first.');

        Livewire::test(RecipeCostCalculator::class)
            ->call('addLine', $this->slugFor('ONION'))
            ->set('email', 'not-an-email')
            ->call('emailPdf')
            ->assertSet('error', 'Enter an email address we can send it to.');

        Mail::assertNothingSent();
    }

    public function test_three_pdfs_a_day_and_then_it_stops(): void
    {
        RateLimiter::clear('recipe-pdf:email:chef@example.test');

        for ($i = 0; $i < 3; $i++) {
            Livewire::test(RecipeCostCalculator::class)
                ->call('addLine', $this->slugFor('ONION'))
                ->set('email', 'chef@example.test')
                ->call('emailPdf')
                ->assertSet('error', '');
        }

        Livewire::test(RecipeCostCalculator::class)
            ->call('addLine', $this->slugFor('ONION'))
            ->set('email', 'chef@example.test')
            ->call('emailPdf')
            ->assertSet('notice', '');

        Mail::assertSent(RecipeCostPdfMail::class, 3);
    }

    public function test_the_public_search_never_returns_a_supplier_or_a_company(): void
    {
        foreach (app(\App\Services\Marketing\PublicIngredientPrices::class)->search('ONION') as $item) {
            $this->assertSame(['slug', 'name', 'unit', 'price'], array_keys($item));
        }
    }
}
