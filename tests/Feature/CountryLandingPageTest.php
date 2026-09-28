<?php

namespace Tests\Feature;

use App\Livewire\Admin\LandingPageForm;
use App\Livewire\Admin\LandingPages;
use App\Models\LandingPage;
use App\Models\User;
use App\Support\Marketing\HomeCopy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The marketing home page in a local language (App\Models\LandingPage,
 * Admin › Country Pages), and the copy map it overrides (HomeCopy).
 */
class CountryLandingPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every $t('key') in the home view must exist in HomeCopy, or the page
     * prints the key itself and no admin can translate it.
     */
    public function test_every_key_the_home_page_uses_is_in_the_copy_map(): void
    {
        $view = file_get_contents(resource_path('views/livewire/marketing/home.blade.php'));
        preg_match_all("/\\\$t\\(['\"]([a-z_]+\\.[a-z0-9_]+)['\"]/", $view, $m);

        $this->assertNotEmpty($m[1]);
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), array_keys(HomeCopy::defaults()))));
    }

    public function test_the_english_home_page_renders_every_string_from_the_map(): void
    {
        $html = $this->get('/?lang=en')->assertOk()->getContent();

        $this->assertStringContainsString('Know your food cost', $html);
        $this->assertStringNotContainsString('hero.title', $html, 'no raw keys on the page');
        $this->assertDoesNotMatchRegularExpression('/(?<![\w\/]):(days|basic|full|hr|ck|addon|target)\b/', strip_tags($html), 'every placeholder filled');
    }

    public function test_a_published_page_serves_its_translation_and_falls_back_to_english(): void
    {
        $this->page(['strings' => ['hero.title' => 'Ketahui biaya makanan Anda', 'cta.trial' => 'Coba gratis :days hari', 'nav.pricing' => 'Harga']]);

        $this->get('/id')->assertOk()
            ->assertSee('<html lang="id"', false)
            ->assertSee('Ketahui biaya makanan Anda')
            ->assertSee('Coba gratis 14 hari')
            ->assertSee('Harga')
            ->assertSee('before month end', false)      // untranslated key: English
            ->assertSee('hreflang="id"', false);
    }

    public function test_a_draft_is_only_visible_to_system_admins(): void
    {
        $this->page(['is_published' => false]);

        $this->get('/id')->assertNotFound();
        $this->actingAs($this->admin())->get('/id')->assertOk();
    }

    public function test_visitors_from_the_country_are_redirected_unless_they_chose_english(): void
    {
        $this->page(['auto_redirect' => true]);

        $this->get('/', ['CF-IPCountry' => 'ID'])->assertRedirect('/id');
        $this->get('/', ['CF-IPCountry' => 'SG'])->assertOk();

        $this->get('/?lang=en', ['CF-IPCountry' => 'ID'])->assertOk()->assertCookie('mk_lang', 'en');
        $this->withCookie('mk_lang', 'en')->get('/', ['CF-IPCountry' => 'ID'])->assertOk();
    }

    public function test_an_admin_cannot_take_a_slug_a_real_page_uses(): void
    {
        Livewire::actingAs($this->admin())->test(LandingPages::class)
            ->set('language_name', 'Up')->set('locale', 'en')->set('slug', 'up')->set('countries', 'ID')
            ->call('create')->assertHasErrors('slug');

        Livewire::actingAs($this->admin())->test(LandingPages::class)
            ->call('usePreset', 'Bahasa Indonesia')->call('create')
            ->assertRedirect(route('admin.landing-pages.edit', LandingPage::firstWhere('slug', 'id')->id));
    }

    public function test_a_translation_must_keep_its_placeholders(): void
    {
        $page = $this->page();

        Livewire::actingAs($this->admin())->test(LandingPageForm::class, ['id' => $page->id])
            ->set('strings.cta__trial', 'Coba gratis')
            ->call('save')->assertHasErrors('strings.cta__trial')
            ->set('strings.cta__trial', 'Coba gratis :days hari')
            ->call('save')->assertHasNoErrors();

        $this->assertSame('Coba gratis :days hari', $page->fresh()->strings['cta.trial']);
    }

    public function test_the_ai_draft_fills_only_blank_boxes_and_keeps_placeholders(): void
    {
        \App\Models\AppSetting::set('openrouter_api_key', 'test');
        $page = $this->page(['strings' => ['hero.title' => 'Sudah diterjemahkan']]);

        Http::fake(['openrouter.ai/*' => function ($request) {
            $in = json_decode($request['messages'][1]['content'], true);
            $out = array_map(fn ($en) => 'ID: '.$en, $in);
            if (isset($out['cta.trial'])) {
                $out['cta.trial'] = 'ID tanpa placeholder'; // dropped :days — must be refused
            }

            return Http::response(['choices' => [['message' => ['content' => json_encode($out)]]]]);
        }]);

        // The queue is synchronous under test: every batch runs inside startAi.
        $test = Livewire::actingAs($this->admin())->test(LandingPageForm::class, ['id' => $page->id])->call('startAi');

        $this->assertFalse($test->get('aiRunning'));
        $this->assertSame(count(HomeCopy::defaults()) - 1, $test->get('aiTotal'));
        $this->assertSame([], $page->fresh()->strings['hero.see'] ?? [], 'a draft is not saved until the admin saves');

        $strings = $test->instance()->strings;
        $this->assertSame('Sudah diterjemahkan', $strings['hero__title'], 'a filled box is not overwritten');
        $this->assertSame('ID: See it work', $strings['hero__see']);
        $this->assertSame('', $strings['cta__trial'], 'a translation that lost :days is not used');
    }

    public function test_a_failing_ai_request_reports_instead_of_hanging(): void
    {
        \App\Models\AppSetting::set('openrouter_api_key', 'test');
        $page = $this->page();
        Http::fake(['openrouter.ai/*' => Http::response(['error' => ['message' => 'Rate limited']], 429)]);

        Livewire::actingAs($this->admin())->test(LandingPageForm::class, ['id' => $page->id])
            ->call('startAi')
            ->assertSet('aiRunning', false)
            ->assertSee('Rate limited');
    }

    private function page(array $attributes = []): LandingPage
    {
        return LandingPage::create(array_merge([
            'slug' => 'id', 'locale' => 'id', 'language_name' => 'Bahasa Indonesia',
            'countries' => ['ID'], 'is_published' => true, 'strings' => [],
        ], $attributes));
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['company_id' => null]);
        setPermissionsTeamId(null);
        $admin->assignRole(Role::findOrCreate('Super Admin', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $admin;
    }
}
