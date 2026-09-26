<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An asset carries a tax class, the same way an ingredient does.
 *
 * Assets are bought from suppliers on purchase orders, and a stand mixer is
 * taxed like anything else — but with nowhere to record the rate, every asset
 * line on a request and on the order it became went through at no tax at all.
 * Null means "the company's default rate", exactly as on ingredients.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assets', 'tax_rate_id')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->foreignId('tax_rate_id')->nullable()->after('unit_cost')
                    ->constrained('tax_rates')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('assets', 'tax_rate_id')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->dropConstrainedForeignId('tax_rate_id');
            });
        }
    }
};
