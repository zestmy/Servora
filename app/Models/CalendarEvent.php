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
