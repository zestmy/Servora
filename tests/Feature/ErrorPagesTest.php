<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The custom error pages in resources/views/errors are what a visitor sees
 * when something goes wrong, so each one must actually be the page served.
 * 500 matters most: it renders while the app is failing, so it must not
 * itself depend on anything that could be the thing that broke.
 */
class ErrorPagesTest extends TestCase
{
    public function test_a_missing_page_shows_the_kitchen_404(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee('This dish isn', escape: false);
    }

    public function test_a_forbidden_page_shows_the_kitchen_403(): void
    {
        Route::get('/__test/forbidden', fn () => abort(403));

        $this->get('/__test/forbidden')
            ->assertForbidden()
            ->assertSee('Kitchen crew only past this door');
    }

    public function test_an_expired_page_shows_the_kitchen_419_and_sends_the_user_back(): void
    {
        // Laravel skips CSRF checks in tests, so raise the 419 directly.
        Route::get('/__test/expired', fn () => abort(419));

        $this->get('/__test/expired')
            ->assertStatus(419)
            ->assertSee('Your order went cold')
            ->assertSee('Take me back now')
            // The redirect back is the point of this page: keep it.
            ->assertSee('window.location.replace(back)', escape: false);
    }

    public function test_a_server_error_shows_the_kitchen_500(): void
    {
        config(['app.debug' => false]);
        Route::get('/__test/boom', fn () => throw new \RuntimeException('secret internals'));

        $this->get('/__test/boom')
            ->assertStatus(500)
            ->assertSee('Something burned in the kitchen')
            ->assertSee('href="/dashboard"', escape: false)
            // Never the exception message: that is for the log, not the page.
            ->assertDontSee('secret internals');
    }

    public function test_maintenance_mode_shows_the_kitchen_503(): void
    {
        // The same command deploy/update.sh runs, snapshot and all.
        $this->artisan('down', ['--refresh' => 15, '--render' => 'errors::503'])->assertExitCode(0);

        try {
            $this->get('/')
                ->assertStatus(503)
                ->assertSee('86&rsquo;d for a moment', escape: false)
                ->assertSee('http-equiv="refresh" content="15"', escape: false)
                // Served while the build is being replaced: nothing from it.
                ->assertDontSee('/build/', escape: false);
        } finally {
            $this->artisan('up');
        }
    }
}
