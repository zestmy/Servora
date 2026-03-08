<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wastage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('reference_number')->nullable();
            $table->date('wastage_date');
            $table->decimal('total_cost', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('wastage_record_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wastage_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->foreignId('uom_id')->constrained('units_of_measure');
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('total_cost', 15, 4);
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wastage_record_lines');
        Schema::dropIfExists('wastage_records');
    }
};
