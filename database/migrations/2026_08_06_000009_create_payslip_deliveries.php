<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A record of every payslip email: to whom, at what address, when, and whether
 * it actually went.
 *
 * Kept as its own table rather than a flag on the line because the question
 * asked afterwards is "what was sent to this person and where" — a boolean
 * cannot answer that, and a resend has to be visible as a second event rather
 * than overwriting the first.
 *
 * The address is COPIED here. If someone's email is corrected next week, the
 * record must still show where the payslip actually went.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslip_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();

            $table->string('email');
            $table->string('status', 20)->default('queued');  // queued | sent | failed
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();

            $table->foreignId('queued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'payroll_run_id']);
            $table->index(['payroll_run_line_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_deliveries');
    }
};
