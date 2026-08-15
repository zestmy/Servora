<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One document queued for one print agent — the server half of the
 * self-hosted PrintNode replacement.
 *
 * One batch = one PDF = one job, exactly as under PrintNode; the batch's
 * driver_job_id points here. The payload is the rendered PDF and is NULLED
 * the moment the job reaches a terminal status: the skeleton row stays for
 * debugging until labels:prune-print-jobs removes it.
 */
class PrintJob extends Model
{
    /** pending → delivered → done | error | expired */
    public const STATUSES = ['pending', 'delivered', 'done', 'error', 'expired'];

    public const TERMINAL_STATUSES = ['done', 'error', 'expired'];

    /**
     * A label is wanted NOW. A job nobody collected inside this window means
     * the outlet PC is off, and the chef fell back to browser printing long
     * ago — expiring it keeps the compliance record honest instead of
     * claiming a label that never existed.
     */
    public const TTL_MINUTES = 10;

    /**
     * A job handed to the agent but not acked inside this window is offered
     * again on the next poll — the agent crashed mid-print. This can
     * double-print inside the window, and that is the accepted trade: a
     * duplicate label is peeled and binned, a missing one is a compliance
     * gap.
     */
    public const REDELIVER_AFTER_MINUTES = 2;

    /** Terminal rows are kept this long for debugging, payload already gone. */
    public const KEEP_DAYS = 7;

    protected $fillable = [
        'company_id', 'print_agent_id', 'label_printer_id',
        'printer_name', 'title', 'payload', 'options',
        'status', 'claimed_at', 'finished_at', 'expires_at', 'error_message',
    ];

    protected $casts = [
        'options'     => 'array',
        'claimed_at'  => 'datetime',
        'finished_at' => 'datetime',
        'expires_at'  => 'datetime',
    ];

    /** The PDF has no business in JSON responses or logs. */
    protected $hidden = ['payload'];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(PrintAgent::class, 'print_agent_id');
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(LabelPrinter::class, 'label_printer_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    /**
     * What the poll hands out: pending jobs, plus delivered ones the agent
     * never acked (crashed mid-print — see REDELIVER_AFTER_MINUTES).
     */
    public function scopeCollectable(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('status', 'pending')
                ->orWhere(function (Builder $q) {
                    $q->where('status', 'delivered')
                        ->where('claimed_at', '<', now()->subMinutes(self::REDELIVER_AFTER_MINUTES));
                });
        })->where('expires_at', '>', now());
    }

    /**
     * Reached a terminal state: record it and drop the payload in the same
     * write — the PDF's job is done and the blob is the growth risk.
     */
    public function finish(string $status, ?string $error = null): void
    {
        $this->forceFill([
            'status'        => $status,
            'finished_at'   => now(),
            'error_message' => $error ? mb_substr($error, 0, 500) : null,
            'payload'       => null,
        ])->save();
    }
}
