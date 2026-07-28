<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Split recipe categories per workspace: outlet menu categories and
     * Central Kitchen production categories are different vocabularies and
     * were sharing one list, so kitchen-only groupings showed up in the outlet
     * recipe dropdown and vice versa.
     *
     * Every existing row becomes 'outlet' — that's what they were built for,
     * and 630-odd recipes join to them by name. The kitchen list starts empty
     * except for names existing production recipes already reference, so no
     * production recipe is left pointing at a category nobody can manage.
     */
    public function up(): void
    {
        Schema::table('recipe_categories', function (Blueprint $table) {
            $table->string('scope', 10)->default('outlet')->after('company_id')->index();
        });

        DB::table('recipe_categories')->update(['scope' => 'outlet']);

        $now = now();
        $used = DB::table('production_recipes')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->select('company_id', 'category')
            ->distinct()
            ->get();

        foreach ($used as $row) {
            $exists = DB::table('recipe_categories')
                ->where('company_id', $row->company_id)
                ->where('scope', 'kitchen')
                ->where('name', $row->category)
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) continue;

            DB::table('recipe_categories')->insert([
                'company_id' => $row->company_id,
                'scope'      => 'kitchen',
                'parent_id'  => null,
                'name'       => $row->category,
                'color'      => '#6366f1',
                'sort_order' => 0,
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Kitchen rows exist only because of this split — leaving them behind
        // would silently add them to the outlet list once scope is gone.
        DB::table('recipe_categories')->where('scope', 'kitchen')->delete();

        Schema::table('recipe_categories', function (Blueprint $table) {
            $table->dropIndex(['scope']);
            $table->dropColumn('scope');
        });
    }
};
