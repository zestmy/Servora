<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RosterEntry extends Model
{
    // Leave type constants
    public const LEAVE_OFF = 'off';      // Regular day off
    public const LEAVE_AL = 'al';        // Annual Leave
    public const LEAVE_RPH = 'rph';      // Replacement Public Holiday
    public const LEAVE_MC = 'mc';        // Medical Leave
    public const LEAVE_RDO = 'rdo';      // Replacement Day Off
    public const LEAVE_CH = 'ch';        // Claim Hours

    public const LEAVE_TYPES = [
        self::LEAVE_OFF => 'Off Day',
        self::LEAVE_AL => 'Annual Leave (AL)',
        self::LEAVE_RPH => 'Replacement PH (RPH)',
        self::LEAVE_MC => 'Medical Leave (MC)',
        self::LEAVE_RDO => 'Replacement Day Off (RDO)',
        self::LEAVE_CH => 'Claim Hours (CH)',
    ];

    public const LEAVE_SHORT = [
        self::LEAVE_OFF => 'OFF',
        self::LEAVE_AL => 'AL',
        self::LEAVE_RPH => 'RPH',
        self::LEAVE_MC => 'MC',
        self::LEAVE_RDO => 'RDO',
        self::LEAVE_CH => 'CH',
    ];

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
        'leave_type',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'day_date' => 'date',
        'hours_worked' => 'decimal:2',
        'planned_ot' => 'decimal:2',
        'rest_duration' => 'integer',
        'planned_ot_manual' => 'boolean',
        'is_off_day' => 'boolean',
        'sort_order' => 'integer',
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
     * Get formatted shift time range (12-hour format).
     */
    public function getShiftRangeAttribute(): string
    {
        if ($this->is_off_day) {
            return 'OFF';
        }

        if (!$this->shift_start || !$this->shift_end) {
            return '-';
        }

        $start = Carbon::parse($this->shift_start)->format('g:iA');
        $end = Carbon::parse($this->shift_end)->format('g:iA');

        return "{$start}-{$end}";
    }

    /**
     * Get short shift time format for grid display (12-hour format).
     */
    public function getShiftShortAttribute(): string
    {
        if ($this->is_off_day) {
            // Return leave type short code
            return self::LEAVE_SHORT[$this->leave_type] ?? 'OFF';
        }

        if (!$this->shift_start || !$this->shift_end) {
            return '-';
        }

        // Format: 9AM-5PM (compact 12-hour format)
        $start = Carbon::parse($this->shift_start);
        $end = Carbon::parse($this->shift_end);

        // Use shorter format without minutes if on the hour
        $startFormat = $start->minute === 0 ? 'gA' : 'g:iA';
        $endFormat = $end->minute === 0 ? 'gA' : 'g:iA';

        return $start->format($startFormat) . '-' . $end->format($endFormat);
    }

    /**
     * Get leave type label.
     */
    public function getLeaveTypeLabelAttribute(): string
    {
        return self::LEAVE_TYPES[$this->leave_type] ?? 'Off Day';
    }

    /**
     * Get regular hours (capped at normal hours setting).
     */
    public function getRegularHoursAttribute(): float
    {
        if ($this->is_off_day || !$this->hours_worked) {
            return 0;
        }

        $settings = RosterSetting::where('outlet_id', $this->roster?->outlet_id)->first();
        $normalHours = $settings?->normal_hours ?? 8.00;

        return min((float) $this->hours_worked, (float) $normalHours);
    }
}
