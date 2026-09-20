<?php

namespace Tests\Feature;

use App\Livewire\Purchasing\PurchaseRequestForm;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A purchase request and its lines are saved together or not at all.
 *
 * Written after a real one: the asset feature shipped writing source='asset'
 * into an enum that only knew 'supplier' and 'kitchen', so the line insert was
 * refused — and because the request row was created first, outside any
 * transaction, production was left holding an APPROVED purchase request with
 * nothing on it. The enum is fixed; this pins the shape of the failure so the
 * next refused line cannot leave the same wreckage.
 *
 * The failure is forced with a model event rather than bad data, because
 * validation rejects bad data long before the insert and the drivers disagree
 * about which values a column will refuse — SQLite, which these tests run on,
 * would have accepted the very value that took production down.
 */
class PurchaseRequestSaveIsAtomicTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private UnitOfMeasure $piece;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Atomic PR Co', 'slug' => Str::slug('Atomic PR Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->piece = UnitOfMeasure::create(['name' => 'Piece', 'abbreviation' => 'pc', 'type' => 'count']);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(collect([
            'purchasing.view', 'purchasing.requests.create', 'purchasing.requests.edit',
            'assets.view',
        ])->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->user);
    }

    private function asset(string $name): Asset
    {
        return Asset::create([
            'company_id' => $this->company->id, 'name' => $name,
            'uom_id' => $this->piece->id, 'unit_cost' => 4000, 'is_active' => true,
        ]);
    }

    /** The database refusing a line must take the request down with it. */
    public function test_a_refused_line_leaves_no_request_behind(): void
    {
        $mixer = $this->asset('STAND MIXER');

        PurchaseRequestLine::creating(fn () => throw new \RuntimeException('line refused'));

        try {
            Livewire::test(PurchaseRequestForm::class)
                ->call('addAsset', $mixer->id)
                ->set('lines.0.quantity', 2)
                ->call('save', 'submit');
            $this->fail('The save should have surfaced the failure rather than swallowing it.');
        } catch (\RuntimeException $e) {
            $this->assertSame('line refused', $e->getMessage());
        }

        $this->assertSame(0, PurchaseRequest::count(),
            'An approved request with no lines is exactly what the transaction exists to prevent.');
        $this->assertSame(0, PurchaseRequestLine::count());
    }

    /** Amending deletes the old lines first, so a refusal must restore them. */
    public function test_a_refused_line_does_not_destroy_the_lines_it_was_replacing(): void
    {
        $mixer = $this->asset('STAND MIXER');
        $oven  = $this->asset('COMBI OVEN');

        Livewire::test(PurchaseRequestForm::class)
            ->call('addAsset', $mixer->id)
            ->set('lines.0.quantity', 2)
            ->call('save')
            ->assertHasNoErrors();

        $pr = PurchaseRequest::firstOrFail();
        $this->assertSame(1, $pr->lines()->count());

        PurchaseRequestLine::creating(fn () => throw new \RuntimeException('line refused'));

        try {
            Livewire::test(PurchaseRequestForm::class, ['id' => $pr->id])
                ->call('addAsset', $oven->id)
                ->set('lines.1.quantity', 1)
                ->call('save');
            $this->fail('The save should have surfaced the failure rather than swallowing it.');
        } catch (\RuntimeException $e) {
            $this->assertSame('line refused', $e->getMessage());
        }

        $pr->refresh();
        $this->assertSame(1, $pr->lines()->count(),
            'The original line is still there — the edit path deletes before it writes.');
        $this->assertSame($mixer->id, (int) $pr->lines()->first()->asset_id);
    }
}
