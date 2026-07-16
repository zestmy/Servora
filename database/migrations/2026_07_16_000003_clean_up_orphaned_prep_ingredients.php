<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deleting a prep recipe used to leave its linked is_prep ingredient behind:
 * gone from the Prep Items list but still offered (and duplicated after
 * re-creation) in the recipe form's ingredient search.
 *
 * Clean up existing orphans — is_prep ingredients whose prep recipe is
 * missing or soft-deleted:
 *  - not referenced by any live recipe's lines → soft-delete
 *  - still referenced → keep but deactivate (visible for manual cleanup;
 *    the search now hides orphans regardless)
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphans = DB::table('ingredients as i')
            ->leftJoin('recipes as r', function ($join) {
                $join->on('r.id', '=', 'i.prep_recipe_id')->whereNull('r.deleted_at');
            })
            ->where('i.is_prep', true)
            ->whereNull('i.deleted_at')
            ->whereNull('r.id')
            ->pluck('i.id');

        foreach ($orphans as $id) {
            $referenced = DB::table('recipe_lines as rl')
                ->join('recipes as r', function ($join) {
                    $join->on('r.id', '=', 'rl.recipe_id')->whereNull('r.deleted_at');
                })
                ->where('rl.ingredient_id', $id)
                ->exists();

            if ($referenced) {
                DB::table('ingredients')->where('id', $id)->update([
                    'is_active'  => false,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('ingredients')->where('id', $id)->update([
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Data cleanup — not reversible.
    }
};
