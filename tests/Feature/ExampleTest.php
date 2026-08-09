<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    // The marketing home page reads `plans`, so it needs a migrated database.
    // RefreshDatabase was commented out in the Laravel skeleton and never
    // reinstated once the page started querying, so this asserted 200 against a
    // database with no tables and got a 500.
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
