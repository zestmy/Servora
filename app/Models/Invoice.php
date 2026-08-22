<?php

namespace App\Models;

use App\Models\Concerns\PurgesStoredFiles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Invoice extends Model
{
    use HasFactory, PurgesStoredFiles;

    public const STATUS_DRAFT  = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_PAID   = 'paid';
    public const STATUS_VOID   = 'void';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_DRAFT  => 'Draft',
        self::STATUS_ISSUED => 'Issued',
        self::STATUS_PAID   => 'Paid',
        self::STATUS_VOID   => 'Void',
    ];

    protected static function booted(): void
    {
        // The rendered PDF is a copy of the invoice, not the invoice itself —
        // but it is still a billing document with a customer's details on it,
        // and leaving it served from the public disk after the row has gone
        // is a file nobody can find and nobody will ever remove.
        static::deleted(fn (self $invoice) => $invoice->purgeOwnedFile('pdf_path'));
    }

    protected $fillable = [
        'company_id', 'payment_id', 'subscription_id', 'invoice_number',
        'amount', 'tax_amount', 'tax_rate', 'tax_label', 'total', 'currency',
        'status', 'issued_at', 'period_start', 'period_end', 'due_at',
        'paid_at', 'sent_at', 'voided_at', 'void_reason',
        'pdf_path', 'line_items', 'notes', 'bill_to', 'created_by',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'tax_amount'   => 'decimal:2',
        'tax_rate'     => 'decimal:2',
        'total'        => 'decimal:2',
        'issued_at'    => 'datetime',
        'period_start' => 'date',
        'period_end'   => 'date',
        'due_at'       => 'datetime',
        'paid_at'      => 'datetime',
        'sent_at'      => 'datetime',
        'voided_at'    => 'datetime',
        'line_items'   => 'array',
        'bill_to'      => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * withTrashed for the same reason Payment::subscription() does it: a
     * subscription follows its company into the bin, but the invoices raised
     * against it are accounting records that outlive both.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Companies soft-delete, so an invoice outlives its company and ->company
     * resolves to null on the admin list. Never dereference it for display.
     */
    public function companyName(): string
    {
        return $this->company?->name
            ?? ($this->bill_to['name'] ?? null)
            ?? 'Deleted company';
    }

    // ── Status ─────────────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    /** Money the platform is still owed on this row. */
    public function isOutstanding(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_ISSUED], true);
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_ISSUED
            && $this->due_at !== null
            && $this->due_at->isPast();
    }

    public function statusLabel(): string
    {
        if ($this->isOverdue()) {
            return 'Overdue';
        }

        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Semantic colour name — the view maps it onto badge classes. */
    public function statusColor(): string
    {
        if ($this->isOverdue()) {
            return 'danger';
        }

        return match ($this->status) {
            self::STATUS_PAID   => 'success',
            self::STATUS_ISSUED => 'info',
            self::STATUS_VOID   => 'gray',
            default             => 'warning',
        };
    }

    public function daysOverdue(): int
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        return (int) $this->due_at->startOfDay()->diffInDays(now()->startOfDay());
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_ISSUED]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ISSUED)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * What a customer is allowed to see. A draft is the platform's working
     * copy — it has not been sent, its numbers can still change, and it must
     * never appear on the tenant's billing page.
     */
    public function scopeVisibleToCustomer(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_ISSUED, self::STATUS_PAID, self::STATUS_VOID]);
    }

    // ── Numbering ──────────────────────────────────────────────────────────

    /**
     * Next number in the year's sequence.
     *
     * lockForUpdate, and callers wrap this in a transaction: two invoices
     * raised in the same second previously read the same last number and the
     * second insert died on the unique index. Ordered by invoice_number rather
     * than id so a backdated row cannot hand out a number already taken.
     */
    public static function generateNumber(?int $year = null): string
    {
        $year = $year ?: (int) date('Y');

        $last = static::query()
            ->where('invoice_number', 'like', "INV-{$year}-%")
            ->orderByDesc('invoice_number')
            ->when(DB::connection()->getDriverName() !== 'sqlite', fn ($q) => $q->lockForUpdate())
            ->value('invoice_number');

        $next = $last
            ? ((int) substr($last, strrpos($last, '-') + 1)) + 1
            : 1;

        return sprintf('INV-%s-%04d', $year, $next);
    }
}
