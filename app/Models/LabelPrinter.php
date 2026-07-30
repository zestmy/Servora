<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A physical label printer in an outlet.
 *
 * `driver` selects the transport. 'browser' prints through the chef's own
 * Chrome via kiosk printing; 'printnode' is stubbed for the day an outlet
 * needs unattended or tablet-driven printing.
 */
class LabelPrinter extends Model
{
    public const DRIVERS = [
        'browser'   => 'Browser (kiosk printing)',
        'printnode' => 'PrintNode',
    ];

    protected $fillable = [
        'company_id', 'outlet_id', 'name', 'driver', 'printnode_printer_id',
        'default_template_id', 'width_mm', 'height_mm', 'is_active',
        'offset_x_mm', 'offset_y_mm', 'rotate_90',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'rotate_90'   => 'boolean',
        'width_mm'    => 'decimal:2',
        'height_mm'   => 'decimal:2',
        'offset_x_mm' => 'decimal:2',
        'offset_y_mm' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function defaultTemplate(): BelongsTo
    {
        return $this->belongsTo(LabelTemplate::class, 'default_template_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForOutlet(Builder $query, int $outletId): Builder
    {
        return $query->where('outlet_id', $outletId);
    }

    public function driverLabel(): string
    {
        return self::DRIVERS[$this->driver] ?? ucfirst((string) $this->driver);
    }
}
