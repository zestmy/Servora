<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the two leave rules off "annual only" and onto EVERY type, and adds
 * Hospitalisation Leave.
 *
 * Pro-rating and the confirmation gate shipped an hour ago as company-wide
 * settings governing one type flagged is_annual. That was too narrow: a
 * company with a custom Study Leave or a Hospitalisation entitlement has the
 * same question about both, and there is nothing about "earned across the
 * year" or "only once confirmed" that is peculiar to annual leave.
 *
 * So each type now answers for itself, which also removes a second source of
 * truth — a company-wide switch plus a per-type marker is two places to look
 * when a balance comes out wrong.
 *
 * The old settings are CARRIED ACROSS rather than dropped. They are hours old
 * and almost certainly untouched, but "almost certainly" is not a reason to
 * silently un-set somebody's payroll-adjacent configuration; anyone who did
 * turn one on keeps it, on the type it was governing.
 *
 * HOSPITALISATION. Separate from medical leave in Malaysian practice — the
 * Employment Act treats hospitalisation as its own, longer entitlement rather
 * than as sick days. Backfilled for existing companies, not just seeded for
 * new ones, because a company that has been running for months would otherwise
 * never see it. Skipped where a company already has a type with that code, so
 * running this twice, or running it against a company that added its own, does
 * nothing.
 */
return new class extends Migration
{
    /** Days, the common Malaysian figure. Editable like any other type. */
    private const HOSPITALISATION_DAYS = 60;

    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('is_prorated')->default(false)->after('is_annual');
            $table->boolean('requires_confirmation')->default(false)->after('is_prorated');
        });

        // Carry the company-wide settings onto whichever type they governed.
        if (Schema::hasColumn('leave_settings', 'annual_prorated')) {
            foreach (DB::table('leave_settings')->get() as $s) {
                if (! $s->annual_prorated && ! $s->annual_requires_confirmation) {
                    continue;
                }

                DB::table('leave_types')
                    ->where('company_id', $s->company_id)
                    ->where('is_annual', true)
                    ->update([
                        'is_prorated'           => (bool) $s->annual_prorated,
                        'requires_confirmation' => (bool) $s->annual_requires_confirmation,
                    ]);
            }

            Schema::table('leave_settings', function (Blueprint $table) {
                $table->dropColumn(['annual_prorated', 'annual_requires_confirmation']);
            });
        }

        // is_annual existed only to route those company-wide settings. With
        // each type answering for itself there is nothing left for it to do,
        // and a column nothing reads is worse than no column: it looks like it
        // still means something.
        if (Schema::hasColumn('leave_types', 'is_annual')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->dropColumn('is_annual');
            });
        }

        $this->addHospitalisation();
    }

    /**
     * One Hospitalisation type per company that lacks one.
     *
     * Sorted to the end rather than inserted mid-list: renumbering existing
     * types would reorder every leave dropdown in the product for a change
     * nobody asked for.
     */
    private function addHospitalisation(): void
    {
        $now = now();

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            $hasAny = DB::table('leave_types')->where('company_id', $companyId)->exists();

            // A company with NO types at all has never opened the module;
            // seedDefaults() will give it the full starter set, including this
            // one, the first time somebody does.
            if (! $hasAny) {
                continue;
            }

            $exists = DB::table('leave_types')
                ->where('company_id', $companyId)
                ->whereRaw('UPPER(TRIM(code)) = ?', ['HL'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('leave_types')->insert([
                'company_id'   => $companyId,
                'name'         => 'Hospitalisation Leave',
                'code'         => 'HL',
                'default_days' => self::HOSPITALISATION_DAYS,
                'colour'       => 'blue',
                'is_paid'      => true,
                'is_claimable' => true,
                'is_active'    => true,
                'sort_order'   => (int) DB::table('leave_types')->where('company_id', $companyId)->max('sort_order') + 1,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('leave_settings', function (Blueprint $table) {
            $table->boolean('annual_prorated')->default(false);
            $table->boolean('annual_requires_confirmation')->default(false);
        });

        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('is_annual')->default(false);
            $table->dropColumn(['is_prorated', 'requires_confirmation']);
        });

        DB::table('leave_types')->whereRaw('UPPER(TRIM(code)) = ?', ['HL'])->delete();
    }
};
