<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staging table for Price Watcher's two-step flow.
 *
 * 1. Scan Documents — user uploads a supplier doc; AI extracts and the
 *    result lands here with status 'extracted' (or 'failed' with an error
 *    message the user can see).
 * 2. Review Documents — user opens the row, reviews the matches, and
 *    imports. The row's status moves to 'imported' / 'discarded'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scanned_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();

            $table->string('original_filename');
            $table->string('file_path');          // relative path inside storage disk
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);

            $table->enum('status', ['pending', 'extracted', 'failed', 'imported', 'discarded'])
                  ->default('pending');
            $table->text('error_message')->nullable();

            // Supplier identity detected by AI (nullable: user may confirm later)
            $table->string('supplier_name_detected')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();

            // Document / effective date
            $table->date('document_date_detected')->nullable();
            $table->date('effective_date')->nullable();

            // Extracted line items (JSON). Kept denormalised on purpose —
            // the review page mutates these freely before committing.
            $table->json('extracted_items')->nullable();

            // Tracking
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scanned_documents');
    }
};
