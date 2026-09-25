<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Assets arriving at an outlet (a receipt) or leaving it (a disposal).
 *
 * One model, two types: the documents are the same shape and the register has
 * to add them up together, so splitting them into two tables would buy nothing
 * but a join.
 */
class AssetMovement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'outlet_id', 'movement_type', 'reference_number', 'supplier_id',
        'purchase_request_id', 'goods_received_note_id', 'stock_transfer_order_id', 'credit_note_id', 'department_id', 'movement_date', 'reason', 'notes',
        'total_cost', 'created_by',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'total_cost'    => 'decimal:4',
    ];

    public const TYPE_RECEIPT  = 'receipt';
    public const TYPE_DISPOSAL = 'disposal';

    /** Why an asset left. Free text would make these unreportable. */
    public const REASONS = [
        'broken'      => 'Broken / beyond repair',
        'lost'        => 'Lost or missing',
        'written_off' => 'Written off',
        'sold'        => 'Sold',
        'returned'    => 'Returned to supplier',
        'other'       => 'Other',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function goodsReceivedNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNote::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function stockTransferOrder(): BelongsTo
    {
        return $this->belongsTo(StockTransferOrder::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AssetMovementLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeReceipts(Builder $q): Builder
    {
        return $q->where('movement_type', self::TYPE_RECEIPT);
    }

    public function scopeDisposals(Builder $q): Builder
    {
        return $q->where('movement_type', self::TYPE_DISPOSAL);
    }

    public function isReceipt(): bool
    {
        return $this->movement_type === self::TYPE_RECEIPT;
    }

    /** +1 for a receipt, -1 for a disposal. The register never hard-codes this. */
    public function signum(): int
    {
        return $this->isReceipt() ? 1 : -1;
    }

    public function typeLabel(): string
    {
        return $this->isReceipt() ? 'Receipt' : 'Disposal';
    }

    public function reasonLabel(): ?string
    {
        return $this->reason ? (self::REASONS[$this->reason] ?? $this->reason) : null;
    }
}
