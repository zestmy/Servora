<?php

namespace Tests\Feature;

use App\Livewire\Settings\FormTemplates;
use App\Models\Company;
use App\Models\FormTemplate;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Searching the Form Templates list.
 *
 * A company that runs a template per section ends up with dozens of them, and
 * the type tabs only ever narrow the list to a third. The search is what makes
 * the page usable at that size, so what matters is that it narrows without
 * losing the type tab, that it reads the description as well as the name (the
 * name is often just "Bar 2" and the description is where the real words are),
 * and that it never reaches across companies.
 */
class FormTemplateSearchTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->makeCompany('Search Co');

        $outlet = Outlet::create([
            'company_id' => $this->company->id,
            'name'       => 'Main',
            'code'       => 'MAIN',
            'is_active'  => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'outlet_id'  => $outlet->id,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$outlet->id]);

        $this->actingAs($this->user);
    }

    private function makeCompany(string $name): Company
    {
        return Company::create([
            'name'      => $name,
            'slug'      => Str::slug($name) . '-' . uniqid(),
            'currency'  => 'MYR',
            'is_active' => true,
        ]);
    }

    private function template(string $name, string $type = 'stock_take', ?string $description = null, ?int $companyId = null): FormTemplate
    {
        return FormTemplate::create([
            'company_id'  => $companyId ?? $this->company->id,
            'name'        => $name,
            'form_type'   => $type,
            'description' => $description,
            'is_active'   => true,
        ]);
    }

    private function names($component): array
    {
        return $component->viewData('templates')->pluck('name')->all();
    }

    public function test_search_narrows_the_list_by_name(): void
    {
        $this->template('Bar Section');
        $this->template('Hot Kitchen');
        $this->template('Pastry Kiosk');

        $screen = Livewire::actingAs($this->user)->test(FormTemplates::class)->set('search', 'kitchen');

        $this->assertSame(['Hot Kitchen'], $this->names($screen));
    }

    public function test_search_also_matches_the_description(): void
    {
        $this->template('Bar 2', 'stock_take', 'Rooftop cocktail station');
        $this->template('Bar 3', 'stock_take', 'Poolside beer fridge');

        $screen = Livewire::actingAs($this->user)->test(FormTemplates::class)->set('search', 'cocktail');

        $this->assertSame(['Bar 2'], $this->names($screen));
    }

    public function test_search_is_case_insensitive_and_matches_mid_word(): void
    {
        $this->template('Hot Kitchen');

        $screen = Livewire::actingAs($this->user)->test(FormTemplates::class)->set('search', 'ITCHE');

        $this->assertSame(['Hot Kitchen'], $this->names($screen));
    }

    public function test_search_and_the_type_tab_narrow_together(): void
    {
        $this->template('Bar Section', 'stock_take');
        $this->template('Bar Section', 'purchase_order');

        $screen = Livewire::actingAs($this->user)->test(FormTemplates::class)
            ->set('typeFilter', 'purchase_order')
            ->set('search', 'bar');

        $this->assertCount(1, $screen->viewData('templates'));
        $this->assertSame('purchase_order', $screen->viewData('templates')->first()->form_type);
    }

    public function test_search_does_not_reach_into_another_company(): void
    {
        $other = $this->makeCompany('Rival Co');
        $this->template('Rival Bar', 'stock_take', null, $other->id);
        $this->template('Our Bar');

        $screen = Livewire::actingAs($this->user)->test(FormTemplates::class)->set('search', 'bar');

        $this->assertSame(['Our Bar'], $this->names($screen));
    }

    public function test_an_empty_result_offers_to_clear_the_filters(): void
    {
        $this->template('Bar Section');

        $screen = Livewire::actingAs($this->user)->test(FormTemplates::class)->set('search', 'no-such-template');

        $screen->assertSee('No templates match your search')
               ->assertDontSee('No templates yet');

        $screen->call('clearFilters');

        $this->assertSame('', $screen->get('search'));
        $this->assertSame('', $screen->get('typeFilter'));
        $this->assertSame(['Bar Section'], $this->names($screen));
    }
}
