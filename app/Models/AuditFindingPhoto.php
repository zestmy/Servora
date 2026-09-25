<?php

namespace App\Models;

use App\Models\Concerns\PurgesStoredFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A photograph of a non-conformance, taken on the auditor's phone at the spot.
 *
 * The public disk, like asset and recipe photos: it is a picture of a dirty
 * grease trap, not an identity document, and the report draws several of
 * them at once.
 */
class AuditFindingPhoto extends Model
{
    use PurgesStoredFiles;

    protected $fillable = ['audit_finding_id', 'file_path', 'caption', 'uploaded_by'];

    protected static function booted(): void
    {
        static::deleted(fn (self $photo) => $photo->purgeOwnedFile('file_path'));
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(AuditFinding::class, 'audit_finding_id');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}
