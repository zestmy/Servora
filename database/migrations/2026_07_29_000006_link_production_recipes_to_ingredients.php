<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Production Recipe had no stockable counterpart, so completing a
     * production batch had nowhere to put the output — ProductionExecute
     * stocks the kitchen via the line recipe's linked Ingredient, and a
     * ProductionRecipe had none. That's why Production Orders could only ever
     * offer outlet prep items.
     *
     * Mirrors how prep items work (Recipe <-> Ingredient with is_prep): each
     * production recipe now owns a stockable Ingredient keyed on its yield
     * UOM, so produced goods land in kitchen inventory and can transfer out.
     */
    public function up(): void
    {
        Schema::table('production_recipes', function (Blueprint $table) {
            $table->foreignId('ingredient_id')->nullable()->after('kitchen_id')
                ->constrained()->nullOnDelete();
        });

        $now = now();

        foreach (DB::table('production_recipes')->whereNull('deleted_at')->get() as $pr) {
            // Reuse an identically-named prep ingredient if one already exists
            // for the company rather than creating a confusing duplicate.
            $existing = DB::table('ingredients')
                ->where('company_id', $pr->company_id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($pr->name)])
                ->value('id');

            if ($existing) {
                DB::table('production_recipes')->where('id', $pr->id)->update(['ingredient_id' => $existing]);
                continue;
            }

            $id = DB::table('ingredients')->insertGetId([
                'company_id'     => $pr->company_id,
                'name'           => $pr->name,
                'code'           => $pr->code,
                'base_uom_id'    => $pr->yield_uom_id,
                'recipe_uom_id'  => $pr->yield_uom_id,
                'purchase_price' => 0,
                'pack_size'      => 1,
                'yield_percent'  => 100,
                'current_cost'   => $pr->total_cost_per_unit ?? 0,
                'is_active'      => $pr->is_active,
                'is_prep'        => 1,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);

            DB::table('production_recipes')->where('id', $pr->id)->update(['ingredient_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('production_recipes', function (Blueprint $table) {
            $table->dropForeign(['ingredient_id']);
            $table->dropColumn('ingredient_id');
        });
    }
};
