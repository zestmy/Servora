<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, \App\Models\Concerns\PurgesStoredFiles;

    protected static function booted(): void
    {
        // A deleted account should not leave its photograph being served from
        // the public disk with nothing left that knows whose face it is.
        static::deleted(fn (self $user) => $user->purgeOwnedFile('avatar'));
    }

    /**
     * Spatie's permission check, aliased so denials can wrap it — see
     * checkPermissionTo() below for why that is the only seam that works.
     */
    use HasRoles {
        checkPermissionTo as protected spatieCheckPermissionTo;
    }

    /**
     * Spatie's own hook, wrapped so an explicit denial beats a grant.
     *
     * Denials cannot be enforced from our Gate::before. Spatie registers its own
     * `$gate->before()` that returns TRUE as soon as the user holds the permission, and
     * Laravel's Gate takes the first non-null before-callback result — so whichever of the
     * two is registered first wins, and ours lost. Gate::after is no help either: it
     * merges with `$result ??= $afterResult`, so it cannot overturn a true.
     *
     * This method is what Spatie's before-callback actually calls. Returning false here
     * turns its `?: null` into null, the Gate falls through to normal resolution, and the
     * ability is denied — through `can:` middleware, @can and can() alike, without us
     * having to remember to check anywhere.
     */
    public function checkPermissionTo($permission, $guardName = null): bool
    {
        if (is_string($permission) && $this->isDenied($permission)) {
            return false;
        }

        if ($this->spatieCheckPermissionTo($permission, $guardName)) {
            return true;
        }

        return is_string($permission) && $this->holdsSomethingImplying($permission, $guardName);
    }

    /** Guards the one-level implication lookup against re-entering itself. */
    protected bool $resolvingImplication = false;

    /**
     * Does a module ability this person holds imply the one being asked for?
     *
     * See PermissionRegistry::impliedViews() for the rule and why it is derived
     * rather than listed. Three things are deliberate here:
     *
     * An explicit DENIAL still wins — checked above, before we get here — because a
     * denial is a considered exception and inheriting the ability back through a
     * side door would make it unenforceable.
     *
     * A denied IMPLIER cannot imply. Take away someone's `inventory.wastage.record`
     * and it stops standing in for `inventory.view`, or a denial would leave a
     * shadow of the ability behind it.
     *
     * One level only. Nothing implies a write ability today, so a chain cannot
     * form — the flag is there so that stays true if that ever changes.
     */
    protected function holdsSomethingImplying(string $permission, $guardName = null): bool
    {
        if ($this->resolvingImplication) {
            return false;
        }

        $impliers = \App\Helpers\PermissionRegistry::impliedBy($permission);

        if (! $impliers) {
            return false;
        }

        $this->resolvingImplication = true;

        try {
            foreach ($impliers as $implier) {
                if ($this->isDenied($implier)) {
                    continue;
                }

                if ($this->spatieCheckPermissionTo($implier, $guardName)) {
                    return true;
                }
            }
        } finally {
            $this->resolvingImplication = false;
        }

        return false;
    }

    protected $fillable = [
        'name', 'email', 'password', 'company_id', 'outlet_id', 'timezone',
        'designation', 'can_manage_users', 'can_approve_po', 'can_approve_pr',
        'can_delete_records', 'can_view_all_outlets', 'can_receive_grn', 'can_manage_invoices',
        'workspace_mode', 'default_kitchen_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at'    => 'datetime',
        'suspended_at'         => 'datetime',
        'last_active_at'       => 'datetime',
        'password'             => 'hashed',
        'can_manage_users'     => 'boolean',
        'can_approve_po'       => 'boolean',
        'can_approve_pr'       => 'boolean',
        'can_delete_records'   => 'boolean',
        'can_view_all_outlets' => 'boolean',
        'can_receive_grn'      => 'boolean',
        'can_manage_invoices'  => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Per-company capability flags stored on the company_user pivot. */
    public const CAPABILITIES = [
        'can_manage_users', 'can_approve_po', 'can_approve_pr', 'can_delete_records',
        'can_view_all_outlets', 'can_receive_grn', 'can_manage_invoices',
    ];

    /**
     * All companies this user is a member of (company_user pivot).
     * `company_id` remains the ACTIVE company — every query scope keys off it.
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot(self::CAPABILITIES)
            ->withTimestamps();
    }

    /** Capability flags for one company, from the pivot (null if not a member). */
    public function capabilitiesForCompany(int $companyId): ?array
    {
        $row = $this->companies()->where('companies.id', $companyId)->first();
        if (! $row) {
            return null;
        }

        return collect(self::CAPABILITIES)
            ->mapWithKeys(fn ($cap) => [$cap => (bool) $row->pivot->{$cap}])
            ->all();
    }

    /**
     * Write capability flags for one company. The pivot is the truth; the
     * users-table columns only cache the ACTIVE company's flags, so they are
     * mirrored when (and only when) that company is the active one.
     */
    public function setCapabilitiesForCompany(int $companyId, array $flags): void
    {
        $flags = array_intersect_key($flags, array_flip(self::CAPABILITIES));
        if (empty($flags)) {
            return;
        }

        $this->companies()->syncWithoutDetaching([$companyId]);
        $this->companies()->updateExistingPivot($companyId, $flags);

        if ((int) $this->company_id === $companyId) {
            $this->update($flags);
        }
    }

    /** Re-copy the active company's pivot flags into the users-table cache. */
    public function refreshCapabilityCache(): void
    {
        if (! $this->company_id) {
            return;
        }

        $flags = $this->capabilitiesForCompany((int) $this->company_id);
        if ($flags !== null) {
            $this->update($flags);
        }
    }

    public function hasMultipleCompanies(): bool
    {
        return $this->companies()->count() > 1;
    }

    /**
     * Switch the active company. Only allowed for companies the user is a
     * member of; returns false (no change) otherwise.
     */
    public function switchToCompany(int $companyId): bool
    {
        if ((int) $this->company_id === $companyId) {
            return true;
        }

        if (! $this->companies()->where('companies.id', $companyId)->exists()) {
            return false;
        }

        $this->update(['company_id' => $companyId]);

        // Spatie teams mode: re-scope permission checks for the rest of this
        // request and drop any team-stale cached relations.
        setPermissionsTeamId($companyId);
        $this->unsetRelation('roles');
        $this->unsetRelation('permissions');
        $this->memoDenied = [];

        // Capability cache columns follow the active company
        $this->refreshCapabilityCache();

        return true;
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class)->withTimestamps();
    }

    /**
     * The user's default outlet for form prefill (first assigned outlet).
     * No longer session-driven — the outlet switcher has been removed.
     * Listings use availableOutletIds() (in ScopesToActiveOutlet) instead.
     */
    public function activeOutletId(): ?int
    {
        // 'pivot_created_at' is the Eloquent accessor name; the actual DB column
        // in the pivot table is 'created_at'. Use the qualified table.column form
        // to avoid ambiguity with other created_at columns in the join.
        // Multi-company users may have outlet rows from other companies — only
        // outlets of the active company count.
        return $this->outlets()
            ->where('outlets.company_id', $this->company_id)
            ->orderBy('outlet_user.created_at')->value('outlets.id');
    }

    public function activeOutlet(): ?Outlet
    {
        $id = $this->activeOutletId();
        return $id ? Outlet::find($id) : null;
    }

    public function canViewAllOutlets(): bool
    {
        return $this->can_view_all_outlets || $this->isSystemRole();
    }

    /**
     * Outlets of the ACTIVE company this user may see, as a query.
     *
     * Use this — never `$user->outlets()` directly — anywhere a list, dropdown
     * or scope is built. The outlet_user pivot spans every company the user
     * belongs to, so the raw relation happily returns another company's
     * outlets; canAccessOutlet() and activeOutletId() have always filtered for
     * exactly that reason. Mirrors canAccessOutlet(): "view all outlets" means
     * all outlets of the active company, never across companies.
     */
    public function accessibleOutlets()
    {
        if ($this->canViewAllOutlets()) {
            return Outlet::where('company_id', $this->company_id);
        }

        return $this->outlets()->where('outlets.company_id', $this->company_id);
    }

    /**
     * Whether this user's access reaches EVERY outlet of the active company.
     *
     * The question a company-wide document has to ask. A payroll run with no
     * outlet pays the whole company, so "may they see it" is not answered by
     * any single outlet: it is answered by whether anything is being hidden
     * from them at all.
     *
     * Not the same as canViewAllOutlets(), which is the flag. Somebody
     * explicitly assigned all three outlets of a three-outlet company can see
     * the whole company just as surely, and a single-outlet company's manager
     * holds every outlet there is — which is what stops this rule locking the
     * only run a small company ever makes.
     */
    public function coversEveryOutlet(): bool
    {
        if ($this->canViewAllOutlets()) {
            return true;
        }

        $all = Outlet::where('company_id', $this->company_id)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        // A company with no outlets hides nothing, so there is nothing to
        // withhold. Returning false there would lock a run nobody can reach.
        return $all === [] || array_diff($all, $this->accessibleOutletIds()) === [];
    }

    /** Accessible outlet ids for the active company. */
    public function accessibleOutletIds(): array
    {
        return $this->accessibleOutlets()
            ->pluck('outlets.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * The outlet that Central Kitchen work is recorded against: the active
     * kitchen's base outlet.
     *
     * In kitchen mode the user is operating one kitchen, not choosing among
     * outlets — a wastage or stock take there belongs to the kitchen. Returns
     * null outside kitchen mode, when the kitchen has no linked outlet, or
     * when the user can't actually reach it, so callers fall back to the
     * ordinary multi-outlet behaviour instead of inventing access.
     */
    public function kitchenOutletId(): ?int
    {
        if (! $this->inKitchenMode()) return null;

        $outletId = $this->activeKitchen()?->outlet_id;
        if (! $outletId) return null;

        return in_array((int) $outletId, $this->accessibleOutletIds(), true)
            ? (int) $outletId
            : null;
    }

    public function canAccessOutlet(int $outletId): bool
    {
        if ($this->isSystemRole()) return true;

        // Access never crosses the active company, even for "all outlets" users.
        if ($this->can_view_all_outlets) {
            return Outlet::where('id', $outletId)->where('company_id', $this->company_id)->exists();
        }

        return $this->outlets()
            ->where('outlets.id', $outletId)
            ->where('outlets.company_id', $this->company_id)
            ->exists();
    }

    /**
     * Team-agnostic role check. With Spatie teams mode on, hasRole() only sees
     * the active company's assignment rows — system-level roles must apply in
     * EVERY company, so check the pivot directly, ignoring team_id.
     */
    public function hasGlobalRole(array|string $roles): bool
    {
        return \Illuminate\Support\Facades\DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', self::class)
            ->where('model_has_roles.model_id', $this->id)
            ->whereIn('roles.name', (array) $roles)
            ->exists();
    }

    /** Check if user has a system-level role (Super Admin / System Admin). */
    public function isSystemRole(): bool
    {
        return $this->memoIsSystemRole ??= $this->hasGlobalRole(['Super Admin', 'System Admin']);
    }

    /** @var bool|null request-lifetime memo for isSystemRole() */
    protected ?bool $memoIsSystemRole = null;

    /**
     * A permission check that system roles always pass.
     *
     * This replaced `hasCapability()`, which took one of seven boolean columns on
     * `company_user` and had two problems. It was coarse — `can_delete_records` was a
     * single switch checked in Sales, Purchasing, Inventory, Clock Events and Overtime
     * Claims, so you could not let someone void a wastage record without also letting
     * them delete purchase orders. And it was a second, parallel authority system:
     * `can_manage_users` wrote both a pivot flag AND the `users.manage` permission, while
     * reads went through a cached column on `users` rather than the pivot the writes
     * landed on. Phase 1 folded all of it into ordinary permissions.
     *
     * Why this exists rather than plain `can()`: `Gate::before` only bypasses for Super
     * Admin, but `hasCapability()` returned true for System Admin too — and System Admin
     * must hold in EVERY company, which a team-scoped role assignment does not. Dropping
     * to `can()` at the call sites would have quietly stripped System Admin's rights
     * everywhere, so the short-circuit is preserved here instead of being scattered.
     *
     * `can_view_all_outlets` is deliberately NOT part of this: it says which outlets a
     * user's abilities apply to, not what they may do. It stays a flag, next to the outlet
     * picker — see canViewAllOutlets().
     */
    public function canDo(string $permission): bool
    {
        if ($this->isSystemRole()) return true;
        return $this->can($permission);
    }

    /**
     * Abilities explicitly taken away from this user in the active company.
     *
     * Denials express "this role, but not that one bit" — the single most common real
     * request, which before Phase 3 forced a whole new role. They live in their own table
     * rather than as a flag on `model_has_permissions` because Spatie's permissions()
     * relation filters on team_id and nothing else, so a "deny" row parked there would be
     * read back as a grant.
     *
     * Memoised per request and per team: Gate::before consults this on EVERY gate check,
     * including policy checks that have nothing to do with the registry, so it has to be
     * free after the first call. switchToCompany() clears the memo.
     *
     * @return list<string>
     */
    public function deniedPermissions(): array
    {
        $team = (int) (getPermissionsTeamId() ?: $this->company_id);

        if (array_key_exists($team, $this->memoDenied)) {
            return $this->memoDenied[$team];
        }

        // Gate::before consults this on every permission check, so a missing table would
        // not fail one feature — it would 500 every page in the app. That can happen on a
        // environment whose migrations have not caught up with its code, which is exactly
        // when you least want the site down. Checked once per process, not per call.
        static $tableExists = null;
        $tableExists ??= \Illuminate\Support\Facades\Schema::hasTable('permission_denials');

        if (! $tableExists) {
            return $this->memoDenied[$team] = [];
        }

        return $this->memoDenied[$team] = DB::table('permission_denials')
            ->join('permissions', 'permissions.id', '=', 'permission_denials.permission_id')
            ->where('permission_denials.model_type', self::class)
            ->where('permission_denials.model_id', $this->id)
            ->where('permission_denials.team_id', $team)
            ->pluck('permissions.name')
            ->all();
    }

    /** @var array<int, list<string>> team id => denied permission names */
    protected array $memoDenied = [];

    /** Is this ability explicitly denied for this user in the active company? */
    public function isDenied(string $permission): bool
    {
        return in_array($permission, $this->deniedPermissions(), true);
    }

    /**
     * Replace this user's denials for ONE company. Anything not listed is un-denied;
     * other companies are untouched, matching how roles and grants behave here.
     *
     * @param  list<string>  $permissionNames
     */
    public function syncDenials(int $companyId, array $permissionNames): void
    {
        $ids = $permissionNames
            ? DB::table('permissions')->whereIn('name', $permissionNames)
                ->where('guard_name', 'web')->pluck('id')->all()
            : [];

        DB::table('permission_denials')
            ->where('model_type', self::class)
            ->where('model_id', $this->id)
            ->where('team_id', $companyId)
            ->when($ids, fn ($q) => $q->whereNotIn('permission_id', $ids))
            ->delete();

        foreach ($ids as $permissionId) {
            DB::table('permission_denials')->insertOrIgnore([
                'permission_id' => $permissionId,
                'model_type'    => self::class,
                'model_id'      => $this->id,
                'team_id'       => $companyId,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        unset($this->memoDenied[$companyId]);
    }

    /**
     * Can this user bypass the Ingredients / Recipes list locks?
     * System admins and users who can manage users can.
     */
    public function canBypassLock(): bool
    {
        return $this->canDo('users.manage');
    }

    public function displayDesignation(): string
    {
        return $this->designation ?: 'Team Member';
    }

    /** Check if user is assigned to any central kitchen of the active company. */
    public function isKitchenUser(): bool
    {
        return \Illuminate\Support\Facades\DB::table('kitchen_users')
            ->join('central_kitchens', 'central_kitchens.id', '=', 'kitchen_users.kitchen_id')
            ->where('kitchen_users.user_id', $this->id)
            ->where('central_kitchens.company_id', $this->company_id)
            ->whereNull('central_kitchens.deleted_at')
            ->exists();
    }

    /** Get the active workspace mode from session (falls back to DB default). */
    public function activeWorkspace(): string
    {
        return session('workspace_mode', $this->workspace_mode ?? 'outlet');
    }

    /**
     * This user's role in the given kitchen (or their active one): manager,
     * chef or staff. Null when they aren't a member.
     *
     * Kitchen rights hang off this rather than a Spatie permission — the
     * kitchen_users pivot already carries the role, and kitchen membership is
     * per-kitchen rather than per-company.
     */
    public function kitchenRole(?int $kitchenId = null): ?string
    {
        $kitchenId ??= $this->activeKitchen()?->id;
        if (! $kitchenId) return null;

        return DB::table('kitchen_users')
            ->where('user_id', $this->id)
            ->where('kitchen_id', $kitchenId)
            ->value('role');
    }

    /**
     * May approve prep requests, fulfil them (which moves stock out of the
     * kitchen), and cancel orders. System roles always qualify.
     */
    public function canManageKitchen(?int $kitchenId = null): bool
    {
        return $this->isSystemRole() || $this->kitchenRole($kitchenId) === 'manager';
    }

    /**
     * May run production: schedule orders, record actuals and complete a
     * batch. Managers and chefs; plain staff can look but not commit.
     */
    public function canRunProduction(?int $kitchenId = null): bool
    {
        return $this->isSystemRole()
            || in_array($this->kitchenRole($kitchenId), ['manager', 'chef'], true);
    }

    /** Check if currently in kitchen workspace. */
    public function inKitchenMode(): bool
    {
        return $this->activeWorkspace() === 'kitchen';
    }

    /** Get the active kitchen for kitchen mode. */
    public function activeKitchen(): ?CentralKitchen
    {
        $kitchenId = session('active_kitchen_id', $this->default_kitchen_id);
        if (! $kitchenId) return null;

        // Guard against a stale session/default pointing at another company's kitchen
        return CentralKitchen::where('id', $kitchenId)
            ->where('company_id', $this->company_id)
            ->first();
    }
}
