<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One item in a print set.
 *
 * No CompanyScope: lines are reached through their set, which is scoped.
 *
 * label_type and storage_state sit on the LINE, not the set — "Chiller 1" is
 * realistically twelve chill use-by labels and two thawed ones, and a uniform
 * set would be useless for that.
 *
 * Either labelable_* or custom_name must be set.
 */
class LabelSetLine extends Model
{
    protected $fillable = [
        'label_set_id', 'sort_order', 'labelable_type', 'labelable_id',
        'custom_name', 'label_type', 'storage_state', 'copies',
        'quantity', 'uom_id', 'template_id', 'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'copies'     => 'integer',
        'quantity'   => 'decimal:4',
        'is_active'  => 'boolean',
    ];

    public function set(): BelongsTo
    {
        return $this->belongsTo(LabelSet::class, 'label_set_id');
    }

    public function labelable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LabelTemplate::class, 'template_id');
    }

    /**
     * What to print as the item name. Falls back through the linked item to
     * the freeform name, so a line whose item was deleted still shows
     * something rather than blank.
     */
    public function displayName(): string
    {
        return $this->labelable?->name ?? (string) $this->custom_name;
    }
}
