<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The compliance record — every label ever printed.
 *
 * Batches group a print run so the audit trail reads "Chiller 1 relabel —
 * 14 items, 32 labels, 07:15, Aiman" rather than 32 loose rows. printed_at
 * is resolved ONCE per batch: resolving now() per line makes the times drift
 * across a single chiller relabel.
 *
 * label_prints.payload is a frozen snapshot of exactly what was printed.
 * A past label must never be re-derived from live data — the item's shelf
 * life will have changed by the time an auditor asks about it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_print_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            // Null for ad-hoc single prints off the search screen.
            $table->foreignId('label_set_id')->nullable()
                  ->constrained('label_sets')->nullOnDelete();
            // Who was picked from the staff list vs who was logged in — on a
            // shared kitchen login these are usually different people.
            $table->foreignId('employee_id')->nullable()
                  ->constrained('employees')->nullOnDelete();
            $table->foreignId('user_id')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('printed_at');
            // Denormalised for the audit list — avoids counting lines per row.
            $table->unsignedInteger('item_count')->default(0);
            $table->unsignedInteger('label_count')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'outlet_id', 'printed_at'], 'label_batches_company_outlet_time_idx');
        });

        Schema::create('label_prints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('label_print_batches')->cascadeOnDelete();
            $table->foreignId('printer_id')->nullable()
                  ->constrained('label_printers')->nullOnDelete();
            $table->foreignId('template_id')->nullable()
                  ->constrained('label_templates')->nullOnDelete();
            $table->nullableMorphs('labelable');
            $table->string('custom_name')->nullable();
            $table->string('label_type', 20);
            $table->string('storage_state', 20);
            $table->timestamp('start_at');
            $table->timestamp('end_at')->nullable();
            // True when no shelf life rule resolved and staff typed the date.
            // Doubles as a report of which categories still need rules.
            $table->boolean('manual_expiry')->default(false);
            $table->unsignedInteger('copies')->default(1);
            $table->json('payload');
            // 'sent' under the browser driver — kiosk printing gives no
            // confirmation, so this records intent, not outcome.
            $table->string('status', 20)->default('sent');
            $table->timestamps();

            // Drives the expiring-today dashboard.
            $table->index(['company_id', 'outlet_id', 'end_at'], 'label_prints_company_outlet_expiry_idx');
            $table->index('batch_id', 'label_prints_batch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_prints');
        Schema::dropIfExists('label_print_batches');
    }
};
