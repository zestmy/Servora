<?php

namespace Tests\Feature;

use App\Livewire\Labels\Staff\PrintLabels;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Label PWA's print screen opens with a greeting — face and FULL name,
 * the same as the Clock home screen and full for the same hard-learned
 * reason (cutting at the first space greeted MOHD AFFANDY BIN ZULKARNAIN
 * as "MOHD"). It matters more here than most: whoever is greeted is
 * stamped as "Prepared by" on every label printed, so the greeting doubles
 * as "you are printing as".
 */
class LabelStaffPrintGreetingTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::create([
            'name'      => 'Print Greeting Co',
            'slug'      => Str::slug('Print Greeting Co') . '-' . uniqid(),
            'currency'  => 'MYR',
            'is_active' => true,
        ]);

        $outlet = Outlet::create([
            'company_id' => $company->id, 'name' => 'Bangsar', 'code' => 'BSR', 'is_active' => true,
        ]);

        $this->employee = Employee::create([
            'company_id' => $company->id,
            'outlet_id'  => $outlet->id,
            'name'       => 'Mohd Affandy Bin Zulkarnain',
            'email'      => 'affandy' . uniqid() . '@example.test',
            'is_active'  => true,
        ]);

        // A PIN session, not a guard — there is no actingAs() for this.
        session(['subdomain_company_id' => $company->id]);
        app(\App\Services\Staff\StaffSession::class)->signIn($this->employee, 'email');
    }

    public function test_the_greeting_carries_the_photo_and_the_full_name(): void
    {
        $this->employee->update(['photo_path' => 'hr/photos/affandy.jpg']);

        $html = Livewire::test(PrintLabels::class)
            // The WHOLE name — "Mohd" alone is the bug, not a greeting.
            ->assertSee('Mohd Affandy Bin Zulkarnain')
            ->assertSeeInOrder(['Good', 'Mohd Affandy Bin Zulkarnain'])
            ->html();

        $this->assertStringContainsString(route('clock.staff.photo', $this->employee->id), $html);
    }

    public function test_no_photo_on_file_still_means_the_initials_disc(): void
    {
        $html = Livewire::test(PrintLabels::class)
            ->assertSee('Mohd Affandy Bin Zulkarnain')
            ->html();

        $this->assertStringNotContainsString(
            route('clock.staff.photo', $this->employee->id),
            $html,
            'No photo on file must mean no img at all — the disc is the avatar.'
        );
        $this->assertStringContainsString('MA', $html, 'The coloured disc carries the initials.');
    }
}
