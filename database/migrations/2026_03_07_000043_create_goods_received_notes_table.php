<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_received_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('grn_number', 30)->unique();
            $table->enum('status', ['pending', 'partial', 'received', 'rejected'])->default('pending');
            $table->date('received_date')->nullable();
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('goods_received_note_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_received_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('expected_quantity', 15, 4)->default(0);
            $table->decimal('received_quantity', 15, 4)->default(0);
            $table->foreignId('uom_id')->constrained('units_of_measure');
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0);
            $table->enum('condition', ['good', 'damaged', 'rejected'])->default('good');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_received_note_lines');
        Schema::dropIfExists('goods_received_notes');
    }
};
