<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingStep extends Model
{
    protected $fillable = ['company_id', 'step', 'completed_at'];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public const STEPS = [
        'company_details',
        'first_outlet',
        'invite_team',
        'explore_features',
    ];

    public const LABELS = [
        'company_details'  => 'Company Details',
        'first_outlet'     => 'First Outlet',
        'invite_team'      => 'Invite Your Team',
        'explore_features' => 'Explore Features',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    public function markComplete(): void
    {
        $this->update(['completed_at' => now()]);
    }
}
