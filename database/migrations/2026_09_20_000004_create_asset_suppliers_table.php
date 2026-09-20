<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who sells an asset, and what they last charged for it.
 *
 * Mirrors `supplier_ingredients` minus its uom_id and pack_size: an asset is
 * bought in its own unit, so a per-supplier UOM would only ever restate the
 * asset's. `is_preferred` is what the purchase request reaches for when a line
 * is added, exactly as it does for an ingredient.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_sku')->nullable();
            $table->decimal('last_cost', 15, 4)->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->timestamps();

            $table->unique(['supplier_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_suppliers');
    }
};
