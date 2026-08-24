<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A label layout: physical size plus a positioned list of fields.
 *
 * `layout` is structured data rather than a Blade file because the template
 * designer has to be able to write it. Shape:
 *
 *   {
 *     "fields": [
 *       {"token": "item.name", "x": 2, "y": 2, "w": 46, "h": 6,
 *        "font_size": 10, "weight": "bold", "align": "left"},
 *       {"token": "static", "x": 2, "y": 20, "text": "Keep refrigerated"}
 *     ]
 *   }
 *
 * x/y/w/h are millimetres from the top-left of the label; font_size is in pt.
 *
 * No rotation property: dompdf does not implement CSS transforms, so a
 * rotated field would render in the browser and silently vanish from the
 * PDF. The two output paths must agree — see LabelRenderService.
 */
class LabelTemplate extends Model
{
    /** Label types and the caption printed on them. */
    public const LABEL_TYPES = [
        'prep'      => 'USE BY',
        'oof'       => 'DEFROSTING',
        'received'  => 'RECEIVED',
        'opened'    => 'OPENED',
        'dry_store' => 'DRY STORE',
        'hot_hold'  => 'HOT HOLDING',
        'cold_hold' => 'COLD HOLDING',
        'custom'    => '',
    ];

    /**
     * Storage state each label type assumes. 'received' resolves to the item's
     * own storage instruction at print time, and 'custom' is always manual, so
     * neither appears here.
     */
    public const DEFAULT_STORAGE_STATE = [
        'prep'      => 'chill',
        'oof'       => 'thawed',
        'opened'    => 'opened',
        'dry_store' => 'ambient',
        // The holding labels record when something went into service, so they
        // borrow the state the food is already in rather than introducing one:
        // hot holding is cooked food kept hot, cold holding is chilled food on
        // display. Both count their holding time off the existing rule for that
        // state, so neither needs a rule of its own.
        'hot_hold'  => 'cooked',
        'cold_hold' => 'chill',
    ];

    /**
     * Tokens a template may position. Rendering ignores unknown tokens rather
     * than failing — a template built before a token was retired must still
     * print.
     */
    public const TOKENS = [
        'item.name'           => 'Item name',
        'item.code'           => 'Item code',
        'label.caption'       => 'Label caption',
        'date.start'          => 'Start day + date + time',
        'date.start_date'     => 'Start day + date',
        'date.start_time'     => 'Start time',
        'date.start_day'      => 'Start day only',
        'date.end'            => 'Use by day + date + time',
        'date.end_date'       => 'Use by day + date',
        'date.end_time'       => 'Use by time',
        'date.end_day'        => 'Use by day only',
        'staff.name'          => 'Staff name',
        'outlet.name'         => 'Outlet name',
        'company.logo'        => 'Company logo',
        'storage.instruction' => 'Storage instruction',
        'quantity'            => 'Quantity + UOM',
        'batch.ref'           => 'Batch reference',
        'footer'              => 'Company footer text',
        'static'              => 'Fixed text',
    ];

    protected $fillable = [
        'company_id', 'name', 'label_type', 'width_mm', 'height_mm',
        'engine', 'layout', 'is_default',
    ];

    protected $casts = [
        'layout'     => 'array',
        'is_default' => 'boolean',
        'width_mm'   => 'decimal:2',
        'height_mm'  => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForType(Builder $query, string $labelType): Builder
    {
        return $query->where('label_type', $labelType);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /** Caption for this template's label type, e.g. "USE BY". */
    public function caption(): string
    {
        return self::LABEL_TYPES[$this->label_type] ?? '';
    }

    /** Positioned fields, tolerating a null or malformed layout. */
    public function fields(): array
    {
        return $this->layout['fields'] ?? [];
    }
}
