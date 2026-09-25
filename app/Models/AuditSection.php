<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A section of one audit — the snapshot of a template section, with its own
 * score card. Scores are written by AuditScoreService only.
 */
class AuditSection extends Model
{
    protected $fillable = [
        'audit_id', 'name', 'name_alt', 'scoring_mode', 'sort_order',
        'total_points', 'na_points', 'available_points', 'lost_points',
        'score_points', 'score_percent', 'answered_count', 'leaf_count',
    ];

    protected $casts = [
        'sort_order'       => 'integer',
        'total_points'     => 'integer',
        'na_points'        => 'integer',
        'available_points' => 'integer',
        'lost_points'      => 'integer',
        'score_points'     => 'integer',
        'score_percent'    => 'decimal:2',
        'answered_count'   => 'integer',
        'leaf_count'       => 'integer',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AuditLine::class)->orderBy('sort_order');
    }

    public function isPenalty(): bool
    {
        return $this->scoring_mode === AuditTemplateSection::MODE_PENALTY;
    }

    public function isComplete(): bool
    {
        return $this->leaf_count > 0 && $this->answered_count >= $this->leaf_count;
    }
}
