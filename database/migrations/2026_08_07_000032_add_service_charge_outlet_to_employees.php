<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which outlet's service charge pool this employee is paid from.
 *
 * Somebody posted to IOI whose service charge comes out of KLCC's pool. It
 * happens when a person is carried on one branch's headcount but earns
 * alongside another's — a transfer part way through a cycle, or a role that
 * belongs to the flagship even though the desk is elsewhere.
 *
 * NULL means "wherever they are posted", which is what every existing row
 * holds and what almost every row will keep holding.
 *
 * WHY POOL MEMBERSHIP AND NOT A RATE. The obvious reading of "pay them at
 * KLCC's rate" is a per-employee rate override, and it cannot work: a pool is
 * a fixed sum divided by the points of everyone sharing it, so paying one
 * person at another outlet's rate takes money the pool does not have and
 * leaves the sum not adding up. The only coherent version is that they are IN
 * the pool whose rate they are paid at, diluting it exactly as any other
 * member does. So this column moves the PERSON, not a number.
 *
 * The consequence to keep hold of: it changes RM/point for everybody in BOTH
 * outlets — the receiving pool gains their points, the home pool loses them.
 * That is correct, and it is why the divisor and the paid rows have to be
 * drawn from the same rule. See Employee::scopeForServiceChargeOutlet().
 *
 * ATTENDANCE IS UNAFFECTED. Where somebody works, is rostered and is marked
 * present is a fact about the place; this is a fact about the payroll. They
 * stay in their own outlet's grid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('service_charge_outlet_id')
                ->nullable()
                ->after('outlet_id')
                ->constrained('outlets')
                // If the receiving outlet is deleted they fall back to their
                // own posting rather than to no pool at all — a null pool
                // would drop them out of the service charge entirely and
                // nobody would notice until payout.
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_charge_outlet_id');
        });
    }
};
