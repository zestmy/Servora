<?php

namespace Tests\Feature;

use App\Livewire\Admin\Docs\ArticleForm;
use App\Livewire\Admin\Docs\Index as DocsIndex;
use App\Livewire\Help\Article as HelpArticle;
use App\Models\Company;
use App\Models\DocArticle;
use App\Models\DocCategory;
use App\Models\DocImage;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The help centre: public to read, editable only by a system role.
 *
 * The two properties worth holding are that an unpublished article is
 * genuinely invisible rather than merely unlinked, and that a route parameter
 * never again collides with a typed component property — the collision that
 * 404'd every article in the manual while the routes and the rows were both
 * perfectly fine.
 */
class HelpCentreTest extends TestCase
{
    use RefreshDatabase;

    private DocCategory $category;
    private DocArticle $article;
    private User $admin;
    private User $tenantUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = DocCategory::create([
            'slug' => 'purchasing', 'title' => 'Purchasing',
            'summary' => 'Orders and deliveries.', 'icon' => 'cart',
            'sort_order' => 10, 'is_published' => true,
        ]);

        $this->article = DocArticle::create([
            'doc_category_id' => $this->category->id,
            'slug'            => 'receive-goods',
            'title'           => 'Receive a delivery',
            'excerpt'         => 'What to check at the door.',
            'keywords'        => 'GRN, goods received',
            'body'            => '<p>Receiving is where the system finds out what really happened.</p>',
            'sort_order'      => 10,
            'is_published'    => true,
        ]);

        $company = Company::create([
            'name' => 'Doc Co', 'slug' => 'doc-co-' . uniqid(), 'currency' => 'MYR', 'is_active' => true,
        ]);
        $outlet = Outlet::create([
            'company_id' => $company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);
        $this->tenantUser = User::factory()->create([
            'company_id' => $company->id, 'outlet_id' => $outlet->id,
        ]);
        $this->tenantUser->companies()->syncWithoutDetaching([$company->id]);

        $this->admin = User::factory()->create(['company_id' => null]);
        setPermissionsTeamId(null);
        $this->admin->assignRole(Role::findOrCreate('Super Admin', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ── Reading ────────────────────────────────────────────────────────────

    public function test_a_guest_can_read_the_whole_manual(): void
    {
        $this->get('/help')->assertOk()->assertSee('Purchasing');
        $this->get('/help/purchasing')->assertOk()->assertSee('Receive a delivery');
        $this->get('/help/purchasing/receive-goods')
            ->assertOk()
            ->assertSee('what really happened', false);
    }

    /**
     * The regression that mattered. Livewire assigns a route parameter onto
     * the public property of the same name before mount() runs, so a
     * `{category}` segment tried to store the string 'purchasing' in a
     * property typed DocCategory — and every article in the manual 404'd
     * while the route, the category and the article were all correct.
     */
    public function test_the_route_parameters_do_not_collide_with_the_component_properties(): void
    {
        $this->assertSame(
            'help/{categorySlug}/{articleSlug}',
            app('router')->getRoutes()->getByName('help.article')->uri(),
            'The article route parameters must not be named after the typed component properties.'
        );

        $this->get('/help/purchasing/receive-goods')->assertOk();
    }

    public function test_an_unpublished_article_is_not_reachable(): void
    {
        $this->article->update(['is_published' => false]);

        $this->get('/help/purchasing/receive-goods')->assertNotFound();
        $this->get('/help/purchasing')->assertOk()->assertDontSee('Receive a delivery');
    }

    public function test_an_unpublished_section_hides_itself_and_its_articles(): void
    {
        $this->category->update(['is_published' => false]);

        $this->get('/help/purchasing')->assertNotFound();
        $this->get('/help/purchasing/receive-goods')->assertNotFound();
        $this->get('/help')->assertOk()->assertDontSee('Orders and deliveries.');
    }

    /**
     * Slugs are unique across the whole manual precisely so a moved article
     * keeps working. The old URL redirects rather than 404ing a bookmark.
     */
    public function test_an_article_reached_through_the_wrong_section_redirects_to_its_own(): void
    {
        $other = DocCategory::create([
            'slug' => 'inventory', 'title' => 'Inventory', 'sort_order' => 20, 'is_published' => true,
        ]);

        $this->get('/help/inventory/receive-goods')
            ->assertRedirect('/help/purchasing/receive-goods');
    }

    public function test_search_finds_an_article_by_a_keyword_that_is_not_in_the_prose(): void
    {
        // "GRN" appears only in the keywords field, which is the whole reason
        // that field exists.
        $this->assertStringNotContainsString('GRN', $this->article->body);

        Livewire::test(\App\Livewire\Help\Index::class)
            ->set('q', 'GRN')
            ->assertSee('Receive a delivery');
    }

    public function test_reading_an_article_counts_a_view_without_touching_last_reviewed(): void
    {
        $reviewed = $this->article->updated_at;

        $this->travel(2)->days();
        $this->get('/help/purchasing/receive-goods')->assertOk();
        $this->travelBack();

        $this->article->refresh();

        $this->assertSame(1, $this->article->view_count);
        $this->assertEquals(
            $reviewed->timestamp,
            $this->article->updated_at->timestamp,
            'A page view bumped "Last reviewed", which readers are shown as an editorial date.'
        );
    }

    // ── Editing ────────────────────────────────────────────────────────────

    public function test_the_editor_is_closed_to_a_tenant_user(): void
    {
        $this->actingAs($this->tenantUser)->get('/admin/docs')->assertForbidden();
        $this->actingAs($this->tenantUser)->get('/admin/docs/articles/create')->assertForbidden();
    }

    public function test_an_admin_can_write_and_publish_an_article(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ArticleForm::class)
            ->set('title', 'Raise a purchase order')
            ->assertSet('slug', 'raise-a-purchase-order')
            ->set('doc_category_id', $this->category->id)
            ->set('body', '<p>Pick the supplier, add lines, send.</p>')
            ->set('is_published', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->get('/help/purchasing/raise-a-purchase-order')
            ->assertOk()
            ->assertSee('Pick the supplier', false);
    }

    public function test_a_slug_cannot_be_reused_anywhere_in_the_manual(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ArticleForm::class)
            ->set('title', 'Something else entirely')
            ->set('slug', 'receive-goods')
            ->set('doc_category_id', $this->category->id)
            ->call('save')
            ->assertHasErrors('slug');
    }

    public function test_a_figure_needs_an_article_to_belong_to(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ArticleForm::class)
            ->set('upload_alt', 'A screenshot')
            ->call('addImage')
            ->assertHasErrors('upload');
    }

    public function test_an_uploaded_figure_is_stored_and_placed_in_the_body(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin)
            ->test(ArticleForm::class, ['id' => $this->article->id])
            ->set('upload', UploadedFile::fake()->image('grn.png', 800, 500))
            ->set('upload_alt', 'The goods received form with three lines')
            ->set('upload_caption', 'Receiving a part delivery.')
            ->call('addImage')
            ->assertHasNoErrors()
            // Appended to the body, not silently filed away — an upload button
            // that does nothing visible is one nobody trusts.
            ->assertSet('body', fn ($body) => str_contains($body, '<figure><img src=')
                && str_contains($body, 'The goods received form with three lines'));

        $image = DocImage::where('doc_article_id', $this->article->id)->firstOrFail();

        Storage::disk('public')->assertExists($image->path);
        $this->assertSame('Receiving a part delivery.', $image->caption);
    }

    public function test_alt_text_is_required_on_a_figure(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin)
            ->test(ArticleForm::class, ['id' => $this->article->id])
            ->set('upload', UploadedFile::fake()->image('grn.png'))
            ->call('addImage')
            ->assertHasErrors('upload_alt');
    }

    public function test_deleting_a_figure_removes_the_stored_file(): void
    {
        Storage::fake('public');

        $component = Livewire::actingAs($this->admin)
            ->test(ArticleForm::class, ['id' => $this->article->id])
            ->set('upload', UploadedFile::fake()->image('grn.png'))
            ->set('upload_alt', 'A figure')
            ->call('addImage');

        $image = DocImage::where('doc_article_id', $this->article->id)->firstOrFail();
        $path  = $image->path;

        $component->call('deleteImage', $image->id);

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('doc_images', ['id' => $image->id]);
    }

    /**
     * Sort orders are spaced by ten so a new article can be dropped between
     * two existing ones. Reordering must SWAP, not renumber, or that spacing
     * is thrown away the first time somebody uses the arrows.
     */
    public function test_reordering_swaps_with_the_neighbour_and_keeps_the_spacing(): void
    {
        $second = DocArticle::create([
            'doc_category_id' => $this->category->id,
            'slug' => 'credit-notes', 'title' => 'Credit notes',
            'sort_order' => 20, 'is_published' => true,
        ]);

        Livewire::actingAs($this->admin)
            ->test(DocsIndex::class)
            ->call('moveArticle', $second->id, 'up');

        $this->assertSame(10, $second->fresh()->sort_order);
        $this->assertSame(20, $this->article->fresh()->sort_order);
    }

    public function test_deleting_a_section_takes_its_articles_with_it(): void
    {
        Livewire::actingAs($this->admin)
            ->test(DocsIndex::class)
            ->call('deleteCategory', $this->category->id);

        $this->assertDatabaseMissing('doc_categories', ['id' => $this->category->id]);
        $this->assertDatabaseMissing('doc_articles', ['id' => $this->article->id]);
    }

    // ── Where it is linked from ────────────────────────────────────────────

    public function test_the_manual_is_offered_in_both_the_app_and_the_marketing_site(): void
    {
        $inNav = collect(\App\Support\Navigation\NavMenu::outlet())
            ->flatMap(fn ($group) => $group['items'])
            ->contains(fn ($item) => ($item['route'] ?? null) === 'help.index');

        $this->assertTrue($inNav, 'Signed-in users have no link to the manual.');

        $this->get('/pricing')->assertOk()->assertSee(route('help.index'));
    }
}
