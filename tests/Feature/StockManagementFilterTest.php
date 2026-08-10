<?php

namespace Tests\Feature;

use App\Livewire\Inventory\Index as StockManagement;
use App\Models\Company;
use App\Models\Department;
use App\Models\Outlet;
use App\Models\PurchaseCapture;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WastageRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Stock Management: the cards answer the question the filters are asking.
 *
 * They did not. The stats were hardcoded to whereMonth(now()) and ignored the
 * date range, the outlet, the department and the search — so narrowing the
 * table to last week left the cards reporting the month, two different answers
 * on one screen. And the department filter existed on the Wastage tab alone
 * although four of the five tables carry the column and every form fills it,
 * while supplier was stored and used on Purchases and filterable nowhere.
 *
 * Both come from the same fix: one filtered query per tab that the table and
 * the cards are both built from. These tests hold that shape — if the stats
 * ever stop agreeing with the list, they fail.
 */
class StockManagementFilterTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private Department $kitchen;
    private Department $bar;
    private Supplier $acme;
    private Supplier $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name'      => 'Stock Filter Co',
            'slug'      => Str::slug('Stock Filter Co') . '-' . uniqid(),
            'currency'  => 'MYR',
            'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id,
            'name'       => 'Main',
            'code'       => 'MAIN',
            'is_active'  => true,
        ]);

        $this->user = User::factory()->create([
            'company_id'           => $this->company->id,
            'outlet_id'            => $this->outlet->id,
            'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        $this->kitchen = Department::create(['company_id' => $this->company->id, 'name' => 'Kitchen']);
        $this->bar     = Department::create(['company_id' => $this->company->id, 'name' => 'Bar']);

        $this->acme  = Supplier::create(['company_id' => $this->company->id, 'name' => 'ACME']);
        $this->other = Supplier::create(['company_id' => $this->company->id, 'name' => 'OTHER']);

        $this->actingAs($this->user);
    }

    private function purchase(string $date, float $amount, Department $dept, Supplier $supplier): PurchaseCapture
    {
        return PurchaseCapture::create([
            'company_id'    => $this->company->id,
            'outlet_id'     => $this->outlet->id,
            'department_id' => $dept->id,
            'supplier_id'   => $supplier->id,
            'supplier_name' => $supplier->name,
            'purchase_date' => $date,
            'amount'        => $amount,
        ]);
    }

    private function wastage(string $date, float $cost, Department $dept): WastageRecord
    {
        return WastageRecord::create([
            'company_id'    => $this->company->id,
            'outlet_id'     => $this->outlet->id,
            'department_id' => $dept->id,
            'wastage_date'  => $date,
            'total_cost'    => $cost,
        ]);
    }

    private function screen()
    {
        return Livewire::actingAs($this->user)->test(StockManagement::class);
    }

    public function test_the_cards_follow_the_date_range_rather_than_the_calendar_month(): void
    {
        $this->purchase(today()->toDateString(), 100, $this->kitchen, $this->acme);
        $this->purchase(today()->subDays(20)->toDateString(), 900, $this->kitchen, $this->acme);

        // Read off the rendered page: the cards are what the person sees.
        $html = $this->screen()
            ->set('tab', 'purchases')
            ->call('setQuickRange', 'today')
            ->html();

        $this->assertStringContainsString('RM 100.00', $html);
        $this->assertStringNotContainsString(
            'RM 1,000.00',
            $html,
            'Today was selected; a card showing the whole month is the bug this fixes.'
        );
    }

    public function test_purchases_can_be_filtered_by_department(): void
    {
        $this->purchase(today()->toDateString(), 100, $this->kitchen, $this->acme);
        $this->purchase(today()->toDateString(), 250, $this->bar, $this->acme);

        $html = $this->screen()
            ->set('tab', 'purchases')
            ->call('setQuickRange', 'today')
            ->set('departmentFilter', (string) $this->bar->id)
            ->html();

        $this->assertStringContainsString('RM 250.00', $html);
        $this->assertStringNotContainsString('RM 350.00', $html, 'The total must follow the department filter.');
    }

    public function test_purchases_can_be_filtered_by_supplier(): void
    {
        $this->purchase(today()->toDateString(), 100, $this->kitchen, $this->acme);
        $this->purchase(today()->toDateString(), 250, $this->kitchen, $this->other);

        $html = $this->screen()
            ->set('tab', 'purchases')
            ->call('setQuickRange', 'today')
            ->set('supplierFilter', (string) $this->acme->id)
            ->html();

        $this->assertStringContainsString('RM 100.00', $html);
        $this->assertStringNotContainsString('RM 350.00', $html);
    }

    public function test_wastage_keeps_its_department_filter(): void
    {
        $this->wastage(today()->toDateString(), 40, $this->kitchen);
        $this->wastage(today()->toDateString(), 60, $this->bar);

        $html = $this->screen()
            ->set('tab', 'wastage')
            ->call('setQuickRange', 'today')
            ->set('departmentFilter', (string) $this->kitchen->id)
            ->html();

        $this->assertStringContainsString('RM 40.00', $html);
        $this->assertStringNotContainsString('RM 100.00', $html);
    }

    /**
     * A number alone says nothing: RM 400 of wastage is good or terrible
     * depending on the week before.
     */
    public function test_the_previous_period_is_the_same_window_shifted_back(): void
    {
        // Inside the last 7 days.
        $this->purchase(today()->toDateString(), 100, $this->kitchen, $this->acme);
        // Inside the 7 days before that.
        $this->purchase(today()->subDays(8)->toDateString(), 700, $this->kitchen, $this->acme);
        // Older than both windows — must not be counted either way.
        $this->purchase(today()->subDays(60)->toDateString(), 5000, $this->kitchen, $this->acme);

        $html = $this->screen()
            ->set('tab', 'purchases')
            ->call('setQuickRange', 'last_7')
            ->html();

        $this->assertStringContainsString('RM 100.00', $html, 'The current window.');
        $this->assertStringContainsString('RM 700.00', $html, 'The window before it.');
        $this->assertStringNotContainsString('5,000.00', $html, 'Neither window reaches back that far.');
    }

    /**
     * A supplier filter surviving onto Wastage would be a filter doing nothing
     * while looking like it is doing something.
     */
    public function test_filters_that_cannot_apply_to_the_new_tab_are_cleared(): void
    {
        $screen = $this->screen()
            ->set('tab', 'purchases')
            ->set('supplierFilter', (string) $this->acme->id)
            ->set('departmentFilter', (string) $this->kitchen->id)
            ->set('tab', 'wastage');

        $screen->assertSet('supplierFilter', '');
        $screen->assertSet('departmentFilter', (string) $this->kitchen->id);
    }

    /**
     * "Last week" is the calendar week just gone, Monday to Sunday — not the
     * rolling seven days that sits next to it. A shift landing on the wrong
     * side of that boundary is a real argument, so the days are pinned here.
     */
    public function test_last_week_runs_monday_to_sunday_of_the_week_before(): void
    {
        // A fixed Wednesday, so the assertion does not move with the calendar.
        $this->travelTo(\Carbon\Carbon::parse('2026-08-12'));

        $this->screen()
            ->call('setQuickRange', 'last_week')
            ->assertSet('dateFrom', '2026-08-03')   // Monday
            ->assertSet('dateTo', '2026-08-09');    // Sunday

        $this->travelBack();
    }

    public function test_last_week_is_not_the_same_as_the_last_seven_days(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-08-12'));

        // Monday of last week. Inside "last week" (3rd–9th) and outside the
        // rolling seven days, which from Wednesday the 12th reach back only to
        // the 6th — the two ranges overlap for most of the week, so the date
        // has to be chosen from the part where they do not.
        $this->purchase('2026-08-03', 120, $this->kitchen, $this->acme);

        $lastWeek = $this->screen()->set('tab', 'purchases')->call('setQuickRange', 'last_week')->html();
        $rolling  = $this->screen()->set('tab', 'purchases')->call('setQuickRange', 'last_7')->html();

        $this->assertStringContainsString('RM 120.00', $lastWeek);

        // Under the rolling range the purchase is not in the window — it is in
        // the window BEFORE it, so it shows up as the comparison rather than as
        // the total. Asserting it is absent entirely would be asserting the
        // comparison does not work.
        $this->assertStringContainsString('RM 0.00', $rolling);
        $this->assertStringContainsString('Previous period: RM 120.00', $rolling);

        $this->travelBack();
    }

    public function test_typing_a_date_by_hand_drops_the_named_range(): void
    {
        $this->screen()
            ->call('setQuickRange', 'last_7')
            ->set('dateFrom', today()->subDays(3)->toDateString())
            ->assertSet('quickRange', '');
    }
}
