<?php

namespace Tests\Feature;

use App\Livewire\Recipes\Form;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\RecipePriceClass;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A recipe photo the form will not take is a message, not a crash.
 *
 * REPORTED AS: "error 500 when uploading image for recipe". The upload leg
 * worked — POST /livewire/upload-file answered 200 — and the component died on
 * the render straight after, so the browser got a 500 from /livewire/update and
 * the user got nothing but a dead form.
 *
 * Per-file errors are recorded under an indexed key (newDineInImages.0), so the
 * form reads them with the wildcard $errors->get('newDineInImages.*'). A
 * wildcard lookup comes back nested — one array of messages per matching key —
 * and <x-input-error> was rendering each element straight into {{ }}. Printing
 * an array is htmlspecialchars(array): a 500 every time a photo was rejected.
 *
 * Whoever hits this is doing nothing exotic — a phone photo over 5 MB is the
 * ordinary case, not the edge one.
 */
class RecipeImageUploadErrorTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;
    private UnitOfMeasure $each;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Photo Co', 'slug' => Str::slug('Photo Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MN', 'is_active' => true,
        ]);

        $this->each = UnitOfMeasure::first() ?? UnitOfMeasure::create([
            'name' => 'Each', 'abbreviation' => 'ea', 'type' => 'count', 'base_factor' => 1,
        ]);

        RecipePriceClass::create([
            'company_id' => $this->company->id, 'name' => 'Dine In', 'sort_order' => 1, 'is_default' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$outlet->id]);
        session(['selected_outlet_id' => $outlet->id]);

        setPermissionsTeamId($this->company->id);
        foreach (['recipes.view', 'recipes.manage', 'reports.view'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        $this->user->givePermissionTo(['recipes.view', 'recipes.manage', 'reports.view']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_an_oversized_dine_in_photo_shows_a_message_instead_of_a_500(): void
    {
        // 6 MB — over the 5120 KB rule, and a wholly ordinary phone photo.
        $tooBig = UploadedFile::fake()->image('nasi-lemak.jpg')->size(6 * 1024);

        Livewire::actingAs($this->user)->test(Form::class)
            ->set('name', 'Nasi Lemak')
            ->set('yield_uom_id', $this->each->id)
            ->set('yield_quantity', '1')
            ->set('selling_price', '10')
            ->set('newDineInImages', [$tooBig])
            ->call('save')
            ->assertHasErrors('newDineInImages.0')
            // The render is the part that used to throw. Reaching an assertion
            // on the output at all means the 500 is gone.
            ->assertSee('Each dine-in photo must be 5 MB or smaller.')
            ->assertDontSee('newDineInImages.0');
    }

    public function test_two_bad_photos_both_get_reported(): void
    {
        Livewire::actingAs($this->user)->test(Form::class)
            ->set('name', 'Rendang')
            ->set('yield_uom_id', $this->each->id)
            ->set('yield_quantity', '1')
            ->set('selling_price', '10')
            ->set('newDineInImages', [
                UploadedFile::fake()->image('a.jpg')->size(6 * 1024),
                UploadedFile::fake()->image('b.jpg')->size(7 * 1024),
            ])
            ->call('save')
            ->assertHasErrors(['newDineInImages.0', 'newDineInImages.1'])
            ->assertSee('Each dine-in photo must be 5 MB or smaller.');
    }

    public function test_a_takeaway_photo_is_reported_the_same_way(): void
    {
        Livewire::actingAs($this->user)->test(Form::class)
            ->set('name', 'Satay')
            ->set('yield_uom_id', $this->each->id)
            ->set('yield_quantity', '1')
            ->set('selling_price', '10')
            ->set('newTakeawayImages', [UploadedFile::fake()->image('satay.jpg')->size(6 * 1024)])
            ->call('save')
            ->assertHasErrors('newTakeawayImages.0')
            ->assertSee('Each takeaway photo must be 5 MB or smaller.');
    }

    public function test_a_photo_within_the_limit_is_accepted(): void
    {
        Livewire::actingAs($this->user)->test(Form::class)
            ->set('name', 'Laksa')
            ->set('yield_uom_id', $this->each->id)
            ->set('yield_quantity', '1')
            ->set('selling_price', '10')
            ->set('newDineInImages', [UploadedFile::fake()->image('laksa.jpg')->size(200)])
            ->call('save')
            ->assertHasNoErrors();
    }
}
