<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RosterEmailRecipient extends Model
{
    protected $fillable = [
        'outlet_id',
        'email',
        'name',
        'role_label',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get display name (name or email if name is empty).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: $this->email;
    }
}
