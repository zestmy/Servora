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
        'name', 'email', 'password', 'company_id', 'outlet_id',
        'designation', 'can_manage_users', 'can_approve_po', 'can_approve_pr',
        'can_delete_records', 'can_view_all_outlets',
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
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class)->withTimestamps();
    }

    public function activeOutletId(): ?int
    {
        $sessionId = session('active_outlet_id');
        if ($sessionId && $this->canAccessOutlet($sessionId)) {
            return (int) $sessionId;
        }
        return $this->outlets()->first()?->id;
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
        if ($this->canViewAllOutlets()) return true;
        return $this->outlets()->where('outlets.id', $outletId)->exists();
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

    /** Get display designation for UI (falls back to "Team Member"). */
    public function displayDesignation(): string
    {
        return $this->designation ?: 'Team Member';
    }
}
