<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RosterDayRemark extends Model
{
    public const TYPE_PUBLIC_HOLIDAY = 'public_holiday';
    public const TYPE_STOCKTAKE = 'stocktake';
    public const TYPE_EVENT = 'event';
    public const TYPE_CUSTOM = 'custom';

    protected $fillable = [
        'roster_id',
        'day_date',
        'remark_type',
        'remark_text',
    ];

    protected $casts = [
        'day_date' => 'date',
    ];

    public function roster(): BelongsTo
    {
        return $this->belongsTo(Roster::class);
    }

    public function getRemarkTypeLabelAttribute(): string
    {
        return match ($this->remark_type) {
            self::TYPE_PUBLIC_HOLIDAY => 'Public Holiday',
            self::TYPE_STOCKTAKE => 'Stocktake',
            self::TYPE_EVENT => 'Event',
            self::TYPE_CUSTOM => 'Custom',
            default => ucfirst($this->remark_type),
        };
    }

    public function getRemarkTypeColorAttribute(): string
    {
        return match ($this->remark_type) {
            self::TYPE_PUBLIC_HOLIDAY => 'red',
            self::TYPE_STOCKTAKE => 'blue',
            self::TYPE_EVENT => 'purple',
            self::TYPE_CUSTOM => 'gray',
            default => 'gray',
        };
    }

    public static function types(): array
    {
        return [
            self::TYPE_PUBLIC_HOLIDAY => 'Public Holiday',
            self::TYPE_STOCKTAKE => 'Stocktake',
            self::TYPE_EVENT => 'Event',
            self::TYPE_CUSTOM => 'Custom',
        ];
    }
}
