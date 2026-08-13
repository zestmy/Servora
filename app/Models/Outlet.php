<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Outlet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'code', 'phone', 'address', 'country', 'state', 'is_active',
        'default_kitchen_id', 'default_cpu_id',
        // Clock-in geofence. Absent from this list, update() dropped them
        // silently while the settings screen still reported a save — the
        // location looked set and never was.
        'latitude', 'longitude', 'clock_radius_m', 'punch_mode',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'latitude'       => 'decimal:7',
        'longitude'      => 'decimal:7',
        'clock_radius_m' => 'integer',
    ];

    /** Where this outlet's staff are expected to punch. */
    public const PUNCH_KIOSK_ONLY = 'kiosk_only';
    public const PUNCH_BYOD_ONLY  = 'byod_only';
    public const PUNCH_BOTH       = 'both';

    public const PUNCH_MODE_LABELS = [
        self::PUNCH_KIOSK_ONLY => 'Kiosk only',
        self::PUNCH_BYOD_ONLY  => 'Own devices only',
        self::PUNCH_BOTH       => 'Kiosk and own devices',
    ];

    public const PUNCH_MODE_HINTS = [
        self::PUNCH_KIOSK_ONLY => 'Everyone uses the outlet tablet. A punch from a phone is still recorded, but it is flagged for review unless that person is allowed their own device.',
        self::PUNCH_BYOD_ONLY  => 'No kiosk here. Staff punch on their own phones, with GPS and a selfie.',
        self::PUNCH_BOTH       => 'Either is fine and neither is flagged. For an outlet mid-rollout, or one whose kiosk covers only part of the building.',
    ];

    public function punchMode(): string
    {
        return in_array($this->punch_mode, array_keys(self::PUNCH_MODE_LABELS), true)
            ? $this->punch_mode
            : self::PUNCH_BYOD_ONLY;
    }

    public function punchModeLabel(): string
    {
        return self::PUNCH_MODE_LABELS[$this->punchMode()];
    }

    /**
     * Whether a punch from somebody's own phone is the expected thing here.
     *
     * Note what this does NOT decide: a false answer never refuses a punch. It
     * only means the punch is flagged, because a flat battery must not be able
     * to cost somebody a day's attendance.
     */
    public function expectsKiosk(): bool
    {
        return $this->punchMode() === self::PUNCH_KIOSK_ONLY;
    }

    /** Registered kiosks at this outlet. */
    public function clockDevices(): HasMany
    {
        return $this->hasMany(ClockDevice::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Central kitchen that fulfils this outlet's prep items. */
    public function defaultKitchen(): BelongsTo
    {
        return $this->belongsTo(CentralKitchen::class, 'default_kitchen_id');
    }

    /** Central purchasing unit that consolidates this outlet's requests. */
    public function defaultCpu(): BelongsTo
    {
        return $this->belongsTo(CentralPurchasingUnit::class, 'default_cpu_id');
    }

    /** Users assigned to this outlet (via pivot). */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function stockTakes(): HasMany
    {
        return $this->hasMany(StockTake::class);
    }

    /** Recipes tagged to this outlet. */
    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class)->withTimestamps();
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(OutletGroup::class, 'outlet_outlet_group')->withTimestamps();
    }

    /**
     * Exclude outlets that serve as a central kitchen. Central kitchens handle
     * production rather than retail sales, so they should not appear in sales
     * analytics or emailed sales reports (AI Analytics, scheduled reports).
     */
    /**
     * Outlets a picker may offer: the open ones, plus whatever this record
     * already points at.
     *
     * A dropdown filtered to `is_active` stops containing the value of the
     * record it is editing the moment that outlet is closed. The select then
     * renders with the wrong row highlighted — browsers fall back to the first
     * option — so the form shows an employee posted somewhere they are not,
     * and one touch of the field moves them there for real.
     *
     * Closing an outlet is a statement about NEW work, not a retraction of the
     * records already filed against it. So the shut ones stay offered wherever
     * they are already the answer, and nowhere else.
     *
     * @param  array<int, int|string|null>|int|string|null  $keep  ids already on the record
     */
    public function scopeSelectable($query, $keep = [])
    {
        $keep = collect(is_array($keep) ? $keep : [$keep])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        return $query->where(function ($q) use ($keep) {
            $q->where('is_active', true);

            if ($keep) {
                $q->orWhereIn('id', $keep);
            }
        });
    }

    public function scopeExcludingCentralKitchens($query)
    {
        return $query->whereNotIn('id', function ($sub) {
            $sub->select('outlet_id')
                ->from('central_kitchens')
                ->whereNotNull('outlet_id')
                ->whereNull('deleted_at');
        });
    }
}
