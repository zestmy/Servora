<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Support\AiModels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * App\Support\AiModels is the one list of which model each AI feature uses,
 * and Settings › API Keys shows it. That page is only true while no feature
 * names a model anywhere else.
 */
class AiModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_model_is_named_outside_the_registry(): void
    {
        $offenders = [];
        foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
            if ($file->getRealPath() === realpath(app_path('Support/AiModels.php'))) {
                continue;
            }
            if (preg_match("#['\"](anthropic|google|openai|deepseek|meta-llama|mistralai|x-ai|qwen)/[\\w.:-]+['\"]#", $file->getContents(), $m)) {
                $offenders[] = $file->getRelativePathname().': '.$m[0];
            }
        }

        $this->assertSame([], $offenders, 'Use AiModels::for() so Settings › API Keys stays true.');
    }

    public function test_every_feature_key_used_in_app_exists(): void
    {
        $used = [];
        foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
            preg_match_all("/AiModels::for\\('([a-z_]+)'\\)/", $file->getContents(), $m);
            $used = array_merge($used, $m[1]);
        }

        $this->assertNotEmpty($used);
        $this->assertSame([], array_values(array_diff(array_unique($used), array_keys(AiModels::FEATURES))));
    }

    public function test_only_configured_features_follow_the_setting(): void
    {
        AppSetting::set('openrouter_model', 'deepseek/deepseek-r1');

        $this->assertSame('deepseek/deepseek-r1', AiModels::for('report_insights'));
        $this->assertSame(AiModels::SONNET, AiModels::for('translation'));
        $this->assertSame(AiModels::GEMINI_FLASH, AiModels::for('recipe_extract'));

        AppSetting::set('openrouter_model', null);
        $this->assertSame(AiModels::FALLBACK, AiModels::for('report_insights'));
    }

    public function test_the_settings_page_lists_each_feature_and_warns_about_a_reasoning_model(): void
    {
        AppSetting::set('openrouter_model', 'deepseek/deepseek-r1');
        $admin = User::factory()->create(['company_id' => null]);
        setPermissionsTeamId(null);
        $admin->assignRole(Role::findOrCreate('Super Admin', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($admin)->get('/settings/api-keys')->assertOk()
            ->assertSee('Which model each feature uses')
            ->assertSee('Country page translation')
            ->assertSee('Follows this setting')
            ->assertSee('is a reasoning model');
    }
}
