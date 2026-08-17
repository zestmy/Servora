<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A registered POS sync agent — one POS terminal, one outlet.
 *
 * Pairs exactly like a PrintAgent (that model's trust notes apply verbatim)
 * but pushes sales report files UP instead of pulling print jobs down.
 *
 * @see \App\Services\Sales\PosAgentService for pairing, token verification
 *      and the heartbeat; the model carries no credential logic.
 */
class PosAgent extends Model
{
    /** Same reasoning as PrintAgent: the code is one read-aloud conversation. */
    public const PAIRING_TTL_MINUTES = 10;

    /**
     * How long after its last ping an agent is treated as gone.
     *
     * The sync agent pings every ~5 minutes, not every 3 seconds — sales
     * sync has no chef standing at a printer. Twelve minutes is two missed
     * pings plus jitter.
     */
    public const HEARTBEAT_STALE_MINUTES = 12;

    /** No O/0, no I/1/L — read aloud over the phone to an outlet. */
    public const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * The agent version the current install zip ships. MUST match
     * `const Version` in tools/pos-agent/main.go — a drift test reads the Go
     * source, because the version nag on the POS Sync screen is the only
     * update mechanism v1 has.
     */
    public const CURRENT_VERSION = '1.0.0';

    /** Default minutes between sync passes, overridable per agent via config. */
    public const DEFAULT_SYNC_INTERVAL_MINUTES = 60;

    protected $fillable = [
        'company_id', 'outlet_id', 'name', 'token_hash',
        'pairing_code', 'pairing_expires_at', 'paired_at',
        'last_seen_at', 'last_seen_ip',
        'agent_version', 'hostname', 'os', 'config',
        'revoked_at', 'revoked_by', 'created_by',
    ];

    protected $casts = [
        'pairing_expires_at' => 'datetime',
        'paired_at'          => 'datetime',
        'last_seen_at'       => 'datetime',
        'config'             => 'array',
        'revoked_at'         => 'datetime',
    ];

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

    public function batches(): HasMany
    {
        return $this->hasMany(PosSalesBatch::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

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

    public function isOnline(): bool
    {
        return ! $this->isRevoked()
            && $this->isPaired()
            && $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subMinutes(self::HEARTBEAT_STALE_MINUTES));
    }

    /** version_compare, not inequality — see PrintAgent::isOutdated(). */
    public function isOutdated(): bool
    {
        return $this->agent_version !== null
            && version_compare($this->agent_version, self::CURRENT_VERSION, '<');
    }

    public function hasLivePairingCode(): bool
    {
        return ! $this->isRevoked()
            && $this->pairing_code !== null
            && $this->pairing_expires_at !== null
            && $this->pairing_expires_at->isFuture();
    }

    public function syncIntervalMinutes(): int
    {
        $minutes = (int) ($this->config['sync_interval_minutes'] ?? 0);

        return $minutes > 0 ? $minutes : self::DEFAULT_SYNC_INTERVAL_MINUTES;
    }

    /** One phrase for the agents list: what this terminal is doing right now. */
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
