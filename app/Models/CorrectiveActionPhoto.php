<?php

namespace App\Models;

use App\Models\Concerns\PurgesStoredFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A photograph on a corrective action: the owner's evidence of the fix, or
 * the auditor's verification. See the migration for why they are two kinds.
 */
class CorrectiveActionPhoto extends Model
{
    use PurgesStoredFiles;

    public const KIND_EVIDENCE     = 'evidence';
    public const KIND_VERIFICATION = 'verification';

    /** Per kind, per action. Six is what the finding allows too. */
    public const MAX_PER_KIND = 6;

    protected $fillable = ['corrective_action_id', 'kind', 'file_path', 'caption', 'uploaded_by', 'uploaded_by_employee_id'];

    protected static function booted(): void
    {
        static::deleted(fn (self $photo) => $photo->purgeOwnedFile('file_path'));
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(CorrectiveAction::class, 'corrective_action_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function uploadedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'uploaded_by_employee_id');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    public function isVerification(): bool
    {
        return $this->kind === self::KIND_VERIFICATION;
    }
}
