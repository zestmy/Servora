<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\LmsUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Every company runs its own training portal, so one person's email must be
 * able to hold an LMS account in more than one company.
 */
class LmsMultiCompanyEmailTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;
    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create(['name' => 'Alpha', 'slug' => 'alpha-' . uniqid(), 'currency' => 'MYR', 'is_active' => true]);
        $this->companyB = Company::create(['name' => 'Beta', 'slug' => 'beta-' . uniqid(), 'currency' => 'MYR', 'is_active' => true]);
    }

    private function trainee(Company $company, string $password, string $status = 'approved'): LmsUser
    {
        return LmsUser::create([
            'company_id' => $company->id,
            'name'       => 'Aisyah',
            'email'      => 'aisyah@example.test',
            'password'   => $password,
            'status'     => $status,
        ]);
    }

    private function registerPayload(): array
    {
        return [
            'name' => 'Aisyah', 'email' => 'aisyah@example.test',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ];
    }

    public function test_an_email_registered_in_one_company_can_register_in_another(): void
    {
        $this->trainee($this->companyA, 'first-pass');

        $this->post("/lms/{$this->companyB->slug}/register", $this->registerPayload())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, LmsUser::where('company_id', $this->companyB->id)->where('email', 'aisyah@example.test')->count());
        $this->assertSame(2, LmsUser::where('email', 'aisyah@example.test')->count());
    }

    public function test_the_same_email_is_still_unique_within_one_company(): void
    {
        $this->trainee($this->companyA, 'first-pass');

        $this->post("/lms/{$this->companyA->slug}/register", $this->registerPayload())
            ->assertSessionHasErrors('email');

        $this->assertSame(1, LmsUser::where('email', 'aisyah@example.test')->count());
    }

    public function test_login_checks_the_password_of_this_company_s_account(): void
    {
        $this->trainee($this->companyA, 'alpha-pass');
        $beta = $this->trainee($this->companyB, 'beta-pass');

        // Company A's password must not open company B's portal…
        $this->post("/lms/{$this->companyB->slug}/login", ['email' => 'aisyah@example.test', 'password' => 'alpha-pass'])
            ->assertSessionHasErrors('email');
        $this->assertFalse(Auth::guard('lms')->check());

        // …and B's own password signs in to B's account, not A's.
        $this->post("/lms/{$this->companyB->slug}/login", ['email' => 'aisyah@example.test', 'password' => 'beta-pass'])
            ->assertRedirect(route('lms.dashboard'));
        $this->assertSame($beta->id, Auth::guard('lms')->id());
    }

    public function test_a_session_in_another_company_does_not_block_this_portal_s_login_page(): void
    {
        $alpha = $this->trainee($this->companyA, 'alpha-pass');

        // Log in on the lms guard directly — actingAs() would also make it the
        // default guard, which real LMS requests never do.
        Auth::guard('lms')->login($alpha);

        $this->get("/lms/{$this->companyB->slug}/login")
            ->assertOk();

        $this->get("/lms/{$this->companyA->slug}/login")
            ->assertRedirect(route('lms.dashboard'));
    }
}
