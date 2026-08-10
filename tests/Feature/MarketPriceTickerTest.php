<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\UnitOfMeasure;
use App\Services\Marketing\MarketPriceTicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The price ticker on the marketing home page.
 *
 * It publishes prices derived from customers' purchase records — a decision
 * taken deliberately (2026-08-11) with the numbers in front of us. These tests
 * hold the two things that keep it defensible and the one that keeps it
 * believable: a median rather than an invoice line, no supplier ever named, and
 * no "price move" that is really a change of unit.
 */
class MarketPriceTickerTest extends TestCase
{
    use RefreshDatabase;

    private UnitOfMeasure $kg;
    private IngredientCategory $veg;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->kg = UnitOfMeasure::firstOrCreate(['abbreviation' => 'kg'], ['name' => 'Kilogram', 'type' => 'weight']);
    }

    private function company(string $name): Company
    {
        return Company::create([
            'name' => $name, 'slug' => Str::slug($name) . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
    }

    private function pricedIngredient(Company $c, string $name, string $category, array $prices, ?int $uomId = null): void
    {
        $cat = IngredientCategory::create(['company_id' => $c->id, 'name' => $category]);

        $ing = Ingredient::create([
            'company_id' => $c->id, 'name' => $name,
            'ingredient_category_id' => $cat->id,
            'base_uom_id' => $this->kg->id, 'recipe_uom_id' => $this->kg->id,
        ]);

        foreach ($prices as $date => $cost) {
            DB::table('ingredient_price_history')->insert([
                'ingredient_id'  => $ing->id,
                'cost'           => $cost,
                'uom_id'         => $uomId ?? $this->kg->id,
                'effective_date' => $date,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }

    public function test_it_publishes_the_median_across_companies_not_one_merchants_price(): void
    {
        $a = $this->company('Alpha');
        $b = $this->company('Beta');
        $c = $this->company('Gamma');

        // Same item, three companies, today. The median is 10 — publishing 6 or
        // 30 would hand over a specific merchant's buying.
        foreach ([[$a, 6.00], [$b, 10.00], [$c, 30.00]] as [$company, $today]) {
            $this->pricedIngredient($company, 'TOMATO', 'Produce', [
                '2026-08-01' => $today - 1,
                '2026-08-08' => $today,
            ]);
        }

        $items = app(MarketPriceTicker::class)->items();

        $this->assertCount(1, $items);
        $this->assertSame(10.0, $items->first()['price']);
    }

    public function test_a_change_of_unit_is_not_published_as_a_price_move(): void
    {
        $a = $this->company('Alpha');
        $punnet = UnitOfMeasure::firstOrCreate(['abbreviation' => 'pkt'], ['name' => 'Packet', 'type' => 'count']);

        // The real case that prompted this: blueberries re-costed per punnet
        // rather than per kilo, which read as +912%.
        $this->pricedIngredient($a, 'BLUEBERRY', 'Fruits', ['2026-08-01' => 8.00]);
        $this->pricedIngredient($a, 'BLUEBERRY2', 'Fruits', ['2026-08-08' => 82.50], $punnet->id);

        $this->assertTrue(
            app(MarketPriceTicker::class)->items()->every(fn ($i) => abs($i['change']) <= 60),
            'A ticker is a credibility device; one absurd number spends the credibility of every honest one beside it.'
        );
    }

    public function test_it_names_no_supplier(): void
    {
        $a = $this->company('Alpha');
        $this->pricedIngredient($a, 'ONION', 'Produce', ['2026-08-01' => 4.00, '2026-08-08' => 4.50]);

        foreach (app(MarketPriceTicker::class)->items() as $item) {
            $this->assertArrayNotHasKey('supplier', $item);
            $this->assertArrayNotHasKey('supplier_id', $item);
        }
    }

    public function test_an_item_in_no_recognised_group_is_left_out(): void
    {
        $a = $this->company('Alpha');
        $this->pricedIngredient($a, 'PACKAGING TAPE', 'Consumables', ['2026-08-01' => 4.00, '2026-08-08' => 4.50]);

        $this->assertCount(0, app(MarketPriceTicker::class)->items());
    }

    public function test_one_price_is_not_a_trend(): void
    {
        $a = $this->company('Alpha');
        $this->pricedIngredient($a, 'GINGER', 'Produce', ['2026-08-08' => 6.80]);

        $this->assertCount(0, app(MarketPriceTicker::class)->items());
    }
}
