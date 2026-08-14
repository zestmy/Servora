<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    // ── Learning & Development ────────────────────────────────────────────

    public function attempts(): HasMany
    {
        return $this->hasMany(TrainingAttempt::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(TrainingCertificate::class);
    }

    public function pathProgress(): HasMany
    {
        return $this->hasMany(TrainingPathProgress::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TrainingAssignment::class);
    }

    /**
     * Initials for the leaderboard avatar.
     *
     * Two letters from two words where there are two, otherwise the first two
     * of the only one — a mononym would otherwise render a single letter in a
     * circle sized for a pair, which reads as a rendering fault rather than a
     * name.
     */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $parts = array_values(array_filter($parts));

        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($this->name, 0, 2));
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
