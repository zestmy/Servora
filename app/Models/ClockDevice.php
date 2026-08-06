<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A registered kiosk — one tablet, one outlet.
 *
 * @see \App\Services\Hr\ClockDeviceService which owns pairing, token
 *      verification and the heartbeat; this model deliberately carries no
 *      credential logic of its own.
 */
class ClockDevice extends Model
{
    /**
     * How long a pairing code is good for.
     *
     * Short on purpose. The code is read out to somebody standing there with
     * the tablet, so ten minutes is a generous version of that conversation,
     * and a code still live tomorrow morning is a second credential nobody
     * remembers issuing.
     */
    public const PAIRING_TTL_MINUTES = 10;

    /**
     * How long after its last ping a kiosk is treated as gone.
     *
     * The screen pings every minute, so this tolerates a dozen missed beats —
     * a tablet is on outlet wifi, and a device that blinks out for ninety
     * seconds has not stopped being the place people should punch.
     *
     * Erring long is the safe direction. Too short and a flaky connection
     * starts un-flagging phone punches that ought to be flagged; too long and
     * a genuinely dead tablet keeps flagging phone punches for a few extra
     * minutes, which a manager can see and resolve.
     */
    public const HEARTBEAT_STALE_MINUTES = 10;

    /**
     * Alphabet for pairing codes.
     *
     * No O/0, no I/1/L. This gets read aloud across a counter in a room with
     * an extractor fan running, and "is that an oh or a zero" is the whole
     * reason a pairing fails twice before it works.
     */
    public const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    protected $fillable = [
        'company_id', 'outlet_id', 'name', 'token_hash',
        'pairing_code', 'pairing_expires_at', 'paired_at',
        'last_seen_at', 'last_seen_ip', 'user_agent',
        'revoked_at', 'revoked_by', 'created_by',
    ];

    protected $casts = [
        'pairing_expires_at' => 'datetime',
        'paired_at'          => 'datetime',
        'last_seen_at'       => 'datetime',
        'revoked_at'         => 'datetime',
    ];

    /**
     * Never serialised, never logged, never rendered.
     *
     * It is only a hash, so it cannot be replayed — but it is the one column
     * on this table that a leak would make interesting, and there is no screen
     * that has any reason to show it.
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

    public function events(): HasMany
    {
        return $this->hasMany(ClockEvent::class);
    }

    /** Devices that have not been revoked. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    /** Devices that have completed pairing and hold a token. */
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
     * Whether this kiosk has pinged recently enough to be relied on.
     *
     * Load-bearing: it is what decides whether a phone punch at this outlet
     * gets flagged for bypassing the kiosk, or waved through because there was
     * no kiosk to bypass.
     */
    public function isOnline(): bool
    {
        return ! $this->isRevoked()
            && $this->isPaired()
            && $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subMinutes(self::HEARTBEAT_STALE_MINUTES));
    }

    /** Whether the pairing code on this row can still be redeemed. */
    public function hasLivePairingCode(): bool
    {
        return ! $this->isRevoked()
            && $this->pairing_code !== null
            && $this->pairing_expires_at !== null
            && $this->pairing_expires_at->isFuture();
    }

    /** One phrase for the devices list: what this tablet is doing right now. */
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
