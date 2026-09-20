<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The asset inventory count — a stock take for the things you keep.
 *
 * Shaped like `stock_takes` on purpose: draft while you walk the outlet,
 * completed when it is filed, with a per-line snapshot of what the system
 * expected, what was found, and what the difference is worth. `total_asset_value`
 * is the number the whole module exists to produce.
 *
 * UNLIKE A STOCK TAKE, A COMPLETED COUNT IS AUTHORITATIVE. Ingredients have a
 * purchase and consumption ledger behind them, so a count there is a check
 * against a figure that is derived anyway. Assets have no consumption: a plate
 * does not get used up, it gets broken, and the breakage nobody wrote down is
 * only ever discovered by counting. So the count becomes the new baseline and
 * movements dated after it are what has changed since.
 *
 * A count may be PARTIAL — filtered to one category, or one department's
 * section of the outlet. That is why the baseline is worked out per asset from
 * the last count that actually listed it, rather than per count: treating an
 * asset that was simply not on the sheet as counted-zero would wipe it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_number')->nullable();
            $table->string('status', 20)->default('draft'); // draft | completed
            $table->date('count_date');
            $table->decimal('total_asset_value', 15, 4)->default(0);
            $table->decimal('total_variance_cost', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'outlet_id', 'count_date']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('asset_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained();
            $table->decimal('system_quantity', 15, 4)->default(0);
            $table->decimal('counted_quantity', 15, 4)->default(0);
            $table->decimal('variance_quantity', 15, 4)->default(0);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('line_value', 15, 4)->default(0);
            $table->decimal('variance_cost', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_count_lines');
        Schema::dropIfExists('asset_counts');
    }
};
