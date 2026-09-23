<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Labour cost moved from the outlet that employs someone to the outlet (or
 * event, or catering job run by an outlet) they were lent to.
 *
 * One transfer = one RECEIVING outlet, many employee lines. Each line keeps
 * the outlet the person came from, so one event staffed from three branches
 * is one document with three sending outlets.
 *
 * Every money column is a SNAPSHOT taken when the line was saved: the daily
 * rate from the salary on file, the OT from the approved claims in the range.
 * A salary revision next month must not quietly rewrite what was charged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labour_cost_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('transfer_number')->unique();
            $table->foreignId('to_outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->date('transfer_date');
            $table->string('purpose', 30)->default('support');
            $table->string('reference', 200)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'transfer_date']);
        });

        Schema::create('labour_cost_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('labour_cost_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_name');
            $table->foreignId('from_outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->date('date_start');
            $table->date('date_end');
            $table->decimal('days', 6, 2)->default(0);
            $table->decimal('daily_rate', 12, 4)->default(0);
            $table->decimal('salary_amount', 12, 2)->default(0);
            $table->decimal('ot_hours', 8, 2)->default(0);
            $table->decimal('ot_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->json('ot_claim_ids')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'date_start', 'date_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labour_cost_transfer_lines');
        Schema::dropIfExists('labour_cost_transfers');
    }
};
