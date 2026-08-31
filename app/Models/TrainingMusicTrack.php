<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One backing track in a company's library, uploaded once and reusable.
 *
 * Deliberately NOT the source of truth for what a quiz plays. A quiz still
 * carries its own `music_path` and the player reads that; this row is how an
 * author finds a file again without uploading it a second time. Keeping the
 * pointer on the quiz means deleting a track cannot silence a paper that is
 * already published — the file stays on disk and the quiz goes on playing it,
 * which is the safe direction for something that only exists to be chosen from.
 *
 * @see \App\Livewire\Training\QuizBuilder for the picker and the upload.
 */
class TrainingMusicTrack extends Model
{
    protected $table = 'training_music_tracks';

    protected $fillable = [
        'company_id', 'title', 'path', 'original_name', 'size_bytes', 'uploaded_by',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('title');
    }

    /** Where the browser fetches it from. Public disk, as the audio migration set out. */
    public function url(): ?string
    {
        return $this->path ? Storage::disk('public')->url($this->path) : null;
    }

    /** "3.2 MB", for a list where the only other fact is a name. */
    public function sizeLabel(): ?string
    {
        if (! $this->size_bytes) {
            return null;
        }

        return $this->size_bytes >= 1048576
            ? number_format($this->size_bytes / 1048576, 1) . ' MB'
            : max(1, (int) round($this->size_bytes / 1024)) . ' KB';
    }

    /**
     * Add a stored file to the library, or return the row that already has it.
     *
     * Idempotent on (company, path) because the unique index says so and
     * because the alternative is an integrity error surfacing on the save
     * button of a screen where somebody was only trying to pick a song.
     */
    public static function adopt(
        int $companyId,
        string $path,
        string $title,
        ?string $originalName = null,
        ?int $sizeBytes = null,
        ?int $uploadedBy = null,
    ): self {
        $existing = static::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('path', $path)
            ->first();

        if ($existing) {
            return $existing;
        }

        return static::withoutGlobalScope(CompanyScope::class)->create([
            'company_id'    => $companyId,
            'title'         => mb_substr(trim($title) ?: 'Untitled track', 0, 120),
            'path'          => $path,
            'original_name' => $originalName ? mb_substr($originalName, 0, 255) : null,
            'size_bytes'    => $sizeBytes,
            'uploaded_by'   => $uploadedBy,
        ]);
    }
}
