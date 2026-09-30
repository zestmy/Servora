<?php

namespace Tests\Feature;

use App\Livewire\Labels\Staff\SetPrint;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LabelSet;
use App\Models\LabelSetLine;
use App\Models\Outlet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The staff set checklist removes lines by id. LabelSetLine has no
 * CompanyScope and there is no web user on this screen, so the line must be
 * found inside a set re-resolved for the signed-in employee's company and
 * outlet — never through a set id the browser could rewrite.
 */
class LabelsSetPrintPermissionTest extends TestCase
{
    use RefreshDatabase;

    private LabelSet $set;
    private LabelSetLine $ownLine;
    private LabelSetLine $foreignLine;
    private LabelSet $foreignSet;

    protected function setUp(): void
    {
        parent::setUp();

        [$company, $outlet] = $this->tenant('Set Print Co');
        [$other, $otherOutlet] = $this->tenant('Other Kitchen Co');

        $this->set = $this->set($company, $outlet, 'Chiller 1');
        $this->ownLine = $this->line($this->set, 'SAMBAL');

        $this->foreignSet = $this->set($other, $otherOutlet, 'Their Chiller');
        $this->foreignLine = $this->line($this->foreignSet, 'NOT MINE');

        $employee = Employee::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'outlet_id' => $outlet->id,
            'name' => 'Chef Aiman', 'email' => 'aiman' . uniqid() . '@example.test', 'is_active' => true,
        ]);

        // A PIN-style staff session, not a web guard.
        session(['subdomain_company_id' => $company->id]);
        app(\App\Services\Staff\StaffSession::class)->signIn($employee, 'email');
    }

    /** @return array{0: Company, 1: Outlet} */
    private function tenant(string $name): array
    {
        $company = Company::create([
            'name' => $name, 'slug' => Str::slug($name) . '-' . uniqid(), 'currency' => 'MYR', 'is_active' => true,
        ]);
        $outlet = Outlet::create([
            'company_id' => $company->id, 'name' => 'Main', 'code' => strtoupper(Str::random(4)), 'is_active' => true,
        ]);

        return [$company, $outlet];
    }

    private function set(Company $company, Outlet $outlet, string $name): LabelSet
    {
        return LabelSet::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'outlet_id' => $outlet->id, 'name' => $name, 'is_active' => true,
        ]);
    }

    private function line(LabelSet $set, string $name): LabelSetLine
    {
        return LabelSetLine::create([
            'label_set_id' => $set->id, 'custom_name' => $name, 'sort_order' => 0,
            'label_type' => 'prep', 'storage_state' => 'chill', 'copies' => 1, 'is_active' => true,
        ]);
    }

    public function test_another_companys_line_is_not_removed(): void
    {
        Livewire::test(SetPrint::class, ['set' => $this->set->id])
            ->call('removeItem', $this->foreignLine->id);

        $this->assertNotNull(LabelSetLine::find($this->foreignLine->id));
    }

    public function test_the_set_id_cannot_be_pointed_at_another_companys_set(): void
    {
        $component = Livewire::test(SetPrint::class, ['set' => $this->set->id]);

        try {
            $component->set('setId', $this->foreignSet->id)->call('removeItem', $this->foreignLine->id);
        } catch (\Throwable) {
            // Locked property (or a 404 from the scoped set lookup) — either way refused.
        }

        $this->assertNotNull(LabelSetLine::find($this->foreignLine->id));
    }

    public function test_a_line_of_the_open_set_is_still_removed(): void
    {
        Livewire::test(SetPrint::class, ['set' => $this->set->id])
            ->call('removeItem', $this->ownLine->id)
            ->assertOk();

        $this->assertNull(LabelSetLine::find($this->ownLine->id));
    }
}
