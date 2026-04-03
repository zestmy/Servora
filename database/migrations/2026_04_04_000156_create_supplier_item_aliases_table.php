<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_item_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->string('extracted_description');
            $table->string('normalized_description');
            $table->unsignedInteger('times_used')->default(1);
            $table->timestamps();

            $table->unique(['company_id', 'supplier_id', 'normalized_description'], 'alias_unique');
            $table->index(['supplier_id', 'normalized_description']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_item_aliases');
    }
};
