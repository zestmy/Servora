<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One line of one audit: the snapshot of a template item plus the result.
 *
 * `result` is what the auditor tapped — ok, nc or na — and is NULL until they
 * tap. A leaf with a NULL result is what stops an audit being submitted.
 * `points_lost` only means anything when the result is nc; it defaults to the
 * full points of the line and the auditor can lower it.
 */
class AuditLine extends Model
{
    public const RESULT_OK = 'ok';
    public const RESULT_NC = 'nc';
    public const RESULT_NA = 'na';

    public const RESULTS = [self::RESULT_OK, self::RESULT_NC, self::RESULT_NA];

    protected $fillable = [
        'audit_id', 'audit_section_id', 'parent_id', 'template_item_id', 'number', 'label',
        'label_alt', 'hint', 'type', 'info_type', 'points', 'is_leaf', 'sort_order',
        'result', 'points_lost', 'subject', 'info_value', 'note',
    ];

    protected $casts = [
        'points'      => 'integer',
        'points_lost' => 'integer',
        'is_leaf'     => 'boolean',
        'sort_order'  => 'integer',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(AuditSection::class, 'audit_section_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function finding(): HasOne
    {
        return $this->hasOne(AuditFinding::class);
    }

    /** A scored leaf — the only kind of line that takes a result. */
    public function isScorable(): bool
    {
        return $this->is_leaf && $this->type !== AuditTemplateItem::TYPE_INFO;
    }

    public function isNonConformance(): bool
    {
        return $this->result === self::RESULT_NC;
    }
}
