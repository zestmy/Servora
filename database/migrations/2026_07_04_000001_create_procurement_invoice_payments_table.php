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

            $table->index(['company_id', 'procurement_invoice_id']);
        });

        // New status for invoices with some, but not all, of the balance paid.
        DB::statement("ALTER TABLE procurement_invoices MODIFY COLUMN status ENUM('draft', 'issued', 'partial', 'paid', 'cancelled', 'overdue') NOT NULL DEFAULT 'draft'");

        // Backfill balance_due (was only set once a credit note applied):
        // settled/cancelled invoices owe nothing; the rest owe total minus credit.
        DB::statement("UPDATE procurement_invoices SET balance_due = 0 WHERE status IN ('paid', 'cancelled') AND balance_due IS NULL");
        DB::statement("UPDATE procurement_invoices SET balance_due = GREATEST(0, total_amount - credit_applied) WHERE balance_due IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_invoice_payments');

        DB::statement("UPDATE procurement_invoices SET status = 'issued' WHERE status = 'partial'");
        DB::statement("ALTER TABLE procurement_invoices MODIFY COLUMN status ENUM('draft', 'issued', 'paid', 'cancelled', 'overdue') NOT NULL DEFAULT 'draft'");
    }
};
