<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class LmsUser extends Authenticatable
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'outlet_id', 'name', 'email', 'password',
        'phone', 'status', 'approved_by', 'approved_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password'    => 'hashed',
            'approved_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /** Outlets this trainee may see SOPs for (managed in Settings > Training Portal). */
    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'lms_user_outlets')->withTimestamps();
    }

    /**
     * Outlet ids whose SOPs this trainee may see. Falls back to the
     * registration outlet when no explicit access rows exist; an empty
     * array means unrestricted (legacy users registered without an outlet).
     */
    public function accessibleOutletIds(): array
    {
        $ids = $this->outlets()->pluck('outlets.id')->map(fn ($id) => (int) $id)->all();

        if (empty($ids) && $this->outlet_id) {
            $ids = [(int) $this->outlet_id];
        }

        return $ids;
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
