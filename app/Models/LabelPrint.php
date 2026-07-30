<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One printed label line — the compliance record.
 *
 * `payload` is a frozen snapshot of exactly what was printed. Never re-derive
 * a past label from the live item: its shelf life will have changed by the
 * time an auditor asks about it. Read the snapshot, not the relations.
 *
 * `status` is 'sent' under the browser driver. Kiosk printing gives no
 * confirmation back, so this records intent, not outcome.
 */
class LabelPrint extends Model
{
    protected $fillable = [
        'company_id', 'outlet_id', 'batch_id', 'printer_id', 'template_id',
        'labelable_type', 'labelable_id', 'custom_name', 'label_type',
        'storage_state', 'start_at', 'end_at', 'manual_expiry', 'copies',
        'payload', 'status',
        'resolved_at', 'resolved_by', 'resolution', 'wastage_record_id',
    ];

    /** used = consumed; wasted = binned and costed; discarded = binned, not costable. */
    public const RESOLUTIONS = [
        'used'      => 'Used',
        'wasted'    => 'Wasted',
        'discarded' => 'Discarded',
    ];

    protected $casts = [
        'start_at'      => 'datetime',
        'end_at'        => 'datetime',
        'resolved_at'   => 'datetime',
        'manual_expiry' => 'boolean',
        'copies'        => 'integer',
        'payload'       => 'array',
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

    public function batch(): BelongsTo
    {
        return $this->belongsTo(LabelPrintBatch::class, 'batch_id');
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(LabelPrinter::class, 'printer_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LabelTemplate::class, 'template_id');
    }

    public function labelable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The item name as printed. Comes from the snapshot, not the live item,
     * so a rename after the fact doesn't rewrite history.
     */
    public function printedName(): string
    {
        return $this->payload['item_name'] ?? $this->custom_name ?? '';
    }

    /** Labels expiring within a window at an outlet — the expiry dashboard. */
    public function scopeExpiringBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereNotNull('end_at')->whereBetween('end_at', [$from, $to]);
    }

    /** Still sitting in the kitchen as far as Servora knows. */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function wastageRecord(): BelongsTo
    {
        return $this->belongsTo(WastageRecord::class);
    }

    public function scopeForOutlet(Builder $query, int $outletId): Builder
    {
        return $query->where('outlet_id', $outletId);
    }
}
