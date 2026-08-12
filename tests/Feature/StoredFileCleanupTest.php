<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\ProductionRecipeImage;
use App\Models\Recipe;
use App\Models\RecipeImage;
use App\Models\RecipeStep;
use App\Models\SalesRecordAttachment;
use App\Models\ScannedDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A row that owns a file takes the file with it.
 *
 * Eleven models held a path to stored bytes and only a handful cleaned up
 * after themselves. Nothing was leaking on production when this was written —
 * every parent whose cascade would reach them soft-deletes, so the cascades
 * never fired — but that is a property of today's delete buttons, not of the
 * schema. The day anything force-deletes a recipe or purges a tenant, 201
 * recipe images and 58 scanned documents would have been left behind with
 * nothing pointing at them.
 *
 * TWO THINGS THIS HAS TO GET RIGHT, and both have a test of their own:
 *
 *   THE DISK. Scanned documents and employee paperwork live on `local`
 *   because they are identity documents; recipe photographs, avatars and
 *   logos live on `public` so they can be served. Naming the wrong disk in a
 *   cleanup hook fails SILENTLY, producing exactly the orphan it was added to
 *   prevent.
 *
 *   SHARED PATHS. Recipes\Index::copyStoredFile() returns the ORIGINAL path
 *   unchanged when the source file is missing, so a duplicated recipe can
 *   share an image row's path. Deleting the copy must not take the original's
 *   picture with it.
 */
class StoredFileCleanupTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->company = Company::create([
            'name' => 'Cleanup Co', 'slug' => Str::slug('Cleanup Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
    }

    private ?User $actor = null;

    /** A user for the columns that insist on knowing who did it. */
    private function actor(): User
    {
        return $this->actor ??= User::factory()->create(['company_id' => $this->company->id]);
    }

    private function uom(): int
    {
        return \App\Models\UnitOfMeasure::firstOrCreate(
            ['name' => 'Kilogram'],
            ['abbreviation' => 'kg', 'type' => 'weight', 'is_base_unit' => true, 'base_unit_factor' => 1],
        )->id;
    }

    private function recipe(): Recipe
    {
        return Recipe::create([
            'company_id' => $this->company->id, 'name' => 'ZZ Recipe ' . Str::random(5),
            'yield_qty' => 1, 'yield_uom_id' => $this->uom(), 'is_active' => true,
        ]);
    }

    private function image(Recipe $recipe, string $path, int $order = 1): RecipeImage
    {
        Storage::disk('public')->put($path, 'bytes');

        return RecipeImage::create([
            'recipe_id' => $recipe->id, 'file_path' => $path, 'sort_order' => $order,
            'file_name' => basename($path), 'mime_type' => 'image/jpeg', 'file_size' => 5,
        ]);
    }

    // ── The disk is found, not assumed ────────────────────────────────────

    public function test_a_public_disk_file_is_removed(): void
    {
        $image = $this->image($this->recipe(), 'recipe-images/a.jpg');

        $image->delete();

        Storage::disk('public')->assertMissing('recipe-images/a.jpg');
    }

    /** The one that would fail silently if the hook named the wrong disk. */
    public function test_a_private_disk_file_is_removed(): void
    {
        $path = 'scanned-documents/' . $this->company->id . '/invoice.pdf';
        Storage::disk('local')->put($path, 'bytes');

        $doc = ScannedDocument::create([
            'company_id' => $this->company->id, 'original_filename' => 'invoice.pdf',
            'file_path' => $path, 'mime_type' => 'application/pdf',
            'uploaded_by' => $this->actor()->id,
        ]);

        $doc->delete();

        Storage::disk('local')->assertMissing($path);
    }

    // ── Shared paths ──────────────────────────────────────────────────────

    public function test_a_file_another_row_still_points_at_is_kept(): void
    {
        $recipe = $this->recipe();
        $shared = 'recipe-images/shared.jpg';

        $first  = $this->image($recipe, $shared, 1);
        $second = $this->image($recipe, $shared, 2);

        $first->delete();

        Storage::disk('public')->assertExists($shared);

        $second->delete();

        Storage::disk('public')->assertMissing($shared);
    }

    // ── Soft deletes keep their files ─────────────────────────────────────

    /**
     * A soft-deleted row can come back, and must come back whole. Removing
     * the file on `deleted` would make every restore hollow.
     */
    public function test_a_soft_deleted_procurement_invoice_keeps_its_scan(): void
    {
        $path = 'procurement/scan.pdf';
        Storage::disk('local')->put($path, 'bytes');

        $invoice = \App\Models\ProcurementInvoice::create([
            'company_id' => $this->company->id, 'invoice_number' => 'INV-1',
            'type' => 'supplier', 'issued_date' => '2026-07-01',
            'subtotal' => 100, 'total_amount' => 100,
            'created_by' => $this->actor()->id,
            'original_file_path' => $path,
        ]);

        $invoice->delete();
        Storage::disk('local')->assertExists($path);

        $invoice->restore();
        Storage::disk('local')->assertExists($path);

        $invoice->forceDelete();
        Storage::disk('local')->assertMissing($path);
    }

    // ── The rest of the sweep ─────────────────────────────────────────────

    public function test_a_recipe_step_image_is_removed(): void
    {
        Storage::disk('public')->put('recipe-steps/s.jpg', 'bytes');

        $step = RecipeStep::create([
            'recipe_id' => $this->recipe()->id, 'sort_order' => 1,
            'instruction' => 'Chop', 'image_path' => 'recipe-steps/s.jpg',
        ]);

        $step->delete();

        Storage::disk('public')->assertMissing('recipe-steps/s.jpg');
    }

    public function test_an_invoice_pdf_is_removed(): void
    {
        Storage::disk('public')->put('invoices/inv.pdf', 'bytes');

        $invoice = Invoice::create([
            'company_id' => $this->company->id, 'invoice_number' => 'X-1',
            'amount' => 10, 'total' => 10, 'status' => 'paid',
            'pdf_path' => 'invoices/inv.pdf',
        ]);

        $invoice->delete();

        Storage::disk('public')->assertMissing('invoices/inv.pdf');
    }

    public function test_a_user_avatar_is_removed(): void
    {
        Storage::disk('public')->put('avatars/me.jpg', 'bytes');

        $user = User::factory()->create([
            'company_id' => $this->company->id, 'avatar' => 'avatars/me.jpg',
        ]);

        $user->delete();

        Storage::disk('public')->assertMissing('avatars/me.jpg');
    }

    public function test_a_production_recipe_image_is_removed(): void
    {
        $kitchen = \App\Models\CentralKitchen::create([
            'company_id' => $this->company->id, 'name' => 'CK', 'code' => 'CK1', 'is_active' => true,
        ]);

        $pr = \App\Models\ProductionRecipe::create([
            'company_id' => $this->company->id, 'kitchen_id' => $kitchen->id, 'name' => 'ZZ Prod',
            'yield_quantity' => 1, 'yield_uom_id' => $this->uom(),
            'created_by' => $this->actor()->id,
        ]);

        Storage::disk('public')->put('production/img.jpg', 'bytes');

        $img = ProductionRecipeImage::create([
            'production_recipe_id' => $pr->id, 'file_path' => 'production/img.jpg',
            'file_name' => 'img.jpg', 'mime_type' => 'image/jpeg', 'file_size' => 5, 'sort_order' => 1,
        ]);

        $img->delete();

        Storage::disk('public')->assertMissing('production/img.jpg');
    }

    public function test_a_sales_attachment_is_removed(): void
    {
        $outlet = \App\Models\Outlet::create([
            'company_id' => $this->company->id, 'name' => 'O', 'code' => 'O1', 'is_active' => true,
        ]);
        $record = \App\Models\SalesRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $outlet->id,
            'sale_date' => '2026-07-01',
        ]);

        Storage::disk('public')->put('sales-attachments/z.jpg', 'bytes');

        $a = SalesRecordAttachment::create([
            'sales_record_id' => $record->id, 'file_path' => 'sales-attachments/z.jpg',
            'file_name' => 'z.jpg', 'mime_type' => 'image/jpeg', 'file_size' => 5,
        ]);

        $a->delete();

        Storage::disk('public')->assertMissing('sales-attachments/z.jpg');
    }

    /** A path already gone must not turn a delete into an error. */
    public function test_a_missing_file_does_not_break_the_delete(): void
    {
        $image = RecipeImage::create([
            'recipe_id' => $this->recipe()->id, 'file_path' => 'recipe-images/never-written.jpg',
            'sort_order' => 1, 'file_name' => 'n.jpg', 'mime_type' => 'image/jpeg', 'file_size' => 5,
        ]);

        $image->delete();

        $this->assertDatabaseMissing('recipe_images', ['id' => $image->id]);
    }

    // ── The leak that was actually happening ──────────────────────────────

    /**
     * Replacing a company logo used to leave the old one behind — an UPDATE
     * leak, which is why the model's `deleted` hook never caught it. Three had
     * accumulated on production.
     */
    public function test_replacing_a_company_logo_removes_the_old_one(): void
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);

        Storage::disk('public')->put('company-logos/old.png', 'old');
        $this->company->update(['logo' => 'company-logos/old.png']);

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Settings\CompanyDetails::class)
            ->set('logo', \Illuminate\Http\UploadedFile::fake()->image('new.png'))
            ->call('save');

        Storage::disk('public')->assertMissing('company-logos/old.png');

        $this->company->refresh();
        $this->assertNotSame('company-logos/old.png', $this->company->logo);
        $this->assertNotNull($this->company->logo);
    }
}
