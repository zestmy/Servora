<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentFolder extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'description',
        'google_drive_folder_id',
        'allow_upload',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'allow_upload' => 'boolean',
        'is_active'    => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get the Google Drive embed URL for this folder.
     */
    public function getEmbedUrlAttribute(): string
    {
        return 'https://drive.google.com/embeddedfolderview?id=' . $this->google_drive_folder_id . '#grid';
    }

    /**
     * Get the direct Google Drive folder URL (for uploading).
     */
    public function getDriveUrlAttribute(): string
    {
        return 'https://drive.google.com/drive/folders/' . $this->google_drive_folder_id;
    }
}
