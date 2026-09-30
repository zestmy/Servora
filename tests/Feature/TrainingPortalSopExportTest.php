<?php

namespace Tests\Feature;

use App\Jobs\GenerateSopExport;
use App\Http\Controllers\Lms\SopExportController;
use App\Livewire\Settings\LmsUsers;
use App\Livewire\Settings\SopExportPanel;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\Recipe;
use App\Models\SopExport;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The Training Portal's two SOP export sections.
 *
 * The handbook's last good copy stays downloadable — through a rebuild, a
 * failed one, and past the pruner's retention window — until a newer one
 * replaces it. The category exports can be narrowed to one outlet.
 */
class TrainingPortalSopExportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->company = Company::create([
            'name' => 'SOP Co', 'slug' => Str::slug('SOP Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
    }

    private function completed(string $finishedAt, array $filters = []): SopExport
    {
        $path = 'sop-exports/' . uniqid() . '.pdf';
        Storage::disk('local')->put($path, '%PDF-fake');

        $export = SopExport::create([
            'company_id' => $this->company->id, 'user_id' => $this->user->id,
            'status' => SopExport::STATUS_COMPLETED, 'label' => 'All', 'filters' => $filters,
            'progress' => 100, 'file_path' => $path, 'filename' => 'SOP Co-Training-SOPs.pdf',
            'file_size' => 9, 'recipe_count' => 3,
            'started_at' => $finishedAt, 'finished_at' => $finishedAt,
        ]);
        $export->forceFill(['created_at' => $finishedAt])->save();

        return $export;
    }

    public function test_previous_copy_stays_downloadable_while_a_rebuild_runs(): void
    {
        $done = $this->completed('2026-09-28 10:00:00');

        SopExport::create([
            'company_id' => $this->company->id, 'user_id' => $this->user->id,
            'status' => SopExport::STATUS_PROCESSING, 'label' => 'All', 'filters' => [], 'progress' => 40,
        ]);

        Livewire::actingAs($this->user)
            ->test(SopExportPanel::class)
            ->assertSee(route('training.sop.export.download', $done), false)
            ->assertSee('Download · 28 Sep 2026');
    }

    public function test_pruner_spares_the_newest_completed_file_however_old(): void
    {
        $older  = $this->completed(now()->subDays(10)->toDateTimeString());
        $newest = $this->completed(now()->subDays(5)->toDateTimeString());

        $this->artisan('sop:prune-exports')->assertSuccessful();

        $this->assertNull($older->fresh()->file_path);
        $this->assertTrue($newest->fresh()->isDownloadable());
    }

    public function test_download_filename_carries_the_build_date(): void
    {
        $export = $this->completed('2026-09-30 14:05:00');

        $this->assertSame('SOP Co-Training-SOPs-2026-09-30.pdf', SopExportController::datedFilename($export));
    }

    public function test_category_exports_narrow_to_the_chosen_outlet(): void
    {
        $klcc = Outlet::create(['company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KL', 'is_active' => true]);
        $ioi  = Outlet::create(['company_id' => $this->company->id, 'name' => 'IOI', 'code' => 'IO', 'is_active' => true]);

        $uom = UnitOfMeasure::first() ?? UnitOfMeasure::create([
            'name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight', 'base_factor' => 1,
        ]);

        $recipe = fn (string $name, string $category) => Recipe::create([
            'company_id' => $this->company->id, 'name' => $name, 'yield_uom_id' => $uom->id,
            'category' => $category, 'is_active' => true, 'is_prep' => false, 'exclude_from_lms' => false,
        ]);

        $recipe('Nasi Lemak', 'Rice');                                   // "All Outlets"
        $recipe('Iced Latte', 'Coffee')->outlets()->sync([$ioi->id]);   // IOI only
        $recipe('Kaya Toast', 'Toast')->outlets()->sync([$klcc->id, $ioi->id]);
        $recipe('Butter Toast', 'Toast');                                // "All Outlets"

        $page = Livewire::actingAs($this->user)->test(LmsUsers::class);

        $page->assertViewHas('sopCategories', fn ($c) => $c->values()->all() === ['Coffee', 'Rice', 'Toast']);

        // Same rule as every outlet filter: tagged to KLCC or untagged. Coffee
        // is tagged to IOI only, so it drops out; Rice ("All Outlets") stays,
        // so a new outlet sees the shared menu without re-tagging anything.
        $page->set('sopOutletId', (string) $klcc->id)
            ->assertViewHas('sopCategories', fn ($c) => $c->values()->all() === ['Rice', 'Toast'])
            ->assertSee(route('training.sop.pdf-all', ['category' => 'Toast', 'outlet' => $klcc->id]));

        // The PDF applies the same rule: Kaya Toast (tagged) and Butter Toast (untagged).
        $builder = app(\App\Services\Pdf\SopExportBuilder::class);
        $this->assertSame(2, $builder->bulk($this->user, ['category' => 'Toast', 'outlet' => $klcc->id])['recipeCount']);
        // And a recipe tagged only to another outlet is left out of KLCC's.
        $this->assertSame(0, $builder->bulk($this->user, ['category' => 'Coffee', 'outlet' => $klcc->id])['recipeCount']);

        // Another company's outlet id is ignored rather than trusted.
        $foreign = Outlet::create(['company_id' => Company::create([
            'name' => 'Other', 'slug' => 'other-' . uniqid(), 'currency' => 'MYR', 'is_active' => true,
        ])->id, 'name' => 'Elsewhere', 'code' => 'EL', 'is_active' => true]);

        $page->set('sopOutletId', (string) $foreign->id)
            ->assertViewHas('exportOutletId', null);
    }

    public function test_handbook_is_built_and_kept_per_outlet(): void
    {
        Queue::fake();

        $klcc = Outlet::create(['company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KL', 'is_active' => true]);
        Outlet::create(['company_id' => $this->company->id, 'name' => 'IOI', 'code' => 'IO', 'is_active' => true]);

        setPermissionsTeamId($this->company->id);
        // The panel sits on Settings > LMS Users (can:training.portal), so that
        // is the ability start() asks for — hr.view was the wrong one.
        Permission::findOrCreate('training.portal', 'web');
        $this->user->givePermissionTo('training.portal');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all =$this->completed(now()->subDays(5)->toDateTimeString());

        // Picking an outlet shows that outlet's copy, not the all-SOPs one.
        $panel = Livewire::actingAs($this->user)->test(SopExportPanel::class)
            ->assertSee(route('training.sop.export.download', $all), false)
            ->set('outletId', (string) $klcc->id)
            ->assertDontSee(route('training.sop.export.download', $all), false)
            ->call('start');

        $export = SopExport::latest('id')->first();
        $this->assertSame(['outlet' => $klcc->id], $export->filters);
        $this->assertStringContainsString('KLCC', $export->label);
        Queue::assertPushed(GenerateSopExport::class);

        // Each selection keeps its own newest file past the retention window.
        $export->delete();
        $klccOld = $this->completed(now()->subDays(9)->toDateTimeString(), ['outlet' => $klcc->id]);
        $klccNew = $this->completed(now()->subDays(4)->toDateTimeString(), ['outlet' => $klcc->id]);

        $this->artisan('sop:prune-exports')->assertSuccessful();

        $this->assertTrue($all->fresh()->isDownloadable());
        $this->assertTrue($klccNew->fresh()->isDownloadable());
        $this->assertNull($klccOld->fresh()->file_path);
    }
}
