<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RosterEntry extends Model
{
    protected $fillable = [
        'roster_id',
        'employee_id',
        'station_id',
        'day_date',
        'shift_start',
        'shift_end',
        'rest_duration',
        'hours_worked',
        'planned_ot',
        'planned_ot_manual',
        'is_off_day',
        'notes',
    ];

    protected $casts = [
        'day_date' => 'date',
        'hours_worked' => 'decimal:2',
        'planned_ot' => 'decimal:2',
        'rest_duration' => 'integer',
        'planned_ot_manual' => 'boolean',
        'is_off_day' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Auto-calculate hours_worked and planned_ot on save
        static::saving(function (RosterEntry $entry) {
            if (!$entry->is_off_day && $entry->shift_start && $entry->shift_end) {
                $entry->hours_worked = $entry->calculateHoursWorked();

                // Only auto-calculate OT if not manually set
                if (!$entry->planned_ot_manual) {
                    $entry->planned_ot = $entry->calculatePlannedOt();
                }
            } else {
                $entry->hours_worked = 0;
                if (!$entry->planned_ot_manual) {
                    $entry->planned_ot = 0;
                }
            }
        });
    }

    public function roster(): BelongsTo
    {
        return $this->belongsTo(Roster::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(RosterStation::class, 'station_id');
    }

    /**
     * Calculate hours worked based on shift times and rest duration.
     */
    public function calculateHoursWorked(): float
    {
        if (!$this->shift_start || !$this->shift_end) {
            return 0;
        }

        $start = Carbon::parse($this->shift_start);
        $end = Carbon::parse($this->shift_end);

        // Handle overnight shifts
        if ($end->lt($start)) {
            $end->addDay();
        }

        $totalMinutes = $start->diffInMinutes($end);
        $restMinutes = $this->rest_duration ?? 0;
        $workedMinutes = max(0, $totalMinutes - $restMinutes);

        return round($workedMinutes / 60, 2);
    }

    /**
     * Calculate planned OT based on outlet settings.
     */
    public function calculatePlannedOt(): float
    {
        $settings = RosterSetting::firstOrCreate(
            ['outlet_id' => $this->roster?->outlet_id ?? 1],
            ['normal_hours' => 8.00, 'rest_duration' => 60]
        );

        $hoursWorked = $this->hours_worked ?? $this->calculateHoursWorked();
        $normalHours = (float) $settings->normal_hours;

        return max(0, round($hoursWorked - $normalHours, 2));
    }

    /**
     * Get formatted shift time range.
     */
    public function getShiftRangeAttribute(): string
    {
        if ($this->is_off_day) {
            return 'OFF';
        }

        if (!$this->shift_start || !$this->shift_end) {
            return '-';
        }

        $start = Carbon::parse($this->shift_start)->format('H:i');
        $end = Carbon::parse($this->shift_end)->format('H:i');

        return "{$start}-{$end}";
    }

    /**
     * Get short shift time format for grid display.
     */
    public function getShiftShortAttribute(): string
    {
        if ($this->is_off_day) {
            return 'OFF';
        }

        if (!$this->shift_start || !$this->shift_end) {
            return '-';
        }

        $start = Carbon::parse($this->shift_start)->format('G');
        $end = Carbon::parse($this->shift_end)->format('G');

        return "{$start}-{$end}";
    }
}
