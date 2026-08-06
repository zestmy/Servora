<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who approves whose leave, and who gets told about it.
 *
 * TWO MECHANISMS, because they answer different questions and a company will
 * have one or both:
 *
 *  1. `employees.reports_to_id` — the ORG CHART. This person's superior. It is
 *     what a company already knows and what staff expect ("it goes to my
 *     manager"), and it is per person, so a chef reporting to a head chef and
 *     a cashier reporting to the outlet manager both work.
 *
 *  2. `leave_approvers` — a user who may approve for an outlet, optionally
 *     narrowed to a section. Mirrors overtime_claim_approvers deliberately:
 *     the same shape, so nobody has to learn a second idea. This is the
 *     fallback when there is no reporting line, and the answer for companies
 *     that approve centrally rather than up a chain.
 *
 * The superior is an EMPLOYEE and an approver is a USER, and that asymmetry is
 * on purpose. A superior may have no login at all — plenty of head chefs do
 * not — but they still have an email address and are still the right person to
 * tell. Approving in the app needs a login; being notified does not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Self-referencing: nullOnDelete so removing a manager leaves
            // their reports without a superior rather than deleting them.
            $table->foreignId('reports_to_id')->nullable()->after('section_id')
                ->constrained('employees')->nullOnDelete();
        });

        Schema::create('leave_approvers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Null outlet = the whole company; null section = the whole outlet.
            $table->foreignId('outlet_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'outlet_id', 'section_id'], 'leave_approvers_scope_unique');
            $table->index(['company_id', 'outlet_id']);
        });

        Schema::create('leave_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // Polymorphic in spirit but explicit in practice: leave and time
            // off are the only two things that notify, and two nullable
            // columns beat a morph nobody can query by hand.
            $table->foreignId('leave_request_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('time_off_request_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('event', 30);        // submitted | decided
            $table->string('email');
            $table->string('recipient_name')->nullable();
            $table->string('status', 20)->default('queued');   // queued | sent | failed
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_notifications');
        Schema::dropIfExists('leave_approvers');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reports_to_id');
        });
    }
};
