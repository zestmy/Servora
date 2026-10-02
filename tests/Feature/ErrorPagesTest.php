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
}
