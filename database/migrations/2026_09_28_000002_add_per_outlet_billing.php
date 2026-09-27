<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Per-outlet checkout (docs/pricing-model.md).
 *
 * subscriptions.outlet_quantity: outlets the suite is paid for.
 * subscriptions.amount: what one cycle costs this company, worked out at
 *   checkout (suite × outlets less volume discount, plus add-ons). NULL on
 *   legacy rows, which keep billing the plan's flat price.
 * subscriptions.pending_change: a cheaper configuration chosen mid-period,
 *   applied at the next renewal instead of refunding the difference.
 * payments.checkout: the configuration a payment buys. The webhook applies
 *   exactly this once CHIP says it is paid — never the form's current state.
 *
 * And the switch-over: Free / Basic / Full go public, the flat legacy plans
 * stop being offered. Existing subscriptions on them are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('outlet_quantity')->default(1)->after('billing_cycle');
            $table->decimal('amount', 10, 2)->nullable()->after('outlet_quantity');
            $table->json('pending_change')->nullable()->after('amount');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->json('checkout')->nullable()->after('metadata');
        });

        DB::table('plans')->whereIn('slug', ['free', 'basic', 'full'])->update(['is_public' => true]);
        DB::table('plans')->whereNull('modules')->update(['is_public' => false]);
    }

    public function down(): void
    {
        DB::table('plans')->whereNull('modules')->update(['is_public' => true]);
        DB::table('plans')->whereIn('slug', ['free', 'basic', 'full'])->update(['is_public' => false]);

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('checkout');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['outlet_quantity', 'amount', 'pending_change']);
        });
    }
};
