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

        /*
         * The capture is the record, so the image goes with it.
         *
         * The same rule EmployeeDocument follows, and here for a stronger
         * reason: this is a photograph of somebody's face held for biometric
         * matching. A row deleted while its file stays leaves that image on
         * disk with nothing pointing at it — unreachable through the product
         * and therefore never reviewed, never exported on a data request, and
         * never deleted.
         *
         * NOT soft-deleted, unlike ClockEvent — a bad capture is discarded and
         * re-taken, never restored — so `deleted` is the right hook and there
         * is nothing left to need the file afterwards.
         *
         * This lives on the model rather than at the one call site that used
         * to do it by hand: a second place that deletes a descriptor would
         * otherwise have to remember, and the whole point of putting it here
         * is that it cannot be forgotten.
         */
        static::deleted(function (self $descriptor) {
            if (filled($descriptor->photo_path)) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($descriptor->photo_path);
            }
        });
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
