<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 50);
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->foreignId('uom_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('pack_size', 15, 4)->default(1);
            $table->decimal('unit_price', 15, 4);
            $table->char('currency', 3)->default('MYR');
            $table->decimal('min_order_quantity', 15, 4)->default(1);
            $table->unsignedTinyInteger('lead_time_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('previous_price', 15, 4)->nullable();
            $table->timestamp('price_changed_at')->nullable();
            $table->decimal('price_change_percent', 6, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_products');
    }
};
