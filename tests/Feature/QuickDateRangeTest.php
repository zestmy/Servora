<?php

namespace Tests\Feature;

use App\Livewire\Audit\Index as Audit;
use App\Livewire\Inventory\Index as StockManagement;
use App\Livewire\Purchasing\Index as Purchasing;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The named date ranges, and the fact that there is only one definition of them.
 *
 * Audit grew the first, Stock Management the second, and Purchasing would have
 * been the third — three implementations of "last week" is three chances for
 * one of them to mean a different seven days than the others, on screens people
 * read side by side. They come from HasQuickDateRanges now.
 */
class QuickDateRangeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::create([
            'name' => 'Range Co', 'slug' => Str::slug('Range Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $outlet = Outlet::create([
            'company_id' => $company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $company->id, 'outlet_id' => $outlet->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$company->id]);
        $this->user->outlets()->syncWithoutDetaching([$outlet->id]);

        $this->actingAs($this->user);
    }

    /** @return array<int, class-string> every screen offering the ranges */
    public static function screens(): array
    {
        return [
            'stock management' => [StockManagement::class],
            'purchasing'       => [Purchasing::class],
            'audit'            => [Audit::class],
        ];
    }

    /**
     * @dataProvider screens
     */
    public function test_last_week_is_monday_to_sunday_on_every_screen_that_offers_it(string $screen): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-08-12'));   // a Wednesday

        Livewire::actingAs($this->user)->test($screen)
            ->call('setQuickRange', 'last_week')
            ->assertSet('dateFrom', '2026-08-03')
            ->assertSet('dateTo', '2026-08-09');

        $this->travelBack();
    }

    /**
     * @dataProvider screens
     */
    public function test_typing_a_date_drops_the_named_range_on_every_screen(string $screen): void
    {
        Livewire::actingAs($this->user)->test($screen)
            ->call('setQuickRange', 'last_7')
            ->set('dateFrom', today()->subDays(3)->toDateString())
            ->assertSet('quickRange', '');
    }

    /**
     * Audit opens on a week, the others on a month — a log answers "what just
     * happened" and a month of every change in the company is a wall. The trait
     * carries no default of its own for exactly this reason.
     */
    public function test_a_screen_may_keep_its_own_idea_of_recently(): void
    {
        Livewire::actingAs($this->user)->test(Audit::class)->assertSet('quickRange', 'last_7');
        Livewire::actingAs($this->user)->test(StockManagement::class)->assertSet('quickRange', 'last_30');
    }

    /**
     * @dataProvider screens
     */
    public function test_every_screen_offers_the_same_named_ranges(string $screen): void
    {
        $this->assertSame(
            ['today', 'last_7', 'last_week', 'last_30', 'this_month', 'last_month', 'all'],
            array_keys($screen::quickRangeOptions()),
            'Two screens offering different ranges is how they come to mean different things.'
        );
    }
}
