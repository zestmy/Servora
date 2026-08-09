<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('procurement_invoice_id')->constrained()->cascadeOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 15, 4);
            $table->string('method', 30)->default('bank_transfer');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Explicit short name — the auto-generated one exceeds MySQL's
            // 64-character identifier limit.
            $table->index(['company_id', 'procurement_invoice_id'], 'pip_company_invoice_idx');
        });

        // New status for invoices with some, but not all, of the balance paid.
        // See the note in update_purchase_order_statuses: MODIFY COLUMN is MySQL-only.
        Schema::table('procurement_invoices', function (Blueprint $table) {
            $table->enum('status', ['draft', 'issued', 'partial', 'paid', 'cancelled', 'overdue'])
                ->default('draft')->nullable(false)->change();
        });

        // Backfill balance_due (was only set once a credit note applied):
        // settled/cancelled invoices owe nothing; the rest owe total minus credit.
        DB::statement("UPDATE procurement_invoices SET balance_due = 0 WHERE status IN ('paid', 'cancelled') AND balance_due IS NULL");
        // GREATEST() is MySQL-only; SQLite spells the scalar form MAX(). Expressed with
        // the query builder so neither driver sees syntax it does not know.
        DB::table('procurement_invoices')->whereNull('balance_due')->update([
            'balance_due' => DB::raw('CASE WHEN total_amount - credit_applied > 0 THEN total_amount - credit_applied ELSE 0 END'),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_invoice_payments');

        DB::statement("UPDATE procurement_invoices SET status = 'issued' WHERE status = 'partial'");
        Schema::table('procurement_invoices', function (Blueprint $table) {
            $table->enum('status', ['draft', 'issued', 'paid', 'cancelled', 'overdue'])
                ->default('draft')->nullable(false)->change();
        });
    }
};
