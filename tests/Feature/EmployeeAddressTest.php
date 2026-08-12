<?php

namespace Tests\Feature;

use App\Livewire\Hr\EmployeeForm;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * An employee's home address, and where post goes when that differs.
 *
 * The record held an address for the emergency CONTACT but none for the
 * employee, so anything that has to be posted — an EA form, a letter of
 * employment — had nowhere to go.
 *
 * TWO COLUMNS, NOT THREE. `mailing_address` is NULL when post goes to the home
 * address and holds a different address when it does not. A
 * `mailing_same_as_home` boolean beside it would be a second fact that can
 * contradict the first, and then every reader has to decide which to believe.
 * Employee::mailingAddress() is the one place that resolves it.
 */
class EmployeeAddressTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;

    private const HOME = '12 Jalan Damai, Taman Sri Muda, 40400 Shah Alam, Selangor';
    private const POST = 'PO Box 88, 46700 Petaling Jaya, Selangor';

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Addr Co', 'slug' => Str::slug('Addr Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        foreach (['hr.view', 'hr.employees.manage'] as $a) {
            Permission::findOrCreate($a, 'web');
        }
        $this->user->givePermissionTo(['hr.view', 'hr.employees.manage']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function employee(array $extra = []): Employee
    {
        return Employee::create(array_merge([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'AAA STAFF', 'is_active' => true, 'join_date' => '2025-01-01',
        ], $extra));
    }

    // ── The model rule ────────────────────────────────────────────────────

    public function test_post_goes_home_when_no_mailing_address_is_set(): void
    {
        $e = $this->employee(['home_address' => self::HOME]);

        $this->assertSame(self::HOME, $e->mailingAddress());
        $this->assertFalse($e->postsElsewhere());
    }

    public function test_a_mailing_address_overrides_the_home_one(): void
    {
        $e = $this->employee(['home_address' => self::HOME, 'mailing_address' => self::POST]);

        $this->assertSame(self::POST, $e->mailingAddress());
        $this->assertTrue($e->postsElsewhere());
    }

    /** A mailing address that merely repeats home is not "elsewhere". */
    public function test_the_same_address_typed_twice_is_not_treated_as_different(): void
    {
        $e = $this->employee(['home_address' => self::HOME, 'mailing_address' => ' ' . self::HOME . ' ']);

        $this->assertFalse($e->postsElsewhere());
    }

    public function test_an_employee_with_no_address_at_all_returns_null(): void
    {
        $this->assertNull($this->employee()->mailingAddress());
        $this->assertFalse($this->employee()->postsElsewhere());
    }

    // ── The form round trip ───────────────────────────────────────────────

    public function test_both_addresses_save_when_they_differ(): void
    {
        $e = $this->employee();

        Livewire::actingAs($this->user)->test(EmployeeForm::class, ['id' => $e->id])
            ->set('f_home_address', self::HOME)
            ->set('f_mailing_same', false)
            ->set('f_mailing_address', self::POST)
            ->call('save')
            ->assertHasNoErrors();

        $e->refresh();

        $this->assertSame(self::HOME, $e->home_address);
        $this->assertSame(self::POST, $e->mailing_address);
    }

    /** Ticking the box stores NULL, not a copy of the home address. */
    public function test_same_as_home_clears_the_mailing_address(): void
    {
        $e = $this->employee(['home_address' => self::HOME, 'mailing_address' => self::POST]);

        Livewire::actingAs($this->user)->test(EmployeeForm::class, ['id' => $e->id])
            ->set('f_mailing_same', true)
            ->call('save')
            ->assertHasNoErrors();

        $e->refresh();

        $this->assertNull($e->mailing_address);
        $this->assertSame(self::HOME, $e->mailingAddress());
    }

    /**
     * Unticking the box and leaving it blank must not save — that would mean
     * "same as home" again, which is the opposite of what was asked for.
     */
    public function test_a_different_mailing_address_cannot_be_left_empty(): void
    {
        $e = $this->employee(['home_address' => self::HOME]);

        Livewire::actingAs($this->user)->test(EmployeeForm::class, ['id' => $e->id])
            ->set('f_mailing_same', false)
            ->set('f_mailing_address', '')
            ->call('save')
            ->assertHasErrors('f_mailing_address');

        $this->assertNull($e->fresh()->mailing_address);
    }

    /** Opening the form shows the box ticked when post goes home. */
    public function test_the_form_reflects_the_stored_state(): void
    {
        $same = $this->employee(['home_address' => self::HOME]);

        Livewire::actingAs($this->user)->test(EmployeeForm::class, ['id' => $same->id])
            ->assertSet('f_mailing_same', true)
            ->assertSet('f_mailing_address', '')
            ->assertSet('f_home_address', self::HOME);

        $different = $this->employee(['home_address' => self::HOME, 'mailing_address' => self::POST]);

        Livewire::actingAs($this->user)->test(EmployeeForm::class, ['id' => $different->id])
            ->assertSet('f_mailing_same', false)
            ->assertSet('f_mailing_address', self::POST);
    }

    /** Long Malaysian addresses must not be truncated into nonsense. */
    public function test_a_long_address_survives(): void
    {
        $long = str_repeat('Jalan Panjang Sekali No 12, Taman Besar, ', 8);
        $e    = $this->employee();

        Livewire::actingAs($this->user)->test(EmployeeForm::class, ['id' => $e->id])
            ->set('f_home_address', $long)
            ->call('save')
            ->assertHasNoErrors();

        // The saver trims, which is right — compare against what that means.
        $this->assertSame(trim($long), $e->fresh()->home_address);
    }

    // ── The printed record ────────────────────────────────────────────────

    public function test_the_printed_record_shows_the_mailing_address_only_when_it_differs(): void
    {
        $show = fn ($v) => $v === null || $v === '' ? '&mdash;' : e($v);

        $render = fn (Employee $e) => view('pdf.employee-details', [
            'employee' => $e, 'show' => $show,
            'canViewPay' => false, 'canViewEmployment' => false,
            'brandName' => 'Addr Co', 'logoBase64' => null,
            'photoBase64' => null, 'generatedBy' => 'Tester',
        ])->render();

        $same = $render($this->employee(['home_address' => self::HOME]));

        $this->assertStringContainsString('Home address', $same);
        $this->assertStringNotContainsString('Mailing address', $same);

        $different = $render($this->employee([
            'home_address' => self::HOME, 'mailing_address' => self::POST,
        ]));

        $this->assertStringContainsString('Mailing address', $different);
        $this->assertStringContainsString(e(self::POST), $different);
    }
}
