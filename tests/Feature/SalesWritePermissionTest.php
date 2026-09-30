<?php

namespace Tests\Feature;

use App\Livewire\Sales\Index as SalesIndex;
use App\Livewire\Sales\SalesForm;
use App\Livewire\Sales\ZeoniqExcelImport;
use App\Livewire\Sales\ZReportImport;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\SalesRecord;
use App\Models\SalesRecordAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The Sales page's route asks only for sales.view, and both import components are
 * embedded in it. Deleting and importing therefore authorise themselves; the
 * public $canDelete flag is display only. The sales form's attachment removal is
 * pinned to the record being edited, because SalesRecordAttachment has no company
 * scope of its own.
 */
class SalesWritePermissionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        [$this->company, $this->outlet] = $this->company('Sell Co');
    }

    /** @return array{0: Company, 1: Outlet} */
    private function company(string $name): array
    {
        $company = Company::create([
            'name' => $name, 'slug' => Str::slug($name) . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $outlet = Outlet::create([
            'company_id' => $company->id, 'name' => 'Main', 'code' => 'M' . $company->id, 'is_active' => true,
        ]);

        return [$company, $outlet];
    }

    /** @param array<int, string> $abilities */
    private function user(array $abilities): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(array_map(fn ($a) => Permission::findOrCreate($a, 'web'), $abilities));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user);

        return $user;
    }

    private function sale(Company $company, Outlet $outlet): SalesRecord
    {
        return SalesRecord::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'outlet_id' => $outlet->id, 'sale_date' => '2026-07-01',
        ]);
    }

    public function test_a_view_only_user_cannot_delete_sales(): void
    {
        $viewer = $this->user(['sales.view']);
        $sale   = $this->sale($this->company, $this->outlet);

        Livewire::actingAs($viewer)->test(SalesIndex::class)
            ->call('delete', $sale->id)
            ->assertForbidden();

        Livewire::actingAs($viewer)->test(SalesIndex::class)
            ->set('selected', [(string) $sale->id])
            ->call('bulkDelete')
            ->assertForbidden();

        $this->assertDatabaseHas('sales_records', ['id' => $sale->id]);
    }

    public function test_the_can_delete_flag_cannot_be_flipped_from_the_browser(): void
    {
        $viewer = $this->user(['sales.view']);

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($viewer)->test(SalesIndex::class)->set('canDelete', true);
    }

    public function test_a_user_with_delete_can_delete_sales(): void
    {
        $user = $this->user(['sales.view', 'sales.delete']);
        $sale = $this->sale($this->company, $this->outlet);

        Livewire::actingAs($user)->test(SalesIndex::class)
            ->call('delete', $sale->id)
            ->assertOk();

        $this->assertNull(SalesRecord::find($sale->id));
    }

    public function test_a_view_only_user_cannot_run_either_import(): void
    {
        $viewer = $this->user(['sales.view']);

        Livewire::actingAs($viewer)->test(ZReportImport::class)
            ->call('saveAll')
            ->assertForbidden();

        Livewire::actingAs($viewer)->test(ZReportImport::class)
            ->call('processZReport')
            ->assertForbidden();

        Livewire::actingAs($viewer)->test(ZeoniqExcelImport::class)
            ->call('saveAll')
            ->assertForbidden();

        Livewire::actingAs($viewer)->test(ZeoniqExcelImport::class)
            ->call('processFile')
            ->assertForbidden();

        $this->assertDatabaseCount('sales_records', 0);
    }

    public function test_removing_an_attachment_cannot_reach_another_companys_record(): void
    {
        [$other, $otherOutlet] = $this->company('Rival Co');
        $theirs = $this->sale($other, $otherOutlet);
        Storage::disk('public')->put('sales-attachments/theirs.jpg', 'bytes');
        $theirFile = SalesRecordAttachment::create([
            'sales_record_id' => $theirs->id, 'file_path' => 'sales-attachments/theirs.jpg',
            'file_name' => 'theirs.jpg', 'mime_type' => 'image/jpeg', 'file_size' => 5,
        ]);

        $user = $this->user(['sales.view', 'sales.record']);
        $mine = $this->sale($this->company, $this->outlet);

        Livewire::actingAs($user)->test(SalesForm::class, ['id' => $mine->id])
            ->call('removeExistingAttachment', $theirFile->id);

        $this->assertDatabaseHas('sales_record_attachments', ['id' => $theirFile->id]);
        Storage::disk('public')->assertExists('sales-attachments/theirs.jpg');
    }

    public function test_removing_an_attachment_still_works_on_the_record_being_edited(): void
    {
        $user = $this->user(['sales.view', 'sales.record']);
        $mine = $this->sale($this->company, $this->outlet);
        Storage::disk('public')->put('sales-attachments/mine.jpg', 'bytes');
        $file = SalesRecordAttachment::create([
            'sales_record_id' => $mine->id, 'file_path' => 'sales-attachments/mine.jpg',
            'file_name' => 'mine.jpg', 'mime_type' => 'image/jpeg', 'file_size' => 5,
        ]);

        Livewire::actingAs($user)->test(SalesForm::class, ['id' => $mine->id])
            ->call('removeExistingAttachment', $file->id);

        $this->assertDatabaseMissing('sales_record_attachments', ['id' => $file->id]);
    }
}
