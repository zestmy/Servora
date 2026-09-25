<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per reminder email about overdue audits or corrective actions.
 *
 * Kept for the same reason `leave_notifications` is: "did the manager get
 * told?" needs an answer, and Mail::send() returning is not one (see
 * EngineMailerTransport). It is also the throttle — the unique key below
 * means a recipient gets at most ONE reminder a day however often the hourly
 * command runs or is re-run by hand.
 *
 *   kind        auditor  → a web user who conducts audits: overdue schedules
 *                          and overdue actions across their outlets
 *               owner    → an employee who owns overdue corrective actions,
 *                          linked to the Staff Portal
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);                        // auditor | owner
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('recipient_name');
            $table->date('sent_on');                           // the local day it belongs to
            $table->json('summary')->nullable();               // counts, for the log screen and tests
            $table->string('status', 10)->default('queued');   // queued | sent | failed
            $table->timestamp('sent_at')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'kind', 'email', 'sent_on'], 'audit_reminders_once_a_day');
            $table->index(['company_id', 'sent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_reminders');
    }
};
