<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 5 of docs/rbac-revamp-proposal.md — finding F9.
 *
 * `settings.view` was one switch over ten unrelated configuration screens: branches,
 * outlet groups, tax rates, sections, certifications, OT approvers, PO approvers,
 * departments, central purchasing and central kitchens. Letting someone maintain tax
 * rates also handed them every approver list and the central kitchen setup.
 *
 * One ability per page now, as agreed. Pages that belong to another module were already
 * gated by it — pay components and banks on `hr.compensation`, leave types and holidays
 * on `hr.leave.approve`, clock settings on `hr.clock.manage`, document folders on
 * `hr.documents.manage`, scheduled reports on `reports.view`, suppliers and form
 * templates on `purchasing.suppliers.manage` — and are deliberately not duplicated.
 *
 * `settings.view` is RETIRED rather than kept as an umbrella. Once the ten pages carry
 * their own abilities it gates nothing: the Settings index has no route middleware (it is
 * a list of links, each tile carrying its own permission), and the three sidebar links
 * into it now name the abilities of the group they open. A permission that gates nothing
 * is one that quietly rots — `roster.view` is the standing example.
 *
 * BACKFILL — preserve access exactly: every role and user holding `settings.view`
 * receives all ten page abilities, because that is what it already let them reach.
 */
return new class extends Migration
{
    private const PAGES = [
        'settings.outlets', 'settings.outlet_groups', 'settings.tax_rates',
        'settings.sections', 'settings.certifications', 'settings.ot_approvers',
        'settings.po_approvers', 'settings.departments', 'settings.cpu', 'settings.kitchens',
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::PAGES as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name, 'guard_name' => 'web',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $sourceId = DB::table('permissions')
            ->where('name', 'settings.view')->where('guard_name', 'web')->value('id');

        if ($sourceId) {
            $targetIds = DB::table('permissions')
                ->whereIn('name', self::PAGES)->where('guard_name', 'web')->pluck('id');

            $roleIds = DB::table('role_has_permissions')
                ->where('permission_id', $sourceId)->pluck('role_id');

            $rows = [];
            foreach ($roleIds as $roleId) {
                foreach ($targetIds as $permId) {
                    $rows[] = ['role_id' => $roleId, 'permission_id' => $permId];
                }
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('role_has_permissions')->insertOrIgnore($chunk);
            }

            $direct = DB::table('model_has_permissions')
                ->where('permission_id', $sourceId)->where('model_type', User::class)
                ->get(['model_id', 'team_id']);

            $rows = [];
            foreach ($direct as $row) {
                foreach ($targetIds as $permId) {
                    $rows[] = [
                        'permission_id' => $permId,
                        'model_type'    => User::class,
                        'model_id'      => $row->model_id,
                        'team_id'       => $row->team_id,
                    ];
                }
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('model_has_permissions')->insertOrIgnore($chunk);
            }

            logger()->info('[rbac phase 5] mirrored settings.view onto ' . count(self::PAGES)
                . ' page abilities for ' . $roleIds->count() . ' roles and ' . $direct->count() . ' user grants');

            DB::table('role_has_permissions')->where('permission_id', $sourceId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $sourceId)->delete();
            DB::table('permission_denials')->where('permission_id', $sourceId)->delete();
            DB::table('permissions')->where('id', $sourceId)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Recreates settings.view and returns it to anyone holding any of its pages. */
    public function down(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'name' => 'settings.view', 'guard_name' => 'web',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $oldId = DB::table('permissions')->where('name', 'settings.view')->value('id');

        $pageIds = DB::table('permissions')->whereIn('name', self::PAGES)->pluck('id');

        foreach (DB::table('role_has_permissions')->whereIn('permission_id', $pageIds)->distinct()->pluck('role_id') as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $oldId]);
        }
        foreach (DB::table('model_has_permissions')->whereIn('permission_id', $pageIds)->get(['model_id', 'team_id'])->unique(fn ($r) => $r->model_id . ':' . $r->team_id) as $r) {
            DB::table('model_has_permissions')->insertOrIgnore([
                'permission_id' => $oldId, 'model_type' => User::class,
                'model_id' => $r->model_id, 'team_id' => $r->team_id,
            ]);
        }

        DB::table('role_has_permissions')->whereIn('permission_id', $pageIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $pageIds)->delete();
        DB::table('permission_denials')->whereIn('permission_id', $pageIds)->delete();
        DB::table('permissions')->whereIn('id', $pageIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
