<?php

namespace Tests\Feature;

use App\Livewire\Supplier\Products;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A supplier manages its own catalogue and nobody else's.
 *
 * Portal sign-up is public and SupplierProduct has no scope, so the product
 * screen's bare findOrFail($id) let any registered supplier open, overwrite —
 * taking the product over — or delete another supplier's items.
 */
class SupplierPortalProductIsolationTest extends TestCase
{
    use RefreshDatabase;

    private SupplierUser $attacker;
    private SupplierProduct $victimProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::create([
            'name' => 'Buyer', 'slug' => Str::slug('Buyer') . '-' . uniqid(), 'currency' => 'MYR', 'is_active' => true,
        ]);

        $supplierA = Supplier::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Attacker Supply', 'is_active' => true, 'portal_enabled' => true]);
        $supplierB = Supplier::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Victim Supply', 'is_active' => true, 'portal_enabled' => true]);

        $this->attacker = SupplierUser::create([
            'supplier_id' => $supplierA->id, 'name' => 'Attacker', 'email' => 'a@supply.test',
            'password' => Hash::make('password'), 'is_active' => true, 'email_verified_at' => now(),
        ]);

        $this->victimProduct = SupplierProduct::create([
            'supplier_id' => $supplierB->id, 'sku' => 'V-1', 'name' => 'Victim Rice', 'unit_price' => 10, 'is_active' => true,
        ]);
    }

    private function asAttacker()
    {
        $this->actingAs($this->attacker, 'supplier');

        return Livewire::test(Products::class);
    }

    public function test_another_suppliers_product_cannot_be_opened(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->asAttacker()->call('openEdit', $this->victimProduct->id);
    }

    public function test_another_suppliers_product_cannot_be_overwritten(): void
    {
        try {
            $this->asAttacker()
                ->set('editId', $this->victimProduct->id)
                ->set('sku', 'V-1')->set('name', 'Hijacked')->set('unit_price', 1)
                ->call('save');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            // refused — the point
        }

        $fresh = $this->victimProduct->fresh();
        $this->assertSame('Victim Rice', $fresh->name);
        $this->assertEquals(10, (float) $fresh->unit_price);
    }

    public function test_another_suppliers_product_cannot_be_deleted(): void
    {
        try {
            $this->asAttacker()->call('delete', $this->victimProduct->id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
        }

        $this->assertNotNull(SupplierProduct::find($this->victimProduct->id));
    }
}
