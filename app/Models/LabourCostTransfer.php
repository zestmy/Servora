<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Salary and overtime moved from the outlets that employ people to the outlet
 * they were lent to. See the create migration for the shape.
 */
class LabourCostTransfer extends Model
{
    use SoftDeletes;

    public const PURPOSES = [
        'support'  => 'Outlet support',
        'event'    => 'Event',
        'catering' => 'Outside catering',
        'other'    => 'Other',
    ];

    public const STATUSES = [
        'draft'     => 'Draft',
        'confirmed' => 'Confirmed',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'company_id', 'transfer_number', 'to_outlet_id', 'transfer_date', 'purpose',
        'reference', 'notes', 'status', 'created_by', 'confirmed_by', 'confirmed_at',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'confirmed_at'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function toOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'to_outlet_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(LabourCostTransferLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function purposeLabel(): string
    {
        return self::PURPOSES[$this->purpose] ?? ucfirst((string) $this->purpose);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public static function generateNumber(): string
    {
        $prefix = 'LCT-' . now()->format('Ymd') . '-';
        $last   = static::withoutGlobalScopes()->withTrashed()
            ->where('transfer_number', 'like', $prefix . '%')
            ->orderByDesc('transfer_number')
            ->value('transfer_number');
        $seq = $last ? ((int) substr($last, -3) + 1) : 1;

        return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
