<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The two downloads pages read one catalog (DownloadCatalog) whose entries
 * only surface files actually hosted in public/downloads/. These tests hold
 * what matters regardless of hosting state: both pages render, the catalog's
 * tools are described, and the public page never renders an admin hint.
 */
class DownloadsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_download_page_renders_and_describes_the_tools(): void
    {
        $response = $this->get('/download');

        $response->assertOk();
        // Entries without hosted files are hidden on the public page, so
        // assert the frame rather than any one tool.
        $response->assertSee('Connect your outlets to Servora');
        $response->assertDontSee('ask your administrator');
    }

    public function test_settings_downloads_renders_for_any_signed_in_user(): void
    {
        $company = Company::create([
            'name' => 'DL Co', 'slug' => Str::slug('DL Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $outlet = Outlet::create([
            'company_id' => $company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id, 'outlet_id' => $outlet->id,
        ]);
        $user->companies()->syncWithoutDetaching([$company->id]);
        $user->outlets()->syncWithoutDetaching([$outlet->id]);

        $response = $this->actingAs($user)->get('/settings/downloads');

        $response->assertOk();
        // Settings shows every catalog entry, hosted or not — the person
        // reading it may be the one who has to go publish the zip.
        $response->assertSee('POS Sync Agent');
        $response->assertSee('Print Agent');
    }
}
