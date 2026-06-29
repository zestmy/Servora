<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Ingredients list filters by company_id (CompanyScope) + is_prep on
     * every load and sorts by name, but `name` had no index — so each page was
     * a filesort over the whole table. This composite covers the company scope,
     * the is_prep filter, and the name ordering in one (leftmost-prefix) index.
     *
     * FK columns are already indexed by their constraints; the pivot tables
     * used by the whereHas/whereDoesntHave filters already index ingredient_id.
     *
     * Existence is checked at execution time so migrate:fresh / re-runs are safe.
     */
    public function up(): void
    {
        $this->addIndex('ingredients', ['company_id', 'is_prep', 'name'], 'ingredients_company_prep_name_idx');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('ingredients', 'ingredients_company_prep_name_idx');
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index]
        ) !== null;
    }

    private function addIndex(string $table, array $columns, string $index): void
    {
        if (! $this->indexExists($table, $index)) {
            Schema::table($table, function (Blueprint $t) use ($columns, $index) {
                $t->index($columns, $index);
            });
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            Schema::table($table, function (Blueprint $t) use ($index) {
                $t->dropIndex($index);
            });
        }
    }
};
