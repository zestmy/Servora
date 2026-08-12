<?php

namespace App\Models;

use App\Models\Concerns\PurgesStoredFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class RecipeImage extends Model
{
    use PurgesStoredFiles;

    protected $fillable = [
        'recipe_id', 'type', 'file_name', 'file_path', 'mime_type', 'file_size', 'sort_order',
    ];

    protected static function booted(): void
    {
        // The recipe form already tidies up when an image is removed one at a
        // time; this covers every other way a row can go — a recipe force
        // deleted, a tenant purged — where nothing was watching.
        //
        // Guarded against a shared path because duplication can produce one:
        // Recipes\Index::copyStoredFile() returns the ORIGINAL path unchanged
        // when the source file is missing, so the copy and the original can
        // point at the same picture.
        static::deleted(fn (self $image) => $image->purgeOwnedFile('file_path'));
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    public function humanSize(): string
    {
        $bytes = $this->file_size;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
