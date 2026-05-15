<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RosterAmendment extends Model
{
    protected $fillable = [
        'roster_id',
        'roster_entry_id',
        'amended_by',
        'reason',
        'changes',
        'notified',
        'notified_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'notified' => 'boolean',
        'notified_at' => 'datetime',
    ];

    public function roster(): BelongsTo
    {
        return $this->belongsTo(Roster::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(RosterEntry::class, 'roster_entry_id');
    }

    public function amendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'amended_by');
    }

    /**
     * Get formatted changes for display.
     */
    public function getFormattedChangesAttribute(): array
    {
        $changes = $this->changes ?? [];
        $formatted = [];

        foreach ($changes as $field => $change) {
            $label = match ($field) {
                'shift_start' => 'Shift Start',
                'shift_end' => 'Shift End',
                'rest_duration' => 'Rest Duration',
                'station_id' => 'Station',
                'is_off_day' => 'Off Day',
                'planned_ot' => 'Planned OT',
                default => ucwords(str_replace('_', ' ', $field)),
            };

            $formatted[] = [
                'field' => $field,
                'label' => $label,
                'from' => $change['from'] ?? null,
                'to' => $change['to'] ?? null,
            ];
        }

        return $formatted;
    }
}
