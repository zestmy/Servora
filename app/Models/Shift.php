<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A named shift template used to fill duty roster cells.
 *
 * Applying a shift copies its times onto the entry — see
 * DutyRoster::applyShift(). The entry also keeps shift_id so the grid can show
 * the shift's short code, but the times it was rostered with are its own.
 */
class Shift extends Model
{
    protected $fillable = [
        'company_id', 'outlet_id', 'section_id', 'name', 'code',
        'start_time', 'end_time', 'rest_duration', 'normal_hours',
        'sort_order', 'is_active',
    ];

    protected $casts = [
        'start_time'    => 'datetime:H:i',
        'end_time'      => 'datetime:H:i',
        'rest_duration' => 'integer',
        'normal_hours'  => 'decimal:2',
        'is_active'     => 'boolean',
    ];

    protected $attributes = [
        'rest_duration' => 60,
        'sort_order'    => 0,
        'is_active'     => true,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('start_time')->orderBy('name');
    }

    /**
     * Shifts offered for a given outlet and section.
     *
     * A null outlet or section on the shift means "anywhere" — those always
     * apply. Narrower ones only show where they belong, which is what stops a
     * kitchen shift appearing in a front-of-house dropdown.
     */
    public function scopeFor($query, ?int $outletId, ?int $sectionId = null)
    {
        return $query
            ->where(fn ($q) => $q->whereNull('outlet_id')->orWhere('outlet_id', $outletId))
            ->when($sectionId, fn ($q) => $q->where(
                fn ($s) => $s->whereNull('section_id')->orWhere('section_id', $sectionId)
            ));
    }

    /** "08:00 – 17:00" for display. */
    public function timeLabel(): string
    {
        return $this->start_time->format('H:i') . ' – ' . $this->end_time->format('H:i');
    }

    /** What the roster grid shows in a cell: the code, or the name if none. */
    public function shortLabel(): string
    {
        return $this->code ?: $this->name;
    }

    /**
     * Paid hours this shift represents: the clock span minus the break, over
     * midnight if it ends earlier than it starts.
     */
    public function paidHours(): float
    {
        $start = $this->start_time->copy();
        $end   = $this->end_time->copy();
        if ($end->lte($start)) {
            $end->addDay();
        }

        return round(max(0, $start->diffInMinutes($end) - (int) $this->rest_duration) / 60, 2);
    }
}
