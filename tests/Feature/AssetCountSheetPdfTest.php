<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetCount;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Pdf\PdfImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The printed asset count sheet.
 *
 * The photo is the reason this export exists on paper rather than as a name
 * list: forty smallwares on a sheet, and "MIXING BOWL" against "STAINLESS
 * MIXING BOWL" is not a difference a name settles when both are in front of
 * you. So what these pin is that the picture reaches the page, that it is
 * EMBEDDED rather than linked (dompdf fetches nothing over the network), and
 * that it is embedded small — one photo per row is how a count sheet blows a
 * memory limit.
 */
class AssetCountSheetPdfTest extends TestCase
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
            'name' => 'Sheet Co', 'slug' => Str::slug('Sheet Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->piece = UnitOfMeasure::create(['name' => 'Piece', 'abbreviation' => 'pc', 'type' => 'count']);
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

    /** A real JPEG on the fake public disk, so PdfImage has something to decode. */
    private function storedPhoto(string $path, int $w = 1200, int $h = 900): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 30, 30));

        ob_start();
        imagejpeg($img, null, 90);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    private function asset(string $name, ?string $imagePath = null, ?AssetCategory $category = null): Asset
    {
        return Asset::create([
            'company_id' => $this->company->id, 'name' => $name, 'uom_id' => $this->piece->id,
            'unit_cost' => 10, 'is_active' => true, 'image_path' => $imagePath,
            'asset_category_id' => $category?->id,
        ]);
    }

    private function countWith(array $assets): AssetCount
    {
        $count = AssetCount::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'status' => AssetCount::STATUS_DRAFT, 'count_date' => '2026-04-01',
            'reference_number' => 'AC-2026-Q2', 'created_by' => auth()->id(),
        ]);

        foreach ($assets as $asset) {
            $count->lines()->create([
                'asset_id' => $asset->id, 'system_quantity' => 12, 'counted_quantity' => 0, 'unit_cost' => 10,
            ]);
        }

        return $count;
    }

    public function test_the_sheet_downloads_as_a_pdf(): void
    {
        $this->actingAs($this->userWith(['assets.view']));

        $count = $this->countWith([$this->asset('DINNER PLATE')]);

        $response = $this->get(route('assets.counts.count-sheet', $count->id));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('Asset-Count-Sheet-AC-2026-Q2.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    /**
     * The picture has to be IN the file. dompdf does no remote fetching here,
     * so a linked image would silently print as nothing.
     */
    public function test_the_photo_is_embedded_in_the_pdf(): void
    {
        $this->actingAs($this->userWith(['assets.view']));

        $count = $this->countWith([
            $this->asset('MIXING BOWL', $this->storedPhoto('asset-photos/bowl.jpg')),
        ]);

        $pdf = $this->get(route('assets.counts.count-sheet', $count->id))->getContent();

        // A JPEG inside a PDF shows up as a DCTDecode image stream; without the
        // photo the document carries no image at all.
        $this->assertStringContainsString('DCTDecode', $pdf);
    }

    public function test_an_asset_without_a_photo_still_prints(): void
    {
        $this->actingAs($this->userWith(['assets.view']));

        $count = $this->countWith([$this->asset('CHEF KNIFE')]);

        $pdf = $this->get(route('assets.counts.count-sheet', $count->id))->getContent();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringNotContainsString('DCTDecode', $pdf);
    }

    /**
     * A photo that has gone missing off the disk must not take the sheet down
     * with it — PdfImage returns null and the row prints without a picture.
     */
    public function test_a_missing_photo_file_does_not_break_the_sheet(): void
    {
        $this->actingAs($this->userWith(['assets.view']));

        $count = $this->countWith([$this->asset('MIXING BOWL', 'asset-photos/gone.jpg')]);

        $this->get(route('assets.counts.count-sheet', $count->id))->assertOk();
    }

    /**
     * One photo per row is how a count sheet blows a memory limit, so the
     * thumbnail is embedded far smaller than the stored original.
     */
    public function test_the_embedded_thumbnail_is_small(): void
    {
        $path = $this->storedPhoto('asset-photos/big.jpg', 1600, 1200);

        $original = strlen(Storage::disk('public')->get($path));
        $embedded  = app(PdfImage::class)->thumb($path);

        $this->assertNotNull($embedded);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $embedded);

        $decoded = base64_decode(substr($embedded, strlen('data:image/jpeg;base64,')));

        $this->assertLessThan($original, strlen($decoded));

        [$w, $h] = getimagesizefromstring($decoded);

        $this->assertLessThanOrEqual(160, max($w, $h), 'A row thumbnail is capped well below the photo size.');
    }

    public function test_the_sheet_is_closed_without_the_view_ability(): void
    {
        $this->actingAs($this->userWith(['ingredients.view']));

        $count = $this->countWith([$this->asset('DINNER PLATE')]);

        $this->get(route('assets.counts.count-sheet', $count->id))->assertForbidden();
    }

    public function test_a_count_at_an_outlet_you_cannot_reach_is_refused(): void
    {
        $this->actingAs($this->userWith(['assets.view']));

        $count = $this->countWith([$this->asset('DINNER PLATE')]);

        $other = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Other', 'code' => 'OTH', 'is_active' => true,
        ]);
        $count->update(['outlet_id' => $other->id]);

        $stranger = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'can_view_all_outlets' => false,
        ]);
        $stranger->companies()->syncWithoutDetaching([$this->company->id]);
        $stranger->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $stranger->givePermissionTo(Permission::findOrCreate('assets.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($stranger)
            ->get(route('assets.counts.count-sheet', $count->id))
            ->assertForbidden();
    }

    /** Grouped by category, with a parent swallowing its children. */
    public function test_lines_are_grouped_under_their_top_category(): void
    {
        $this->actingAs($this->userWith(['assets.view']));

        $equipment = AssetCategory::create([
            'company_id' => $this->company->id, 'name' => 'Equipment', 'color' => '#14b8a6',
        ]);
        $fridges = AssetCategory::create([
            'company_id' => $this->company->id, 'name' => 'Refrigeration',
            'color' => '#14b8a6', 'parent_id' => $equipment->id,
        ]);

        $count = $this->countWith([
            $this->asset('UPRIGHT CHILLER', null, $fridges),
            $this->asset('DINNER PLATE'),
        ]);

        $grouped = $count->load('lines.asset.category.parent')->lines
            ->groupBy(fn ($l) => $l->asset?->category?->parent?->name ?? $l->asset?->category?->name ?? 'Uncategorised');

        $this->assertTrue($grouped->has('Equipment'), 'A child category prints under its parent.');
        $this->assertTrue($grouped->has('Uncategorised'));

        $this->get(route('assets.counts.count-sheet', $count->id))->assertOk();
    }
}
