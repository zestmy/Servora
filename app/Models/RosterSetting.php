<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RosterSetting extends Model
{
    protected $fillable = [
        'outlet_id',
        'normal_hours',
        'rest_duration',
        'week_start_day',
    ];

    protected $casts = [
        'normal_hours' => 'decimal:2',
        'rest_duration' => 'integer',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * Get rest duration formatted as human-readable string.
     */
    public function getRestDurationFormattedAttribute(): string
    {
        $hours = intdiv($this->rest_duration, 60);
        $minutes = $this->rest_duration % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}m";
        }
    }
}
