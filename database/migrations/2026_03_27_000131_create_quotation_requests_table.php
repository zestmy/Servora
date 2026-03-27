<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rfq_number', 30)->unique();
            $table->string('title', 255);
            $table->enum('status', ['draft', 'sent', 'partial_response', 'fully_quoted', 'closed', 'cancelled'])->default('draft');
            $table->date('needed_by_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status']);
        });

        Schema::create('quotation_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->foreignId('uom_id')->constrained('units_of_measure');
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('quotation_request_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'quoted', 'declined', 'expired'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_request_suppliers');
        Schema::dropIfExists('quotation_request_lines');
        Schema::dropIfExists('quotation_requests');
    }
};
