<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'company_id', 'outlet_id', 'timezone',
        'designation', 'can_manage_users', 'can_approve_po', 'can_approve_pr',
        'can_delete_records', 'can_view_all_outlets', 'can_receive_grn', 'can_manage_invoices',
        'workspace_mode', 'default_kitchen_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at'    => 'datetime',
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

    /**
     * All companies this user is a member of (company_user pivot).
     * `company_id` remains the ACTIVE company — every query scope keys off it.
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->withTimestamps();
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

    /** Check if user has a system-level role (Super Admin / System Admin). */
    public function isSystemRole(): bool
    {
        return $this->hasRole(['Super Admin', 'System Admin']);
    }

    /** Check a capability flag (system roles always have all capabilities). */
    public function hasCapability(string $capability): bool
    {
        if ($this->isSystemRole()) return true;
        return (bool) ($this->{$capability} ?? false);
    }

    /**
     * Can this user bypass the Ingredients / Recipes list locks?
     * System admins and users with the "manage users" capability can.
     */
    public function canBypassLock(): bool
    {
        return $this->isSystemRole() || $this->hasCapability('can_manage_users');
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
