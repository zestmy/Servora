<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A registered print agent — one outlet PC, one outlet.
 *
 * The self-hosted replacement for PrintNode's client: pairs once, polls for
 * jobs, prints them, reports back. See docs/print-agent-plan.md.
 *
 * @see \App\Services\Labels\PrintAgentService which owns pairing, token
 *      verification and the heartbeat; this model deliberately carries no
 *      credential logic of its own — the same split as ClockDevice.
 */
class PrintAgent extends Model
{
    /**
     * How long a pairing code is good for.
     *
     * Same reasoning as the clock kiosk: the code is read out to somebody
     * standing at the PC, ten minutes is a generous version of that
     * conversation, and a code still live tomorrow is a second credential
     * nobody remembers issuing.
     */
    public const PAIRING_TTL_MINUTES = 10;

    /**
     * How long after its last poll an agent is treated as gone.
     *
     * The agent polls every few seconds, so two minutes is dozens of missed
     * beats — much tighter than the kiosk's ten, because the badge this
     * feeds is read by a chef deciding whether to walk to the printer.
     */
    public const HEARTBEAT_STALE_MINUTES = 2;

    /**
     * Alphabet for pairing codes — ClockDevice's, verbatim. No O/0, no
     * I/1/L: it gets read aloud across a kitchen.
     */
    public const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * The agent version the current install zip ships.
     *
     * MUST match `const Version` in tools/print-agent/main.go — a test
     * reads the Go source and fails on drift, because this constant is the
     * only update mechanism v1 has: the Agents screen nags any agent
     * reporting something older.
     */
    public const CURRENT_VERSION = '1.0.0';

    protected $fillable = [
        'company_id', 'outlet_id', 'name', 'token_hash',
        'pairing_code', 'pairing_expires_at', 'paired_at',
        'last_seen_at', 'last_seen_ip',
        'agent_version', 'hostname', 'os',
        'printers', 'printers_reported_at',
        'revoked_at', 'revoked_by', 'created_by',
    ];

    protected $casts = [
        'pairing_expires_at'   => 'datetime',
        'paired_at'            => 'datetime',
        'last_seen_at'         => 'datetime',
        'printers'             => 'array',
        'printers_reported_at' => 'datetime',
        'revoked_at'           => 'datetime',
    ];

    /**
     * Never serialised, never logged, never rendered. Only a hash, but the
     * one column a leak would make interesting.
     */
    protected $hidden = ['token_hash'];

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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    /** Agents that have not been revoked. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    /** Agents that have completed pairing and hold a token. */
    public function scopePaired(Builder $query): Builder
    {
        return $query->whereNotNull('token_hash');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isPaired(): bool
    {
        return $this->token_hash !== null;
    }

    /**
     * Whether this agent has polled recently enough to be relied on. Feeds
     * the printer status badge, so a chef about to walk to the printer is
     * the reader.
     */
    public function isOnline(): bool
    {
        return ! $this->isRevoked()
            && $this->isPaired()
            && $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subMinutes(self::HEARTBEAT_STALE_MINUTES));
    }

    /**
     * Whether this agent is running an older build than the current zip.
     *
     * version_compare, not inequality: an agent somehow AHEAD of the
     * server's constant (a deploy raced an install) must not be nagged to
     * "update" backwards.
     */
    public function isOutdated(): bool
    {
        return $this->agent_version !== null
            && version_compare($this->agent_version, self::CURRENT_VERSION, '<');
    }

    /** Whether the pairing code on this row can still be redeemed. */
    public function hasLivePairingCode(): bool
    {
        return ! $this->isRevoked()
            && $this->pairing_code !== null
            && $this->pairing_expires_at !== null
            && $this->pairing_expires_at->isFuture();
    }

    /**
     * The printer names this agent reported, for the printer form's picker.
     *
     * @return array<int, array{name: string, is_default: bool, status: ?string, papers: array}>
     */
    public function reportedPrinters(): array
    {
        return collect($this->printers ?? [])
            ->filter(fn ($p) => is_array($p) && isset($p['name']) && is_string($p['name']))
            ->map(fn (array $p) => [
                'name'       => $p['name'],
                'is_default' => (bool) ($p['is_default'] ?? false),
                'status'     => isset($p['status']) && is_string($p['status']) ? $p['status'] : null,
                'papers'     => collect($p['papers'] ?? [])
                    ->filter(fn ($paper) => is_array($paper) && isset($paper['name']) && is_string($paper['name']))
                    ->map(fn (array $paper) => [
                        'name' => $paper['name'],
                        'size' => isset($paper['size']) && is_string($paper['size']) ? $paper['size'] : null,
                    ])->values()->all(),
            ])->values()->all();
    }

    /** One phrase for the agents list: what this PC is doing right now. */
    public function statusLabel(): string
    {
        return match (true) {
            $this->isRevoked()           => 'Revoked',
            ! $this->isPaired()          => $this->hasLivePairingCode() ? 'Waiting to pair' : 'Not paired',
            $this->isOnline()            => 'Online',
            $this->last_seen_at === null => 'Paired, never seen',
            default                      => 'Offline since ' . $this->last_seen_at->diffForHumans(),
        };
    }
}
