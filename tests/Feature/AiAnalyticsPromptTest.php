<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Outlet;
use App\Models\SalesRecord;
use App\Models\User;
use App\Services\AiAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * What the AI is actually told.
 *
 * A report was asked for comparing 1–9 Aug 2026 against 1–9 Jul 2026 and
 * 1–9 Aug 2025, and came back weak. The persona was not the problem: the
 * context carried the WHOLE previous month and nothing at all from last year,
 * so the model was handed nine days against thirty-one and asked about a year
 * it could not see. Every percentage available to it was wrong by the ratio of
 * the two spans, and any year-on-year statement had to be invented.
 *
 * These tests hold the data side, because that is the half no prompt can fix.
 */
class AiAnalyticsPromptTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Dotty Co', 'slug' => Str::slug('Dotty Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $user = User::factory()->create(['company_id' => $this->company->id, 'outlet_id' => $this->outlet->id]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->actingAs($user);
    }

    private function sale(string $date, float $revenue, int $pax = 10): void
    {
        SalesRecord::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'sale_date'  => $date,
            'total_revenue' => $revenue,
            'gross_revenue' => $revenue,
            'total_cost' => $revenue * 0.3,
            'pax' => $pax,
            'transactions' => $pax,
        ]);
    }

    private function promptFor(string $period): string
    {
        $service = app(AiAnalyticsService::class);

        $build = new \ReflectionMethod($service, 'buildContext');
        $build->setAccessible(true);
        $context = $build->invoke($service, $period, $this->outlet->id, null);

        return $service->buildPrompt($context, 'monthly_review', null);
    }

    /**
     * The point of the whole change: nine days of August must be compared with
     * the same nine days of July and of last August, by date.
     */
    public function test_the_prompt_compares_the_same_days_of_the_month(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-08-09'));

        $this->sale('2026-08-05', 5000);   // in window
        $this->sale('2026-07-05', 4000);   // same days last month
        $this->sale('2026-07-25', 9999);   // LATER in July — must not be counted
        $this->sale('2025-08-05', 3000);   // same days last year

        $prompt = $this->promptFor('2026-08');

        $this->assertStringContainsString('Like-for-like comparison', $prompt);
        $this->assertStringContainsString('1 Aug – 9 Aug 2026', $prompt);
        $this->assertStringContainsString('1 Jul – 9 Jul 2026', $prompt);
        $this->assertStringContainsString('1 Aug – 9 Aug 2025', $prompt);

        // RM 4,000 for the matched window, excluding the rest of July.
        $this->assertStringContainsString('RM 4,000.00', $prompt);

        // The whole-month figure (4,000 + 9,999) is still in the prompt and is
        // legitimately useful — what matters is that it cannot be mistaken for
        // the matched one, which is what produced the bad report.
        $this->assertStringContainsString('FULL calendar months', $prompt);
        $this->assertStringContainsString('do NOT quote a percentage from it', $prompt);

        $this->travelBack();
    }

    public function test_it_states_the_percentage_against_the_matched_window(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-08-09'));

        $this->sale('2026-08-05', 5000);
        $this->sale('2026-07-05', 4000);   // +25%
        $this->sale('2025-08-05', 2500);   // +100%

        $prompt = $this->promptFor('2026-08');

        $this->assertStringContainsString('Revenue vs same days last month: +25.0%', $prompt);
        $this->assertStringContainsString('Revenue vs same days last year: +100.0%', $prompt);

        $this->travelBack();
    }

    /**
     * An empty comparison window must be named as empty. A blank cell is an
     * invitation to fill it in, which is exactly what went wrong.
     */
    public function test_an_empty_comparison_window_is_called_out_rather_than_left_blank(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-08-09'));

        $this->sale('2026-08-05', 5000);   // nothing last month, nothing last year

        $prompt = $this->promptFor('2026-08');

        $this->assertStringContainsString('no sales recorded in that window', $prompt);
        $this->assertStringContainsString('rather than estimating', $prompt);

        $this->travelBack();
    }

    public function test_the_system_prompt_briefs_a_coo_not_an_analyst(): void
    {
        $service = app(AiAnalyticsService::class);

        $method = new \ReflectionMethod($service, 'systemPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($service, [
            'company_name' => 'Dotty Co',
            'outlet_name'  => 'KLCC',
        ]);

        $this->assertStringContainsString('Chief Operating Officer', $prompt);
        $this->assertStringContainsString('NEVER estimate', $prompt);
        $this->assertStringContainsString('Compare like with like', $prompt);
        $this->assertStringContainsString('ringgit before percentages', $prompt);
    }
}
