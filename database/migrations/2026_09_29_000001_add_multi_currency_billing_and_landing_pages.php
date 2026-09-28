<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Multi-currency subscription pricing, and country landing pages.
 *
 * billing_currencies: the currencies a visitor can be priced in besides MYR,
 *   which is the base and never has a row. Each lists the countries it
 *   covers; one may be the rest-of-the-world currency. Prices are either
 *   fixed by the admin (`prices`) or converted from the MYR list at Bank
 *   Negara's rate and rounded up to `rounding`.
 * exchange_rates: the last good BNM rate per currency, MYR per `unit` of the
 *   foreign currency. Checkout reads it when BNM cannot be reached.
 * companies.billing_country / billing_currency: where the company signed up
 *   from, and the currency it is priced in — locked by its first payment.
 * subscriptions.currency: the currency `amount` is in. CHIP-IN only takes
 *   MYR, so every charge converts at the rate of the day; `amount` stays in
 *   the currency the customer was quoted. Widened because IDR and VND run to
 *   hundreds of millions for a large group.
 * payments.fx: for a payment priced in another currency, what it was priced
 *   at and the BNM rate that turned it into the MYR `amount` CHIP charged.
 * landing_pages: the home page in a local language, per country.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique();
            $table->string('name', 60);
            $table->string('symbol', 8)->nullable();
            $table->json('countries')->nullable();
            $table->boolean('is_rest_of_world')->default(false);
            $table->string('pricing_mode', 12)->default('converted');
            $table->json('prices')->nullable();
            $table->decimal('rounding', 12, 2)->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('currency', 3)->unique();
            $table->unsignedInteger('unit')->default(1);
            $table->decimal('buying_rate', 18, 8)->nullable();
            $table->decimal('selling_rate', 18, 8)->nullable();
            $table->decimal('middle_rate', 18, 8)->nullable();
            $table->date('rate_date');
            $table->timestamp('fetched_at');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->char('billing_country', 2)->nullable()->after('currency');
            $table->char('billing_currency', 3)->nullable()->after('billing_country');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->char('currency', 3)->default('MYR')->after('outlet_quantity');
            $table->decimal('amount', 16, 2)->nullable()->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->json('fx')->nullable()->after('checkout');
        });

        Schema::create('landing_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 20)->unique();
            $table->string('locale', 10);
            $table->string('language_name', 40);
            $table->json('countries')->nullable();
            $table->json('strings')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('auto_redirect')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_pages');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('fx');
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['billing_country', 'billing_currency']);
        });
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('billing_currencies');
    }
};
