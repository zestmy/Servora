<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCount;
use App\Models\AssetMovement;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\AssetOnHandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The one piece of arithmetic in the asset module: how much of a thing an
 * outlet has.
 *
 * Every screen in the module reads it through AssetOnHandService, so a rule
 * that is wrong here is wrong on the register, on the count sheet and on the
 * receipt at the same time — which is exactly why it is tested on its own
 * rather than through whichever screen happens to show it.
 */
class AssetOnHandTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $main;
    private Outlet $second;
    private Asset $plate;
    private Asset $knife;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Assets Co', 'slug' => Str::slug('Assets Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->main = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->second = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Second', 'code' => 'SEC', 'is_active' => true,
        ]);

        $piece = UnitOfMeasure::create(['name' => 'Piece', 'abbreviation' => 'pc', 'type' => 'count']);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->main->id,
            'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->main->id, $this->second->id]);

        $this->actingAs($this->user);

        $this->plate = Asset::create([
            'company_id' => $this->company->id, 'name' => 'DINNER PLATE',
            'uom_id' => $piece->id, 'unit_cost' => 12.50, 'is_active' => true,
        ]);

        $this->knife = Asset::create([
            'company_id' => $this->company->id, 'name' => 'CHEF KNIFE',
            'uom_id' => $piece->id, 'unit_cost' => 80, 'is_active' => true,
        ]);
    }

    private function movement(string $type, string $date, array $lines, ?Outlet $outlet = null): AssetMovement
    {
        $movement = AssetMovement::create([
            'company_id'    => $this->company->id,
            'outlet_id'     => ($outlet ?? $this->main)->id,
            'movement_type' => $type,
            'movement_date' => $date,
            'total_cost'    => 0,
        ]);

        foreach ($lines as $assetId => $quantity) {
            $movement->lines()->create([
                'asset_id' => $assetId, 'quantity' => $quantity, 'unit_cost' => 0, 'total_cost' => 0,
            ]);
        }

        return $movement;
    }

    private function assetCount(string $status, string $date, array $lines, ?Outlet $outlet = null): AssetCount
    {
        $count = AssetCount::create([
            'company_id' => $this->company->id,
            'outlet_id'  => ($outlet ?? $this->main)->id,
            'status'     => $status,
            'count_date' => $date,
        ]);

        foreach ($lines as $assetId => $quantity) {
            $count->lines()->create([
                'asset_id' => $assetId, 'counted_quantity' => $quantity, 'unit_cost' => 0,
            ]);
        }

        return $count;
    }

    private function onHand(Asset $asset, ?Outlet $outlet = null): float
    {
        return app(AssetOnHandService::class)->quantity($asset->id, ($outlet ?? $this->main)->id);
    }

    public function test_receipts_add_up_and_disposals_come_off(): void
    {
        $this->movement(AssetMovement::TYPE_RECEIPT, '2026-01-10', [$this->plate->id => 120]);
        $this->movement(AssetMovement::TYPE_RECEIPT, '2026-02-01', [$this->plate->id => 60]);
        $this->movement(AssetMovement::TYPE_DISPOSAL, '2026-02-15', [$this->plate->id => 8]);

        $this->assertSame(172.0, $this->onHand($this->plate));
    }

    public function test_a_completed_count_replaces_everything_before_it(): void
    {
        $this->movement(AssetMovement::TYPE_RECEIPT, '2026-01-10', [$this->plate->id => 120]);

        // Counted 96 — twenty-four went missing without a disposal note, which
        // is the whole reason assets get counted.
        $this->assetCount(AssetCount::STATUS_COMPLETED, '2026-03-01', [$this->plate->id => 96]);

        $this->assertSame(96.0, $this->onHand($this->plate));

        $this->movement(AssetMovement::TYPE_RECEIPT, '2026-03-05', [$this->plate->id => 24]);

        $this->assertSame(120.0, $this->onHand($this->plate));
    }

    /**
     * Count the bar in the morning, take a delivery in the afternoon, book it
     * in. Same date on both documents, so the tie is broken by when each was
     * entered — the delivery was keyed after the count was completed, so it
     * happened after it.
     */
    public function test_a_movement_entered_after_the_count_still_counts_on_the_same_day(): void
    {
        $this->assetCount(AssetCount::STATUS_COMPLETED, '2026-03-01', [$this->plate->id => 96]);

        // A second apart, because both timestamps are whole seconds and a tie
        // inside the same second is not something this can resolve. Nothing
        // real gets close: a count takes minutes to walk.
        $this->travel(1)->second();

        $this->movement(AssetMovement::TYPE_RECEIPT, '2026-03-01', [$this->plate->id => 24]);

        $this->assertSame(120.0, $this->onHand($this->plate));
    }

    /**
     * The other way round: the delivery was booked in first and the person
     * counting then walked past those plates and counted them. Adding it again
     * would double-count it.
     */
    public function test_a_movement_entered_before_the_count_is_already_in_it(): void
    {
        $this->movement(AssetMovement::TYPE_RECEIPT, '2026-03-01', [$this->plate->id => 24]);

        $this->travel(1)->second();

        $this->assetCount(AssetCount::STATUS_COMPLETED, '2026-03-01', [$this->plate->id => 96]);

        $this->assertSame(96.0, $this->onHand($this->plate));
    }

    public function test_a_movement_dated_after_the_count_always_counts(): void
    {
        $this->assetCount(AssetCount::STATUS_COMPLETED, '2026-03-01', [$this->plate->id => 96]);
        $this->movement(AssetMovement::TYPE_RECEIPT, '2026-03-02', [$this->plate->id => 24]);

        $this->assertSame(120.0, $this->onHand($this->plate));
    }

    public function test_a_movement_dated_before_the_count_never_counts(): void
    {
        $this->movement(AssetMovement::TYPE_RECEIPT, '2026-02-20', [$this->plate->id => 24]);
        $this->assetCount(AssetCount::STATUS_COMPLETED, '2026-03-01', [$this->plate->id => 96]);

        $this->assertSame(96.0, $this->onHand($this->plate));
    }

    public function test_a_draft_count_is_not_a_baseline(): void
    {
        $this->movement(AssetMovement::TYPE_RECEIPT, '2026-01-10', [$this->plate->id => 120]);
        $this->assetCount(AssetCount::STATUS_DRAFT, '2026-03-01', [$this->plate->id => 5]);

        $this->assertSame(120.0, $this->onHand($this->plate));
    }

    /**
     * The reason the baseline is per asset rather than per count: a count of
     * one section of the outlet says nothing about the assets it never listed,
     * and treating them as counted-zero would wipe them.
     */
    public function test_a_partial_count_leaves_the_assets_it_did_not_list_alone(): void
    {
        $this->movement(AssetMovement::TYPE_RECEIPT, '2026-01-10', [
            $this->plate->id => 120,
            $this->knife->id => 10,
        ]);

        $this->assetCount(AssetCount::STATUS_COMPLETED, '2026-03-01', [$this->plate->id => 96]);

        $this->assertSame(96.0, $this->onHand($this->plate));
        $this->assertSame(10.0, $this->onHand($this->knife), 'An uncounted asset must not be read as zero.');
    }

    public function test_quantities_are_held_per_outlet(): void
    {
        $this->movement(AssetMovement::TYPE_RECEIPT, '2026-01-10', [$this->plate->id => 120]);
        $this->movement(AssetMovement::TYPE_RECEIPT, '2026-01-10', [$this->plate->id => 30], $this->second);

        $this->assertSame(120.0, $this->onHand($this->plate, $this->main));
        $this->assertSame(30.0, $this->onHand($this->plate, $this->second));

        $both = app(AssetOnHandService::class)
            ->forOutlets([$this->main->id, $this->second->id], [$this->plate->id]);

        $this->assertSame(120.0, $both[$this->main->id][$this->plate->id]);
        $this->assertSame(30.0, $both[$this->second->id][$this->plate->id]);
    }

    public function test_a_deleted_movement_stops_counting(): void
    {
        $receipt = $this->movement(AssetMovement::TYPE_RECEIPT, '2026-01-10', [$this->plate->id => 120]);

        $this->assertSame(120.0, $this->onHand($this->plate));

        $receipt->delete();

        $this->assertSame(0.0, $this->onHand($this->plate));
    }

    public function test_a_deleted_count_falls_back_to_the_one_before_it(): void
    {
        $this->assetCount(AssetCount::STATUS_COMPLETED, '2026-01-31', [$this->plate->id => 120]);
        $later = $this->assetCount(AssetCount::STATUS_COMPLETED, '2026-03-01', [$this->plate->id => 96]);

        $this->assertSame(96.0, $this->onHand($this->plate));

        $later->delete();

        $this->assertSame(120.0, $this->onHand($this->plate));
    }
}
