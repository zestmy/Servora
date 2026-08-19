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
        'custom_name', 'label_type', 'storage_state',
        'shelf_life_value', 'shelf_life_unit', 'copies',
        'quantity', 'uom_id', 'template_id', 'is_active',
    ];

    protected $casts = [
        'sort_order'       => 'integer',
        'copies'           => 'integer',
        'quantity'         => 'decimal:4',
        'shelf_life_value' => 'decimal:2',
        'is_active'        => 'boolean',
    ];

    /**
     * Freeform names are uppercased on the way in, the same as ingredients
     * and recipes are. Done here rather than at each of the four screens
     * that can create a line, so none of them can forget.
     */
    public function setCustomNameAttribute($value): void
    {
        $this->attributes['custom_name'] = $value === null || trim((string) $value) === ''
            ? null
            : \App\Services\Labels\LabelName::normalise($value);
    }

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

    /**
     * The line's own shelf life, shaped as a resolved rule, or null when the
     * line follows the rules chain. When set it is the most specific answer
     * there is — this set, this line — so it beats every rule at print time.
     */
    public function shelfLifeOverride(): ?array
    {
        if (! ((float) $this->shelf_life_value > 0) || empty($this->shelf_life_unit)) {
            return null;
        }

        return [
            'value'  => (float) $this->shelf_life_value,
            'unit'   => $this->shelf_life_unit,
            'source' => 'set_line',
        ];
    }
}
