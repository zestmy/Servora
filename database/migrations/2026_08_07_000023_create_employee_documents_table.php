<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scanned paperwork held against an employee — application form, IC or
 * passport, typhoid card, food handler certificate.
 *
 * NOT the Documents module. That one is a company-wide Google Drive library
 * browsed by folder; these are copies of one person's papers, and an IC scan
 * has no business being one shared-folder permission away from the whole
 * company. They live on the PRIVATE disk and are served through
 * EmployeeDocumentController, which checks the viewer's outlet access on every
 * request.
 *
 * MANY ROWS PER TYPE on purpose. A food handler certificate is renewed, and the
 * expiring one still has to be produced at an inspection covering the period it
 * was valid for — replacing it in place would destroy the only copy of the
 * document that proves the kitchen was compliant last year.
 *
 * The expiry dates stay on `employees` where the compliance reminders already
 * read them: this table is the SCAN, not the fact. Uploading a certificate does
 * not change what the record says about the certificate, and the form makes
 * that plain by keeping the two side by side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // See EmployeeDocument::TYPES.
            $table->string('type', 40);
            // What this particular copy is, when the type alone is not enough
            // — "Passport (renewed 2026)", "Page 2".
            $table->string('label', 120)->nullable();

            $table->string('file_path', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size_bytes')->default(0);

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
