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
        'name',
        'email',
        'password',
        'company_id',
        'outlet_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Legacy single-outlet relationship (kept for backward compat). */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /** All outlets this user has access to. */
    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class)->withTimestamps();
    }

    /** Get the currently active outlet from session, or first assigned outlet. */
    public function activeOutletId(): ?int
    {
        $sessionId = session('active_outlet_id');

        // Validate the session outlet is one the user actually has access to
        if ($sessionId && $this->canAccessOutlet($sessionId)) {
            return (int) $sessionId;
        }

        // Default to first assigned outlet
        return $this->outlets()->first()?->id;
    }

    /** Get active outlet model. */
    public function activeOutlet(): ?Outlet
    {
        $id = $this->activeOutletId();
        return $id ? Outlet::find($id) : null;
    }

    /** Whether user can view "All Outlets" (no outlet filter). */
    public function canViewAllOutlets(): bool
    {
        return $this->hasRole(['Super Admin', 'System Admin', 'Business Manager', 'Operations Manager']);
    }

    /** Check if user has access to a specific outlet. */
    public function canAccessOutlet(int $outletId): bool
    {
        if ($this->canViewAllOutlets()) {
            return true;
        }

        return $this->outlets()->where('outlets.id', $outletId)->exists();
    }
}
