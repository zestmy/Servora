<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `payments.subscription_id` was NOT NULL because the only thing that ever
 * wrote a payment was the CHIP-IN webhook, and a gateway payment always has a
 * subscription behind it.
 *
 * Settling an invoice by hand does not. An invoice can legitimately be raised
 * against a company with no subscription attached — an ad-hoc charge, a
 * consultancy day, a credit — and marking one paid writes a Payment so that
 * the invoice, the payment history and the revenue figures agree about what
 * was received. With the column NOT NULL that insert died on a constraint
 * violation and the admin could not record the money at all.
 *
 * Nullable is the honest shape: the question "which subscription is this for?"
 * genuinely has no answer for some payments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows written since this migration ran may legitimately hold null, and
        // tightening the column would either fail or need them deleted. Left
        // deliberately as a no-op rather than destroying payment records on a
        // rollback.
    }
};
