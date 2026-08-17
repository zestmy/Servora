<?php

namespace App\Services\Sales;

use App\Models\PosAgent;
use App\Models\User;
use App\Scopes\CompanyScope;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Pairing, credentials and the heartbeat for POS sync agents.
 *
 * PrintAgentService with the nouns renamed, deliberately — the problem is
 * identical (an on-prem device vouching for one company and one outlet) and
 * the fixes for its sharp edges are inherited rather than rediscovered:
 * header-only token, all-failures-collapse-to-null resolve(), single-use
 * pairing codes, throttled quiet heartbeat.
 */
class PosAgentService
{
    /** Header the JSON endpoints authenticate on. */
    public const HEADER = 'X-Agent-Token';

    /**
     * Don't write last_seen_at more often than this. The sync agent pings
     * every ~5 minutes; the throttle exists so a tight retry loop after an
     * error can't turn into an UPDATE per attempt.
     */
    private const HEARTBEAT_THROTTLE_SECONDS = 240;

    public function issuePairingCode(PosAgent $agent): string
    {
        $code = $this->uniqueCode($agent->company_id);

        $agent->forceFill([
            'pairing_code'       => $code,
            'pairing_expires_at' => now()->addMinutes(PosAgent::PAIRING_TTL_MINUTES),
        ])->save();

        return $code;
    }

    /**
     * Redeem a pairing code for an agent token. Returns the RAW token — the
     * only time it exists in readable form. See PrintAgentService::redeem()
     * for why $companyId may be null locally.
     *
     * @return array{agent: PosAgent, token: string}|null
     */
    public function redeem(?int $companyId, string $code, ?Request $request = null): ?array
    {
        $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($code)) ?? '');

        if ($code === '') {
            return null;
        }

        $agent = PosAgent::withoutGlobalScope(CompanyScope::class)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('pairing_code', $code)
            ->active()
            ->first();

        if (! $agent || ! $agent->hasLivePairingCode()) {
            return null;
        }

        $token = Str::random(64);

        $agent->forceFill([
            'token_hash'         => $this->hash($token),
            'pairing_code'       => null,
            'pairing_expires_at' => null,
            'paired_at'          => now(),
            'last_seen_at'       => now(),
            'last_seen_ip'       => $request?->ip(),
        ])->save();

        return ['agent' => $agent, 'token' => $token];
    }

    /**
     * The agent holding this token, or null — for every way a token can
     * stop being good, because they all mean "this terminal needs pairing
     * again" to the caller.
     */
    public function resolve(?int $companyId, ?string $token): ?PosAgent
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        $agent = PosAgent::withoutGlobalScope(CompanyScope::class)
            ->where('token_hash', $this->hash($token))
            ->active()
            ->first();

        if (! $agent || ($companyId && $agent->company_id !== $companyId)) {
            return null;
        }

        if (! $agent->outlet || ! $agent->outlet->is_active) {
            return null;
        }

        return $agent;
    }

    public function resolveFromRequest(Request $request, ?int $companyId): ?PosAgent
    {
        $token = $request->header(self::HEADER);

        return $this->resolve($companyId, is_string($token) ? $token : null);
    }

    public function heartbeat(PosAgent $agent, ?Request $request = null): void
    {
        if ($agent->last_seen_at
            && $agent->last_seen_at->gt(now()->subSeconds(self::HEARTBEAT_THROTTLE_SECONDS))) {
            return;
        }

        $agent->forceFill([
            'last_seen_at' => now(),
            'last_seen_ip' => $request?->ip() ?? $agent->last_seen_ip,
        ])->saveQuietly();
    }

    /**
     * Take an agent out of service. The token dies; the row stays — batches
     * point at it, and "what did that terminal upload" is exactly the
     * question a decommissioned till gets asked.
     */
    public function revoke(PosAgent $agent, ?User $by = null): void
    {
        $agent->forceFill([
            'revoked_at'         => now(),
            'revoked_by'         => $by?->id,
            'token_hash'         => null,
            'pairing_code'       => null,
            'pairing_expires_at' => null,
        ])->save();
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function uniqueCode(int $companyId): string
    {
        $alphabet = PosAgent::CODE_ALPHABET;
        $length   = strlen($alphabet);

        for ($attempt = 0; $attempt < 12; $attempt++) {
            $code = '';

            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, $length - 1)];
            }

            $taken = PosAgent::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $companyId)
                ->where('pairing_code', $code)
                ->exists();

            if (! $taken) {
                return $code;
            }
        }

        return strtoupper(Str::random(8));
    }
}
