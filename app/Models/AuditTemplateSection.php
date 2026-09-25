<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A section of an audit form — "Bar area", "Kitchen area".
 *
 * No company scope: it belongs to a template, dies with the template, and the
 * template is already scoped.
 */
class AuditTemplateSection extends Model
{
    public const MODE_AREA    = 'area';
    public const MODE_PENALTY = 'penalty';

    protected $fillable = [
        'audit_template_id', 'name', 'name_alt', 'scoring_mode', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(AuditTemplate::class, 'audit_template_id');
    }

    /** Every item in the section, flat, in display order. */
    public function items(): HasMany
    {
        return $this->hasMany(AuditTemplateItem::class)->orderBy('sort_order');
    }

    /** Only the top-level items; their children hang off each one. */
    public function rootItems(): HasMany
    {
        return $this->hasMany(AuditTemplateItem::class)->whereNull('parent_id')->orderBy('sort_order');
    }

    public function isPenalty(): bool
    {
        return $this->scoring_mode === self::MODE_PENALTY;
    }
}
