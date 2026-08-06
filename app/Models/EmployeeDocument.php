<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A scan held against one employee. See the migration for why these are not in
 * the company Documents library.
 */
class EmployeeDocument extends Model
{
    protected $fillable = [
        'company_id', 'employee_id', 'type', 'label',
        'file_path', 'original_name', 'mime_type', 'size_bytes', 'uploaded_by',
    ];

    protected $casts = ['size_bytes' => 'integer'];

    /**
     * The paperwork a Malaysian F&B employer keeps per employee.
     *
     * `other` is deliberate: the alternative is somebody filing an offer letter
     * under "Application Form" because there was nowhere else to put it.
     */
    public const TYPES = [
        'application_form'  => 'Application Form',
        'identity'          => 'IC / Passport',
        'typhoid_card'      => 'Typhoid Card',
        'food_handler_cert' => 'Food Handler Certificate',
        'other'             => 'Other',
    ];

    /** Types whose expiry is tracked on the employee record itself. */
    public const EXPIRY_FIELDS = [
        'typhoid_card'      => 'typhoid_expired_on',
        'food_handler_cert' => 'food_handler_expired_on',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        // The file is the record. Leaving orphans on disk turns a delete into
        // a lie, and these are IC scans.
        static::deleted(function (self $doc) {
            Storage::disk('local')->delete($doc->file_path);
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    /** e.g. "1.4 MB". */
    public function sizeLabel(): string
    {
        $bytes = (int) $this->size_bytes;

        return $bytes >= 1048576
            ? round($bytes / 1048576, 1) . ' MB'
            : max(1, (int) round($bytes / 1024)) . ' KB';
    }
}
