<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One line of an audit form. A leaf carries points; a parent is a heading.
 *
 * TYPES
 *   check    the ordinary item: OK / non-conformance / not applicable
 *   product  a slot the auditor fills with a product on the day ("Toast:
 *            Kaya & butter"), scored through its child criteria — correct
 *            procedure, correct ingredients, correct tools, presentation
 *   info     an unscored answer: a number, a time, a note
 */
class AuditTemplateItem extends Model
{
    public const TYPE_CHECK   = 'check';
    public const TYPE_PRODUCT = 'product';
    public const TYPE_INFO    = 'info';

    public const TYPES = [self::TYPE_CHECK, self::TYPE_PRODUCT, self::TYPE_INFO];

    public const INFO_TYPES = ['text', 'number', 'time', 'textarea'];

    protected $fillable = [
        'audit_template_section_id', 'parent_id', 'number', 'label', 'label_alt', 'hint',
        'type', 'info_type', 'points', 'sort_order',
    ];

    protected $casts = [
        'points'     => 'integer',
        'sort_order' => 'integer',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(AuditTemplateSection::class, 'audit_template_section_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function isScored(): bool
    {
        return $this->type !== self::TYPE_INFO;
    }
}
