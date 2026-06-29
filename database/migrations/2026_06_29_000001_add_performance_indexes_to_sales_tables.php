<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
     */
    public function up(): void
    {
        Schema::table('sales_records', function (Blueprint $table) {
            // Outlet filter + date range + ORDER BY sale_date (covers the list query)
            try { $table->index(['outlet_id', 'sale_date'], 'sales_records_outlet_date_idx'); } catch (\Throwable $e) {}
            // Company-level scopes and month-to-date target aggregation
            try { $table->index(['company_id', 'sale_date'], 'sales_records_company_date_idx'); } catch (\Throwable $e) {}
        });

        Schema::table('sales_record_lines', function (Blueprint $table) {
            // Category revenue aggregation: WHERE sales_record_id IN (...) GROUP BY sales_category_id, SUM(total_revenue)
            // (sales_record_id, sales_category_id, total_revenue) makes this a covering index.
            try { $table->index(['sales_record_id', 'sales_category_id', 'total_revenue'], 'srl_record_cat_rev_idx'); } catch (\Throwable $e) {}
        });
    }

    public function down(): void
    {
        Schema::table('sales_records', function (Blueprint $table) {
            try { $table->dropIndex('sales_records_outlet_date_idx'); } catch (\Throwable $e) {}
            try { $table->dropIndex('sales_records_company_date_idx'); } catch (\Throwable $e) {}
        });

        Schema::table('sales_record_lines', function (Blueprint $table) {
            try { $table->dropIndex('srl_record_cat_rev_idx'); } catch (\Throwable $e) {}
        });
    }
};
