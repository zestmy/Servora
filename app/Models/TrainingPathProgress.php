<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** How far one trainee has got along one path. */
class TrainingPathProgress extends Model
{
    protected $table = 'training_path_progress';

    protected $fillable = [
        'training_path_id', 'lms_user_id', 'completed_items', 'total_items',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'completed_items' => 'integer',
        'total_items'     => 'integer',
        'started_at'      => 'datetime',
        'completed_at'    => 'datetime',
    ];

    public function path(): BelongsTo
    {
        return $this->belongsTo(TrainingPath::class, 'training_path_id');
    }

    public function trainee(): BelongsTo
    {
        return $this->belongsTo(LmsUser::class, 'lms_user_id');
    }

    public function percent(): float
    {
        return $this->total_items > 0
            ? round($this->completed_items / $this->total_items * 100, 1)
            : 0.0;
    }
}
