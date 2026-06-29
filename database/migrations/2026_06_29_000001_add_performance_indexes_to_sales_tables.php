<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite indexes for the Sales list/stats queries.
     *
     * The FK columns (company_id, outlet_id, sales_record_id, sales_category_id)
     * already have single-column indexes created automatically by their foreign
     * key constraints. What was missing is `sale_date` — used by every date
     * filter and the default ORDER BY — and the composite combinations that let
     * MySQL filter by outlet/company AND range-scan the date in one index.
     *
     * Index existence is checked at execution time (not via a try/catch around
     * the Blueprint, whose commands run *after* the closure returns) so the
     * migration is safe to re-run / migrate:fresh.
     */
    public function up(): void
    {
        $this->addIndex('sales_records', ['outlet_id', 'sale_date'], 'sales_records_outlet_date_idx');
        $this->addIndex('sales_records', ['company_id', 'sale_date'], 'sales_records_company_date_idx');
        // Covering index for: WHERE sales_record_id IN (...) GROUP BY sales_category_id, SUM(total_revenue)
        $this->addIndex('sales_record_lines', ['sales_record_id', 'sales_category_id', 'total_revenue'], 'srl_record_cat_rev_idx');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('sales_records', 'sales_records_outlet_date_idx');
        $this->dropIndexIfExists('sales_records', 'sales_records_company_date_idx');
        $this->dropIndexIfExists('sales_record_lines', 'srl_record_cat_rev_idx');
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
