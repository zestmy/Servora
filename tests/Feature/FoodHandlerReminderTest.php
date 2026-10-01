<?php

namespace Tests\Feature;

use App\Livewire\Hr\Employees;
use App\Models\Company;
use App\Models\ComplianceSetting;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\User;
use App\Services\Hr\DocumentExpiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Food handler is on the Documents & Training card whether or not the company
 * tracks its expiry.
 *
 * It used to drop out entirely when expiry was off (the default), on the
 * grounds that a one-off certificate has nothing to expire. But who has not
 * taken it yet is exactly the reminder HR wants, so as a one-off it now reads
 * certified / pending, and the pending staff are on the list.
 */
class FoodHandlerReminderTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Food Co', 'slug' => Str::slug('Food Co') . '-' . uniqid(), 'currency' => 'MYR', 'is_active' => true]);
        $this->outlet  = Outlet::create(['company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true]);
    }

    private function employee(string $name, array $extra = []): Employee
    {
        return Employee::create(array_merge([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => $name, 'is_active' => true, 'join_date' => '2026-01-01',
        ], $extra));
    }

    private function summary(): array
    {
        return app(DocumentExpiry::class)->summarise(
            Employee::withoutGlobalScopes()->where('company_id', $this->company->id),
            $this->company->id
        );
    }

    public function test_untracked_food_handler_reports_certified_and_pending(): void
    {
        $this->employee('CERTIFIED COOK', ['food_handler_certified' => true]);
        $this->employee('NEW STARTER', ['food_handler_certified' => false]);

        $summary = $this->summary();
        $doc = collect($summary['documents'])->firstWhere('key', 'food_handler');

        $this->assertNotNull($doc, 'Food handler must be on the card even with expiry tracking off.');
        $this->assertFalse($doc['has_expiry']);
        $this->assertSame(1, $doc[DocumentExpiry::VALID]);
        $this->assertSame(1, $doc[DocumentExpiry::MISSING]);

        $pending = $summary['rows']->where('document_key', 'food_handler');
        $this->assertSame(['NEW STARTER'], $pending->pluck('name')->values()->all());
        $this->assertFalse($pending->first()['has_expiry']);
    }

    public function test_tracked_food_handler_still_reports_by_expiry(): void
    {
        ComplianceSetting::create(['company_id' => $this->company->id, 'food_handler_expires' => true]);
        $this->employee('LAPSED', ['food_handler_certified' => true, 'food_handler_expired_on' => now()->subDays(3)->toDateString()]);

        $doc = collect($this->summary()['documents'])->firstWhere('key', 'food_handler');

        $this->assertTrue($doc['has_expiry']);
        $this->assertSame(1, $doc[DocumentExpiry::EXPIRED]);
    }

    public function test_the_employees_page_shows_the_card_and_the_pending_staff(): void
    {
        $this->employee('CERTIFIED COOK', ['food_handler_certified' => true]);
        $this->employee('NEW STARTER', ['food_handler_certified' => false]);

        $user = User::factory()->create(['company_id' => $this->company->id, 'can_view_all_outlets' => true]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);
        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(Permission::findOrCreate('hr.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::actingAs($user)->test(Employees::class)
            ->assertSee('Food Handler')
            ->assertSee('pending')
            ->assertSee('certified')
            ->assertSee('not taken');
    }
}
