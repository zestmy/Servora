<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEvent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'outlet_id', 'event_date', 'end_date',
        'title', 'category', 'description', 'impact', 'created_by',
    ];

    protected $casts = [
        'event_date' => 'date',
        'end_date'   => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function outlet(): BelongsTo   { return $this->belongsTo(Outlet::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public static function categoryOptions(): array
    {
        return [
            'holiday'      => 'Public Holiday',
            'promotion'    => 'Promotion / Campaign',
            'operational'  => 'Operational (staffing, maintenance)',
            'menu_change'  => 'Menu Change / Launch',
            'external'     => 'External Event (weather, competition)',
            'other'        => 'Other',
        ];
    }

    public static function impactOptions(): array
    {
        return [
            'positive' => 'Positive',
            'negative' => 'Negative',
            'neutral'  => 'Neutral',
        ];
    }

    public function categoryLabel(): string
    {
        return static::categoryOptions()[$this->category] ?? ucfirst($this->category);
    }

    /**
     * Events (company-scoped) covering any day in [$from, $to] for the given
     * outlets. An event with null outlet_id applies to every outlet. Returns
     * an empty collection when the range is incomplete.
     */
    public static function coveringRange(array $outletIds, $from, $to)
    {
        if (! $from || ! $to) {
            return collect();
        }

        return static::where(function ($q) use ($from, $to) {
                $q->whereBetween('event_date', [$from, $to])
                  ->orWhere(function ($q2) use ($from, $to) {
                      $q2->whereNotNull('end_date')
                         ->where('event_date', '<=', $to)
                         ->where('end_date', '>=', $from);
                  });
            })
            ->where(function ($q) use ($outletIds) {
                $q->whereNull('outlet_id');
                if (! empty($outletIds)) {
                    $q->orWhereIn('outlet_id', $outletIds);
                }
            })
            ->orderBy('event_date')
            ->get();
    }

    /**
     * Subset of a pre-fetched $events collection that falls on $date for the
     * given outlet (a null-outlet event applies everywhere). Handles multi-day
     * events via event_date..end_date.
     */
    public static function onDate($events, $date, ?int $outletId)
    {
        $ds = ($date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date))->toDateString();

        return $events->filter(function ($e) use ($ds, $outletId) {
            if ($e->outlet_id !== null && (int) $e->outlet_id !== (int) $outletId) {
                return false;
            }
            $start = $e->event_date->toDateString();
            $end   = ($e->end_date ?? $e->event_date)->toDateString();

            return $ds >= $start && $ds <= $end;
        });
    }

    public function scopeForPeriod($query, string $period)
    {
        $start = \Carbon\Carbon::createFromFormat('!Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('event_date', [$start, $end])
              ->orWhere(function ($q2) use ($start, $end) {
                  $q2->whereNotNull('end_date')
                     ->where('event_date', '<=', $end)
                     ->where('end_date', '>=', $start);
              });
        });
    }
}
