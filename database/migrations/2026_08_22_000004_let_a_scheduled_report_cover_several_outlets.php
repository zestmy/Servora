<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A scheduled report could cover one outlet or every outlet, and nothing in
 * between — `report_subscriptions.outlet_id`, nullable, where null meant "all".
 *
 * That is the wrong shape for how groups actually run. An operations manager
 * looking after three of eleven branches had to choose between eleven sections
 * of noise or three separate subscriptions arriving as three separate emails
 * at three different moments.
 *
 * The pivot makes the set explicit. EMPTY STILL MEANS ALL: that is what every
 * existing row with a null outlet_id means, and rewriting them into eleven
 * pivot rows apiece would silently freeze each subscription's outlet list at
 * today's outlets — a branch opened next month would quietly stop appearing in
 * a report that used to cover everything.
 *
 * `outlet_id` stays, derived: it holds the outlet when exactly one is selected
 * and null otherwise, so ReportLog rows, the completeness check and every
 * existing query keep working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_subscription_outlet', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();

            // One outlet cannot be on the same subscription twice.
            $table->unique(['report_subscription_id', 'outlet_id'], 'report_sub_outlet_unique');
        });

        // Backfill the single-outlet subscriptions. Null ones are left with no
        // pivot rows, which is exactly how "all outlets" is expressed.
        $singles = DB::table('report_subscriptions')
            ->whereNotNull('outlet_id')
            ->get(['id', 'outlet_id']);

        foreach ($singles as $row) {
            DB::table('report_subscription_outlet')->insert([
                'report_subscription_id' => $row->id,
                'outlet_id'              => $row->outlet_id,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_subscription_outlet');
    }
};
