<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assets arriving at an outlet, and assets leaving it.
 *
 * ONE DOCUMENT WITH A TYPE, not a receipts table and a disposals table. The two
 * carry the same fields and differ only in sign and in whether a supplier makes
 * sense, and a single table is what lets the register add them up in one pass
 * rather than reconciling two.
 *
 * This is the only thing that moves an asset's quantity between counts. A
 * completed count is the baseline, receipts and disposals dated after it are
 * the movement since — see App\Services\AssetOnHandService, which is the one
 * place that arithmetic lives. There is deliberately no balance column here or
 * on `assets`: a stored balance is a number that can disagree with the
 * documents behind it, and nothing would say which was right.
 *
 * `purchase_request_id` is how a receipt says which request it satisfies. It is
 * nullable because most disposals and plenty of receipts (a replacement sent by
 * a supplier, an outlet opening with its kit already in place) never had one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('movement_type', 20)->default('receipt'); // receipt | disposal
            $table->string('reference_number')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->date('movement_date');
            $table->string('reason')->nullable(); // disposals: broken, lost, written off, transferred out
            $table->text('notes')->nullable();
            $table->decimal('total_cost', 15, 4)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'outlet_id', 'movement_date']);
            $table->index(['company_id', 'movement_type']);
        });

        Schema::create('asset_movement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_movement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_movement_lines');
        Schema::dropIfExists('asset_movements');
    }
};
