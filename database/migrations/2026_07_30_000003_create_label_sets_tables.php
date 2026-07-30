<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Print sets — named collections a chef prints in one go: "Chiller 1",
 * "Sandwich Station", "Grill Station".
 *
 * Named LabelSet, not LabelGroup, because OutletGroup already exists and the
 * two would be confused constantly. Deliberately NOT reusing RosterStation:
 * a chiller isn't somewhere you roster staff, and polluting the roster's
 * station list with fridges would break a module that isn't ours.
 *
 * Sets are outlet-owned — both company_id and outlet_id are required. There
 * are no company-wide sets and no cross-outlet sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'outlet_id', 'is_active'], 'label_sets_company_outlet_active_idx');
        });

        Schema::create('label_set_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_set_id')->constrained()->cascadeOnDelete();
            // Physically meaningful: labels peel off the roll in this order and
            // get applied walking down the shelf.
            $table->unsignedInteger('sort_order')->default(0);
            // Ingredient, Recipe, ProductionRecipe — or null for a freeform line.
            $table->nullableMorphs('labelable');
            $table->string('custom_name')->nullable();
            // Per LINE, not per set: "Chiller 1" is realistically twelve chill
            // use-by labels and two thawed ones.
            $table->string('label_type', 20);
            $table->string('storage_state', 20);
            $table->unsignedInteger('copies')->default(1);
            $table->decimal('quantity', 12, 4)->nullable();
            $table->foreignId('uom_id')->nullable()
                  ->constrained('units_of_measure')->nullOnDelete();
            $table->foreignId('template_id')->nullable()
                  ->constrained('label_templates')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['label_set_id', 'sort_order'], 'label_set_lines_set_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_set_lines');
        Schema::dropIfExists('label_sets');
    }
};
