<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * One queued SOP PDF export.
 *
 * See the create migration for why this is a table and not a cache entry.
 */
class SopExport extends Model
{
    public const STATUS_QUEUED     = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';

    /** Files older than this are pruned; the row is kept as history. */
    public const KEEP_FILE_HOURS = 48;

    /**
     * How long a row may sit unfinished before the panel stops believing it.
     *
     * A worker killed mid-render (OOM, deploy restart, systemd stop) never gets
     * to write a status, so without this the panel would show a spinner
     * forever and the button would stay disabled. Comfortably longer than the
     * job's own timeout so it only ever catches a genuinely dead run.
     */
    public const STALE_AFTER_MINUTES = 20;

    protected $fillable = [
        'company_id', 'user_id', 'status', 'label', 'filters',
        'progress', 'progress_label', 'phase',
        'file_path', 'filename', 'file_size', 'recipe_count',
        'error', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'filters'      => 'array',
        'progress'     => 'integer',
        'file_size'    => 'integer',
        'recipe_count' => 'integer',
        'started_at'   => 'datetime',
        'finished_at'  => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /** Queued or processing, and recent enough to still be believable. */
    public function scopeRunning($query)
    {
        return $query
            ->whereIn('status', [self::STATUS_QUEUED, self::STATUS_PROCESSING])
            ->where('created_at', '>=', now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_PROCESSING], true)
            && ! $this->isStale();
    }

    /** Unfinished but past the point where a live worker would have reported. */
    public function isStale(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_PROCESSING], true)
            && $this->created_at?->lt(now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /** Completed, and the file has not been pruned out from under it. */
    public function isDownloadable(): bool
    {
        return $this->isCompleted()
            && $this->file_path
            && Storage::disk('local')->exists($this->file_path);
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->file_size;

        if ($bytes <= 0) {
            return '—';
        }

        return $bytes >= 1048576
            ? round($bytes / 1048576, 1) . ' MB'
            : max(1, (int) round($bytes / 1024)) . ' KB';
    }

    /** Delete the rendered file, leaving the row as history. */
    public function forgetFile(): void
    {
        if ($this->file_path && Storage::disk('local')->exists($this->file_path)) {
            Storage::disk('local')->delete($this->file_path);
        }

        $this->forceFill(['file_path' => null])->save();
    }
}
