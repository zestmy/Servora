<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Companies created before the billing module have no subscription row
     * at all, so they never appear in Admin > Subscriptions and rely on
     * lenient feature-gate fallbacks. Give each one an ACTIVE subscription
     * on the highest plan (they operated without limits — a lower plan
     * could suddenly enforce max_outlets/max_recipes caps), running to the
     * app's lifetime cap (2037-12-31, the MySQL TIMESTAMP-safe convention
     * used by lifetime coupons).
     *
     * Companies whose only rows are cancelled/expired are left alone —
     * that is a deliberate state, not a gap.
     */
    public function up(): void
    {
        $plan = DB::table('plans')->where('is_active', true)->orderByDesc('sort_order')->first(['id']);
        if (! $plan) {
            return; // no plans seeded — nothing sensible to backfill
        }

        $now    = now();
        $maxEnd = \Carbon\Carbon::create(2037, 12, 31, 23, 59, 59);

        $companyIds = DB::table('companies')
            ->whereNull('deleted_at') // companies are soft-deleted; a deleted one needs no subscription
            ->whereNotIn('id', DB::table('subscriptions')->select('company_id'))
            ->pluck('id');

        foreach ($companyIds as $companyId) {
            DB::table('subscriptions')->insert([
                'company_id'           => $companyId,
                'plan_id'              => $plan->id,
                'status'               => 'active',
                'billing_cycle'        => 'yearly',
                'trial_ends_at'        => null,
                'current_period_start' => $now,
                'current_period_end'   => $maxEnd,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Data backfill — not reversible without risking deletion of rows
        // that were legitimately edited after the fact.
    }
};
