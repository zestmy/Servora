<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The asset catalogue — the Market List of things you keep rather than cook.
 *
 * ONE ROW PER ITEM TYPE, NOT PER PHYSICAL UNIT. An outlet counts 240 dinner
 * plates, not 240 plates each with its own tag; a register that demanded a
 * serial per unit would be unusable for the 90% of an F&B asset list that is
 * smallwares. Quantity on hand lives in the movements and counts, never here,
 * so this table stays a catalogue and no balance column can drift.
 *
 * ONE `unit_cost`, not the ingredient's purchase_price / pack_size / yield
 * triple: an asset is bought by the unit, there is no pack to break down and
 * nothing is lost to trim. Counting a count line at this cost is what makes
 * "what is our total asset value" answerable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->foreignId('asset_category_id')->nullable()->constrained('asset_categories')->nullOnDelete();
            $table->foreignId('uom_id')->constrained('units_of_measure');
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'asset_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
