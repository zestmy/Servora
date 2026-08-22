<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `invoices` was written for exactly one caller: the CHIP-IN webhook, which
 * creates a paid row after money has already moved. Everything an invoice is
 * for BEFORE that — issue it, send it, chase it, void it, put a period and a
 * customer's registered address on it — had nowhere to live.
 *
 * The columns added here are what makes the row a document rather than a
 * receipt stub:
 *
 *   subscription_id  which subscription the money is for. payment_id only
 *                    exists once paid, so an issued-but-unpaid invoice had no
 *                    link to the thing being billed at all.
 *   period_*         the service period. Two invoices for the same company and
 *                    amount are otherwise indistinguishable on a statement.
 *   due_at           there is no "overdue" without it.
 *   bill_to          a SNAPSHOT of the customer's billing details. Companies
 *                    rename and move; a reissued PDF must not silently change
 *                    the address on a document already sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->after('payment_id')
                ->constrained()->nullOnDelete();
            $table->string('currency', 3)->default('MYR')->after('total');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('tax_amount');
            $table->string('tax_label', 30)->nullable()->after('tax_rate');
            $table->date('period_start')->nullable()->after('issued_at');
            $table->date('period_end')->nullable()->after('period_start');
            $table->timestamp('due_at')->nullable()->after('period_end');
            $table->timestamp('sent_at')->nullable()->after('paid_at');
            $table->timestamp('voided_at')->nullable()->after('sent_at');
            $table->string('void_reason', 300)->nullable()->after('voided_at');
            $table->text('notes')->nullable()->after('line_items');
            $table->json('bill_to')->nullable()->after('notes');
            $table->foreignId('created_by')->nullable()->after('bill_to')
                ->constrained('users')->nullOnDelete();

            // The admin list sorts by issue date and filters by status; the
            // ageing panel asks for unpaid rows past their due date.
            $table->index('issued_at');
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['status', 'due_at']);
            $table->dropIndex(['issued_at']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropColumn([
                'currency', 'tax_rate', 'tax_label', 'period_start', 'period_end',
                'due_at', 'sent_at', 'voided_at', 'void_reason', 'notes', 'bill_to',
            ]);
        });
    }
};
