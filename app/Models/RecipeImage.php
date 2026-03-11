<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class RecipeImage extends Model
{
    protected $fillable = [
        'recipe_id', 'type', 'file_name', 'file_path', 'mime_type', 'file_size', 'sort_order',
    ];

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
