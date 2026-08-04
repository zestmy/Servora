<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One enrolled face capture: the 128-float template, not a photograph.
 *
 * @see \App\Services\Hr\FaceMatcher for the comparison itself.
 */
class EmployeeFaceDescriptor extends Model
{
    /** Length of a face-api.js recognition descriptor. */
    public const DESCRIPTOR_LENGTH = 128;

    /**
     * Which network produced the vectors. Bumping this invalidates nothing
     * automatically — FaceMatcher simply ignores descriptors that were not
     * produced by the model currently in the browser, so a swap degrades to
     * "everyone must re-enrol" rather than to silent mismatching.
     */
    public const MODEL_VERSION = 'face-api-1.7';

    protected $fillable = [
        'company_id', 'employee_id', 'descriptor', 'model_version',
        'photo_path', 'enrolled_by',
    ];

    protected $casts = [
        'descriptor' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function enroller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    /**
     * Whether an incoming array is a usable descriptor.
     *
     * The vector arrives from a browser, so it is attacker-controlled: a
     * short array, a nested one, or one full of nulls would otherwise reach
     * the distance maths and produce a nonsense score that happens to pass.
     */
    public static function isValidDescriptor(mixed $value): bool
    {
        if (! is_array($value) || count($value) !== self::DESCRIPTOR_LENGTH) {
            return false;
        }

        foreach ($value as $component) {
            if (! is_int($component) && ! is_float($component)) {
                return false;
            }
            if (! is_finite((float) $component)) {
                return false;
            }
        }

        return true;
    }
}
