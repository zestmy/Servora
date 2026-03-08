<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('do_number')->unique();
            $table->enum('status', ['pending', 'received', 'partial', 'rejected'])->default('pending');
            $table->date('delivery_date');
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('delivery_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('ordered_quantity', 15, 4)->default(0);
            $table->decimal('delivered_quantity', 15, 4);
            $table->foreignId('uom_id')->constrained('units_of_measure');
            $table->decimal('unit_cost', 15, 4);
            $table->enum('condition', ['good', 'damaged', 'rejected'])->default('good');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_order_lines');
        Schema::dropIfExists('delivery_orders');
    }
};
