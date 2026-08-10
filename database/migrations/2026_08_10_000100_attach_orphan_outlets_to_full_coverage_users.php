<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give the branches nobody was ever attached to back to the people who had all
 * the others.
 *
 * Outlet access is an explicit per-user list, and creating a branch attached it
 * to nobody — so a new branch was invisible in every outlet dropdown in the
 * product except to "all outlets" accounts, including to the person who had just
 * created it. Reported as a new branch missing from the employee form's picker.
 *
 * Settings > Branches now attaches on create. This repairs the ones already out
 * there. Deliberately narrow on two axes:
 *
 * ORPHANS ONLY — an outlet with zero pivot rows is one nobody has ever been
 * assigned to, which is the signature of this bug. An outlet with some users
 * attached is somebody's deliberate scoping decision and is left alone.
 *
 * COMPLETE COVERAGE ONLY — a user gets it if they already held every OTHER
 * outlet of that company. Someone whose access is the whole estate should not
 * quietly drop to "the whole estate except the new one". A branch manager on one
 * outlet gets nothing: head office opening a branch is not a promotion.
 */
return new class extends Migration
{
    public function up(): void
    {
        // whereNull('deleted_at') on both sides, and it matters twice: a deleted
        // branch is not an orphan worth repairing, and — the one that actually
        // bit — counting one toward "all the others" means nobody has complete
        // coverage and the migration silently does nothing. Production had a
        // branch deleted in June sitting between the real ones.
        $orphans = DB::table('outlets')
            ->whereNull('deleted_at')
            ->whereNotIn('id', fn ($q) => $q->select('outlet_id')->from('outlet_user'))
            ->get(['id', 'company_id', 'name']);

        foreach ($orphans as $outlet) {
            $others = DB::table('outlets')
                ->where('company_id', $outlet->company_id)
                ->where('id', '!=', $outlet->id)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            // A company whose only outlet is the orphan has no coverage to
            // preserve — there is nobody this rule can identify.
            if (! $others) {
                continue;
            }

            $users = DB::table('users')
                ->where('company_id', $outlet->company_id)
                ->where(fn ($q) => $q->where('can_view_all_outlets', false)->orWhereNull('can_view_all_outlets'))
                ->pluck('id');

            foreach ($users as $userId) {
                $held = DB::table('outlet_user')
                    ->join('outlets', 'outlets.id', '=', 'outlet_user.outlet_id')
                    ->where('outlet_user.user_id', $userId)
                    ->where('outlets.company_id', $outlet->company_id)
                    ->whereNull('outlets.deleted_at')
                    ->pluck('outlets.id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                if (array_diff($others, $held)) {
                    continue;
                }

                DB::table('outlet_user')->insertOrIgnore([
                    'user_id'    => $userId,
                    'outlet_id'  => $outlet->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Not reversible, and saying so beats pretending.
     *
     * The rows this adds are indistinguishable from an administrator assigning
     * the same person to the same branch a minute later, so a down() would have
     * to guess which of the two it was undoing — and guessing wrong takes real
     * access away from someone.
     */
    public function down(): void
    {
        // Intentionally empty. See the note above.
    }
};
