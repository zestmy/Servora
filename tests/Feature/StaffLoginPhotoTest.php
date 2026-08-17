<?php

namespace Tests\Feature;

use App\Livewire\Labels\Staff\Login as LabelsLogin;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The sign-in screen's confirmation step shows the chosen person's face —
 * served by a PRE-AUTH route, because nobody holds a session on the sign-in
 * screen and the session-gated photo route would 403 into initials forever.
 *
 * What this pins is the fence: the route serves photos for EXACTLY the
 * people the login screen already names to the same unauthenticated visitor
 * — this company, active, PIN set and not switched off — and 404s for
 * everyone else. An email-only employee with a photo on file is the case
 * that proves it: their name never appears on the PIN picker, so their face
 * must not be fetchable from it either.
 */
class StaffLoginPhotoTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Employee $pinHolder;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->company = Company::create([
            'name' => 'Doorway Co', 'slug' => Str::slug('Doorway Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Bangsar', 'code' => 'BSR', 'is_active' => true,
        ]);

        $this->pinHolder = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'Mohd Affandy Bin Zulkarnain',
            'photo_path' => 'hr/photos/affandy.jpg', 'is_active' => true,
        ]);
        $this->pinHolder->setLabelPin('4821');

        Storage::disk('local')->put('hr/photos/affandy.jpg', 'jpeg-bytes');
    }

    /** The company the subdomain resolved, the way the login screen holds it. */
    private function onCompany()
    {
        return $this->withSession(['subdomain_company_id' => $this->company->id]);
    }

    public function test_a_pin_holders_photo_is_served_to_the_sign_in_screen(): void
    {
        $this->onCompany()
            ->get(route('clock.staff.login.photo', $this->pinHolder->id))
            ->assertOk();
    }

    /**
     * An email-only employee's name never appears on the PIN picker, so
     * their face must not be fetchable from it either — photo or no photo.
     */
    public function test_an_employee_without_a_pin_is_404_even_with_a_photo(): void
    {
        $emailOnly = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'Ben Ooi', 'email' => 'ben' . uniqid() . '@example.test',
            'photo_path' => 'hr/photos/ben.jpg', 'is_active' => true,
        ]);
        Storage::disk('local')->put('hr/photos/ben.jpg', 'jpeg-bytes');

        $this->onCompany()
            ->get(route('clock.staff.login.photo', $emailOnly->id))
            ->assertNotFound();
    }

    public function test_a_switched_off_pin_takes_the_face_off_the_doorway_too(): void
    {
        $this->pinHolder->forceFill(['pin_login_disabled_at' => now()])->save();

        $this->onCompany()
            ->get(route('clock.staff.login.photo', $this->pinHolder->id))
            ->assertNotFound();
    }

    public function test_another_companys_subdomain_cannot_reach_the_photo(): void
    {
        $rival = Company::create([
            'name' => 'Rival Co', 'slug' => Str::slug('Rival Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->withSession(['subdomain_company_id' => $rival->id])
            ->get(route('clock.staff.login.photo', $this->pinHolder->id))
            ->assertNotFound();
    }

    public function test_no_company_context_means_404(): void
    {
        $this->get(route('clock.staff.login.photo', $this->pinHolder->id))
            ->assertNotFound();
    }

    // ── The screen itself ─────────────────────────────────────────────────

    public function test_the_confirmation_step_wears_the_login_photo_route(): void
    {
        session(['subdomain_company_id' => $this->company->id]);

        $html = Livewire::test(LabelsLogin::class)
            ->set('outletId', $this->outlet->id)
            ->set('employeeId', $this->pinHolder->id)
            ->assertSee('Mohd Affandy Bin Zulkarnain')
            ->html();

        $this->assertStringContainsString(
            route('clock.staff.login.photo', $this->pinHolder->id),
            $html,
            'The face confirms the name before a PIN is typed against it.'
        );
        $this->assertStringNotContainsString(
            route('clock.staff.photo', $this->pinHolder->id) . '"',
            $html,
            'The session-gated route would 403 here — nobody is signed in yet.'
        );
    }

    public function test_no_photo_on_file_still_means_the_initials_disc(): void
    {
        $this->pinHolder->forceFill(['photo_path' => null])->save();

        session(['subdomain_company_id' => $this->company->id]);

        $html = Livewire::test(LabelsLogin::class)
            ->set('outletId', $this->outlet->id)
            ->set('employeeId', $this->pinHolder->id)
            ->assertSee('Mohd Affandy Bin Zulkarnain')
            ->html();

        $this->assertStringNotContainsString('/login/photo/', $html);
        $this->assertStringContainsString('MA', $html, 'The coloured disc carries the initials.');
    }
}
