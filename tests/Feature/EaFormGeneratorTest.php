<?php

namespace Tests\Feature;

use App\Livewire\Marketing\EaFormGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The free Borang EA generator.
 *
 * The thing worth protecting here is what it does NOT do: it adds up and lays
 * out, and it never derives a statutory figure. A year's PCB is the twelve
 * deductions actually remitted, and a tool that recalculated it would hand
 * somebody a form that disagrees with their own payslips.
 */
class EaFormGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function filled(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(EaFormGenerator::class)
            ->set('employeeName', 'Nurul Aina binti Rahman')
            ->set('income.salary', 36000)
            ->set('income.allowances', 3600)
            ->set('deductions.pcb', 1200)
            ->set('contributions.epf_employee', 4356);
    }

    public function test_it_defaults_to_the_year_just_finished(): void
    {
        Livewire::test(EaFormGenerator::class)
            ->assertSet('year', (int) now()->subYear()->year);
    }

    /** Exempt allowances are reported on the form and are not part of the gross. */
    public function test_exempt_allowances_are_not_added_into_the_gross(): void
    {
        $component = $this->filled()->set('income.exempt', 5000);

        $figures = $component->instance()->figures();

        $this->assertEqualsWithDelta(39600, $figures['gross'], 0.01);
        $this->assertEqualsWithDelta(5000, $figures['income']['exempt'], 0.01);
    }

    public function test_the_gross_adds_every_taxable_line(): void
    {
        $figures = $this->filled()
            ->set('income.overtime', 2400)
            ->set('income.service_charge', 6000)
            ->set('income.bonus', 3000)
            ->set('income.bik', 1200)
            ->instance()->figures();

        // 36000 + 3600 + 2400 + 6000 + 3000 + 1200
        $this->assertEqualsWithDelta(52200, $figures['gross'], 0.01);
    }

    /** The whole point of the month-by-month mode. */
    public function test_the_monthly_grid_totals_into_the_form(): void
    {
        $component = Livewire::test(EaFormGenerator::class)
            ->set('mode', 'monthly')
            ->set('employeeName', 'Nurul Aina binti Rahman');

        for ($i = 0; $i < 12; $i++) {
            $component->set("months.$i.salary", 3000)
                ->set("months.$i.service_charge", 500)
                ->set("months.$i.pcb", 100)
                ->set("months.$i.epf_employee", 363);
        }

        $figures = $component->instance()->figures();

        $this->assertEqualsWithDelta(36000, $figures['income']['salary'], 0.01);
        $this->assertEqualsWithDelta(6000, $figures['income']['service_charge'], 0.01);
        $this->assertEqualsWithDelta(1200, $figures['deductions']['pcb'], 0.01);
        $this->assertEqualsWithDelta(4356, $figures['contributions']['epf_employee'], 0.01);
        $this->assertSame(12, $figures['months_paid']);
    }

    /** A part-year starter is the normal case, not an edge case. */
    public function test_months_with_nothing_entered_are_not_counted(): void
    {
        $component = Livewire::test(EaFormGenerator::class)->set('mode', 'monthly');

        foreach ([6, 7, 8, 9, 10, 11] as $i) {
            $component->set("months.$i.salary", 2500);
        }

        $figures = $component->instance()->figures();

        $this->assertSame(6, $figures['months_paid']);
        $this->assertEqualsWithDelta(15000, $figures['income']['salary'], 0.01);
    }

    /**
     * A bonus is paid once, so it stays an annual field. Switching to the grid
     * must not throw it away.
     */
    public function test_once_a_year_figures_survive_the_monthly_grid(): void
    {
        $component = Livewire::test(EaFormGenerator::class)
            ->set('mode', 'monthly')
            ->set('income.bonus', 4000)
            ->set('deductions.zakat', 600);

        $component->set('months.0.salary', 3000);

        $figures = $component->instance()->figures();

        $this->assertEqualsWithDelta(7000, $figures['gross'], 0.01);
        $this->assertEqualsWithDelta(600, $figures['deductions']['zakat'], 0.01);
    }

    /** Nothing here is derived. Enter no PCB and the form says no PCB. */
    public function test_it_never_invents_a_statutory_figure(): void
    {
        $figures = Livewire::test(EaFormGenerator::class)
            ->set('income.salary', 60000)
            ->instance()->figures();

        $this->assertSame(0.0, $figures['deductions']['pcb']);
        $this->assertSame(0.0, $figures['contributions']['epf_employee']);
    }

    public function test_moving_the_year_moves_an_untouched_employment_period(): void
    {
        Livewire::test(EaFormGenerator::class)
            ->set('year', 2024)
            ->assertSet('employedFrom', '2024-01-01')
            ->assertSet('employedTo', '2024-12-31');
    }

    /** Somebody who typed a join date meant it. */
    public function test_a_typed_employment_date_survives_a_year_change(): void
    {
        Livewire::test(EaFormGenerator::class)
            ->set('employedFrom', '2025-04-15')
            ->set('year', 2024)
            ->assertSet('employedFrom', '2025-04-15')
            ->assertSet('employedTo', '2024-12-31');
    }

    public function test_the_pdf_downloads(): void
    {
        RateLimiter::clear('ea-form-pdf:ip:127.0.0.1');

        $download = $this->filled()->call('downloadPdf')->effects['download'] ?? null;

        $this->assertNotNull($download, 'The button must actually return a file.');
    }

    public function test_a_form_without_a_name_on_it_is_refused(): void
    {
        RateLimiter::clear('ea-form-pdf:ip:127.0.0.1');

        Livewire::test(EaFormGenerator::class)
            ->set('income.salary', 36000)
            ->call('downloadPdf')
            ->assertSet('downloadError', 'An EA form needs the employee\'s name on it.');
    }

    public function test_three_downloads_a_day_and_then_it_stops(): void
    {
        RateLimiter::clear('ea-form-pdf:ip:127.0.0.1');

        for ($i = 0; $i < 3; $i++) {
            $this->filled()->call('downloadPdf')->assertSet('downloadError', '');
        }

        $this->filled()->call('downloadPdf')->assertSee('3 downloads today');
    }

    public function test_the_page_says_it_is_not_the_official_form(): void
    {
        $this->get(route('tools.ea-form'))
            ->assertOk()
            ->assertSee('not LHDN', escape: false);
    }
}
