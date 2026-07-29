<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the Prep Request tables. The feature was removed from Central
     * Kitchen mode — the kitchen produces its own items and sends them out by
     * transfer — and the models, audit registration and product-merge entry
     * have gone with it.
     *
     * Guarded rather than blind: production was verified empty before this was
     * written, but any install that DOES hold rows keeps its tables and gets a
     * clear exception instead of silently losing records.
     */
    public function up(): void
    {
        if (! Schema::hasTable('outlet_prep_requests')) {
            return;
        }

        $requests = DB::table('outlet_prep_requests')->count();
        $lines    = Schema::hasTable('outlet_prep_request_lines')
            ? DB::table('outlet_prep_request_lines')->count()
            : 0;

        if ($requests > 0 || $lines > 0) {
            throw new RuntimeException(
                "Refusing to drop prep request tables: {$requests} request(s) and {$lines} line(s) still exist. "
                . 'Export or delete them first, then re-run this migration.'
            );
        }

        Schema::dropIfExists('outlet_prep_request_lines');
        Schema::dropIfExists('outlet_prep_requests');
    }

    /**
     * Recreates the schema exactly as the original migration did. Rows are
     * gone for good — this restores the shape, not the data.
     */
    public function down(): void
    {
        Schema::create('outlet_prep_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kitchen_id')->constrained('central_kitchens')->cascadeOnDelete();
            $table->string('request_number', 30)->unique();
            $table->enum('status', ['draft', 'submitted', 'approved', 'scheduled', 'fulfilled', 'cancelled'])->default('draft');
            $table->date('needed_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status']);
        });

        Schema::create('outlet_prep_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_prep_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('requested_quantity', 15, 4);
            $table->decimal('fulfilled_quantity', 15, 4)->default(0);
            $table->foreignId('uom_id')->constrained('units_of_measure');
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }
};
