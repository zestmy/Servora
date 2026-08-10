<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke tests for the public marketing pages.
 *
 * There were none, which is how the footer's Product column grew to nine links
 * and the Features index ended up clipping its first and last entries: nothing
 * ever looked at these pages except a person, occasionally.
 *
 * These do not police layout — a test cannot see "neat". They hold the two
 * things that actually broke: every page still renders (a bad route name in a
 * shared layout takes the whole marketing site down, not one page), and every
 * link the footer promises is really there.
 */
class MarketingPagesTest extends TestCase
{
    use RefreshDatabase;

    public static function pageProvider(): array
    {
        return [
            'home'          => ['/'],
            'features'      => ['/features'],
            'pricing'       => ['/pricing'],
            'marketplace'   => ['/marketplace'],
            'for suppliers' => ['/for-suppliers'],
            'free tools'    => ['/tools'],
            'ea form'       => ['/tools/ea-form-generator'],
        ];
    }

    /** @dataProvider pageProvider */
    public function test_the_public_pages_render(string $path): void
    {
        $this->get($path)->assertOk();
    }

    public function test_the_footer_carries_every_free_tool(): void
    {
        $response = $this->get('/');

        foreach (['tools.index', 'tools.recipe-cost', 'tools.food-cost', 'tools.menu-matrix', 'tools.salary', 'tools.ea-form'] as $name) {
            $response->assertSee(route($name), escape: false);
        }
    }

    public function test_the_footer_carries_the_product_pages(): void
    {
        $response = $this->get('/');

        foreach (['features', 'pricing', 'marketplace', 'for-suppliers', 'referral.program'] as $name) {
            $response->assertSee(route($name), escape: false);
        }
    }

    /**
     * Twelve areas, twelve jump links. The strip used to hide the overflow, so
     * a missing entry looked exactly like a scrolled one.
     */
    public function test_the_features_index_lists_every_area(): void
    {
        $response = $this->get('/features');

        foreach ([
            'Ingredients and recipe costing',
            'AI document capture and analysis',
            'Purchasing, RFQ and receiving',
            'Inventory and stock control',
            'Central kitchen and production',
            'Food safety labelling',
            'Sales and revenue',
            'Reports and analytics',
            'People, attendance and claims',
            'Training portal',
            'Supplier portal and marketplace',
            'Multi-outlet, roles and control',
        ] as $title) {
            $response->assertSee($title, escape: false);
        }
    }
}
