<?php

namespace App\Services;

use App\Models\CentralKitchen;
use App\Models\CentralPurchasingUnit;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Resolves which central facility (kitchen / purchasing unit) serves a given
 * outlet's procurement requests. Replaces the historical "first active" guesses
 * so that multi-kitchen / multi-CPU chains route correctly.
 *
 * All lookups run under the models' CompanyScope, so results are tenant-safe.
 * Every resolver stays backward-compatible: when a company has exactly one
 * active facility it is returned unconditionally, matching the previous
 * single-facility behaviour with zero configuration.
 */
class ProcurementRoutingService
{
    /**
     * Resolve the central kitchen that should produce prep items for $outletId.
     *
     * Precedence:
     *   1. The outlet's explicitly-assigned kitchen (if still active).
     *   2. The company's only active kitchen (unambiguous single-kitchen setup).
     *   3. The acting user's default kitchen (if active) — for kitchen-workspace users.
     *   4. null — ambiguous; the caller must surface this rather than misroute.
     */
    public static function resolveKitchenId(?int $outletId, ?User $user = null): ?int
    {
        if ($outletId) {
            $assigned = Outlet::whereKey($outletId)->value('default_kitchen_id');
            if ($assigned && CentralKitchen::active()->whereKey($assigned)->exists()) {
                return (int) $assigned;
            }
        }

        $activeIds = CentralKitchen::active()->limit(2)->pluck('id');
        if ($activeIds->count() === 1) {
            return (int) $activeIds->first();
        }

        if ($user && $user->default_kitchen_id
            && CentralKitchen::active()->whereKey($user->default_kitchen_id)->exists()) {
            return (int) $user->default_kitchen_id;
        }

        return null;
    }

    /**
     * Resolve the central purchasing unit that should consolidate $outletId's requests.
     *
     * Precedence:
     *   1. The outlet's explicitly-assigned CPU (if still active).
     *   2. The company's only active CPU (unambiguous single-CPU setup).
     *   3. The single CPU the acting user belongs to (via cpu_users), if active.
     *   4. null — ambiguous; the caller must surface this rather than misroute.
     */
    public static function resolveCpuId(?int $outletId, ?User $user = null): ?int
    {
        if ($outletId) {
            $assigned = Outlet::whereKey($outletId)->value('default_cpu_id');
            if ($assigned && CentralPurchasingUnit::active()->whereKey($assigned)->exists()) {
                return (int) $assigned;
            }
        }

        $activeIds = CentralPurchasingUnit::active()->limit(2)->pluck('id');
        if ($activeIds->count() === 1) {
            return (int) $activeIds->first();
        }

        if ($user) {
            $memberIds = DB::table('cpu_users')->where('user_id', $user->id)->pluck('cpu_id')->unique();
            if ($memberIds->count() === 1
                && CentralPurchasingUnit::active()->whereKey($memberIds->first())->exists()) {
                return (int) $memberIds->first();
            }
        }

        return null;
    }
}
