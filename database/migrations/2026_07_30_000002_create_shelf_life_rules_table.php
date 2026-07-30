<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shelf life as a storage-state matrix.
 *
 * A single shelf life per item can't express "chill 3 days / frozen 90 days /
 * thawed 24 hours", and defrost (OOF) labels are one of the headline label
 * types, so the label module needs a value per storage state.
 *
 * Rules hang off categories by default — one row per ingredient category
 * covers hundreds of items — with per-item rows only where an item genuinely
 * differs. Resolution order lives in ShelfLifeService.
 *
 * The legacy recipes.shelf_life_value / production_recipes.shelf_life_value
 * columns STAY. Other screens (SOP PDFs, LMS, prep item form) read them. This
 * migration copies them in as the item's own rule for its stated storage
 * instruction, so nothing has to be re-entered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shelf_life_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // IngredientCategory, RecipeCategory, Ingredient, Recipe, ProductionRecipe
            $table->morphs('ruleable');
            // ambient | chill | frozen | thawed | opened | cooked
            // 'chill' (not 'chilled') to match Recipe::STORAGE_OPTIONS.
            $table->string('storage_state', 20);
            $table->decimal('value', 8, 2);
            // minutes | hours | days | weeks | months — Recipe::SHELF_LIFE_UNITS
            $table->string('unit', 10);
            $table->timestamps();

            $table->unique(
                ['ruleable_type', 'ruleable_id', 'storage_state'],
                'slr_ruleable_state_unique'
            );
            $table->index('company_id');
        });

        $this->backfill('recipes', \App\Models\Recipe::class);
        $this->backfill('production_recipes', \App\Models\ProductionRecipe::class);
    }

    /**
     * Copy an existing single shelf life in as a rule for the item's own
     * storage instruction, defaulting to 'chill' when none is set.
     */
    private function backfill(string $table, string $morphClass): void
    {
        if (! Schema::hasColumn($table, 'shelf_life_value')) {
            return;
        }

        $now = now();

        DB::table($table)
            ->select('id', 'company_id', 'shelf_life_value', 'shelf_life_unit', 'storage_instruction')
            ->whereNotNull('shelf_life_value')
            ->where('shelf_life_value', '>', 0)
            ->whereNotNull('shelf_life_unit')
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($morphClass, $now) {
                $insert = [];

                foreach ($rows as $row) {
                    $insert[] = [
                        'company_id'    => $row->company_id,
                        'ruleable_type' => $morphClass,
                        'ruleable_id'   => $row->id,
                        'storage_state' => $row->storage_instruction ?: 'chill',
                        'value'         => $row->shelf_life_value,
                        'unit'          => $row->shelf_life_unit,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ];
                }

                if ($insert) {
                    DB::table('shelf_life_rules')->insertOrIgnore($insert);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('shelf_life_rules');
    }
};
