<?php

namespace Tests\Feature;

use App\Livewire\Assets\CountForm;
use App\Livewire\Assets\Index as AssetsIndex;
use App\Livewire\Assets\Register;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The photograph on an asset.
 *
 * It exists for one job: somebody walking an outlet with forty smallwares on a
 * count sheet needs to tell two mixing bowls apart, and a name cannot do that.
 * So what these pin is that the picture survives the round trip to the count
 * sheet, that replacing one does not leave the old file behind, and that a file
 * the browser cannot draw produces a validation error rather than a dead form —
 * which is the failure the employee photo shipped with before it was found.
 */
class AssetPhotoTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private UnitOfMeasure $piece;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->company = Company::create([
            'name' => 'Asset Photo Co', 'slug' => Str::slug('Asset Photo Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->piece = UnitOfMeasure::create(['name' => 'Piece', 'abbreviation' => 'pc', 'type' => 'count']);

        $this->actingAs($this->userWith([
            'assets.view', 'assets.manage', 'assets.cost', 'assets.delete', 'assets.counts.record',
        ]));
    }

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(collect($permissions)->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function asset(string $name, ?string $imagePath = null): Asset
    {
        return Asset::create([
            'company_id' => $this->company->id, 'name' => $name, 'uom_id' => $this->piece->id,
            'unit_cost' => 10, 'is_active' => true, 'image_path' => $imagePath,
        ]);
    }

    public function test_a_photo_can_be_added_when_adding_an_asset(): void
    {
        Livewire::test(AssetsIndex::class)
            ->call('openCreate')
            ->set('name', 'stainless mixing bowl')
            ->set('uom_id', $this->piece->id)
            ->set('unit_cost', '34')
            ->set('image', UploadedFile::fake()->image('bowl.jpg', 800, 600))
            ->call('save')
            ->assertHasNoErrors();

        $asset = Asset::firstOrFail();

        $this->assertNotNull($asset->image_path);
        $this->assertStringStartsWith('asset-photos/' . $this->company->id . '/', $asset->image_path);
        Storage::disk('public')->assertExists($asset->image_path);
        $this->assertStringContainsString($asset->image_path, $asset->imageUrl());
    }

    public function test_replacing_a_photo_deletes_the_one_it_replaced(): void
    {
        $asset = $this->asset('MIXING BOWL');

        Livewire::test(AssetsIndex::class)
            ->call('openEdit', $asset->id)
            ->set('image', UploadedFile::fake()->image('first.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $first = $asset->fresh()->image_path;
        Storage::disk('public')->assertExists($first);

        Livewire::test(AssetsIndex::class)
            ->call('openEdit', $asset->id)
            ->set('image', UploadedFile::fake()->image('second.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $second = $asset->fresh()->image_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertExists($second);
        Storage::disk('public')->assertMissing($first);
    }

    /**
     * Remove is deferred to Save here, unlike EmployeeForm where it deletes on
     * the spot — this is a modal with a Cancel beside it. So the file has to
     * survive Remove-then-Cancel, and go on Remove-then-Save.
     */
    public function test_removing_a_photo_waits_for_save(): void
    {
        $asset = $this->asset('MIXING BOWL');

        $component = Livewire::test(AssetsIndex::class)
            ->call('openEdit', $asset->id)
            ->set('image', UploadedFile::fake()->image('bowl.jpg'))
            ->call('save');

        $path = $asset->fresh()->image_path;
        Storage::disk('public')->assertExists($path);

        // Removed, then cancelled — nothing happens.
        $component->call('openEdit', $asset->id)
            ->call('clearImage')
            ->assertSet('removeImage', true)
            ->call('closeModal');

        $this->assertSame($path, $asset->fresh()->image_path);
        Storage::disk('public')->assertExists($path);

        // Removed, then saved — it goes.
        $component->call('openEdit', $asset->id)
            ->call('clearImage')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($asset->fresh()->image_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_choosing_a_new_photo_cancels_a_pending_removal(): void
    {
        $asset = $this->asset('MIXING BOWL', 'asset-photos/old.jpg');
        Storage::disk('public')->put('asset-photos/old.jpg', 'x');

        Livewire::test(AssetsIndex::class)
            ->call('openEdit', $asset->id)
            ->call('clearImage')
            ->assertSet('removeImage', true)
            ->set('image', UploadedFile::fake()->image('new.jpg'))
            ->assertSet('removeImage', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNotNull($asset->fresh()->image_path);
        $this->assertNotSame('asset-photos/old.jpg', $asset->fresh()->image_path);
    }

    /**
     * A file the browser cannot draw is refused AS IT LANDS, not at save.
     *
     * That is the whole point of the guard: the modal previews with
     * temporaryUrl(), which throws on anything unpreviewable, and a throw there
     * is a 500 on the render right after choosing the file — the failure the
     * employee photo shipped with. So the upload is dropped, an error is shown,
     * and the form is still usable. Saving afterwards files the asset without a
     * photo rather than refusing the whole record over one bad file.
     */
    public function test_a_file_that_is_not_an_image_is_refused_as_it_lands(): void
    {
        $component = Livewire::test(AssetsIndex::class)
            ->call('openCreate')
            ->set('name', 'MIXING BOWL')
            ->set('uom_id', $this->piece->id)
            ->set('image', UploadedFile::fake()->create('invoice.pdf', 40, 'application/pdf'));

        $component->assertHasErrors('image')
            ->assertSet('image', null);

        // The form is alive, not dead: the rest of the record still saves.
        $component->call('save')->assertHasNoErrors();

        $this->assertNull(Asset::firstOrFail()->image_path);
    }

    public function test_a_photo_over_five_megabytes_is_refused(): void
    {
        Livewire::test(AssetsIndex::class)
            ->call('openCreate')
            ->set('name', 'MIXING BOWL')
            ->set('uom_id', $this->piece->id)
            ->set('image', UploadedFile::fake()->image('huge.jpg')->size(6000))
            ->call('save')
            ->assertHasErrors('image');
    }

    /** The reason the column exists: the count sheet carries it. */
    public function test_the_count_sheet_carries_the_photo(): void
    {
        $withPhoto = $this->asset('MIXING BOWL', 'asset-photos/bowl.jpg');
        $without   = $this->asset('CHEF KNIFE');

        Livewire::test(CountForm::class)
            ->call('loadAll')
            ->assertCount('lines', 2)
            // Ordered by name: CHEF KNIFE, then MIXING BOWL.
            ->assertSet('lines.0.image', null)
            ->assertSet('lines.1.image', Storage::disk('public')->url($withPhoto->image_path));

        $this->assertNull($without->imageUrl());
    }

    /**
     * The register carries it too, so the screen that says what a thing is
     * worth can also show what it is.
     */
    public function test_the_register_carries_the_photo(): void
    {
        $withPhoto = $this->asset('MIXING BOWL', 'asset-photos/bowl.jpg');
        $without   = $this->asset('CHEF KNIFE');

        // The register only lists what is actually held, so give both a receipt.
        $movement = \App\Models\AssetMovement::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'movement_type' => \App\Models\AssetMovement::TYPE_RECEIPT,
            'movement_date' => now()->toDateString(), 'total_cost' => 0,
        ]);
        $movement->lines()->create(['asset_id' => $withPhoto->id, 'quantity' => 4, 'unit_cost' => 10, 'total_cost' => 40]);
        $movement->lines()->create(['asset_id' => $without->id, 'quantity' => 2, 'unit_cost' => 10, 'total_cost' => 20]);

        $rows = Livewire::test(Register::class)->viewData('rows')->items();

        $byName = collect($rows)->keyBy('name');

        $this->assertSame(
            Storage::disk('public')->url($withPhoto->image_path),
            $byName['MIXING BOWL']['image']
        );
        $this->assertNull($byName['CHEF KNIFE']['image']);
    }

    public function test_force_deleting_an_asset_takes_its_photo_with_it(): void
    {
        $asset = $this->asset('MIXING BOWL', 'asset-photos/bowl.jpg');
        Storage::disk('public')->put('asset-photos/bowl.jpg', 'x');

        // A soft delete keeps the file — the row can come back, and an asset
        // restored to a broken image would be worse than a few stray kilobytes.
        $asset->delete();
        Storage::disk('public')->assertExists('asset-photos/bowl.jpg');

        $asset->forceDelete();
        Storage::disk('public')->assertMissing('asset-photos/bowl.jpg');
    }
}
