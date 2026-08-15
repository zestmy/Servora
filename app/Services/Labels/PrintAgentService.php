<?php

namespace App\Services\Labels;

use App\Models\PrintAgent;
use App\Models\User;
use App\Scopes\CompanyScope;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Pairing, credentials and the heartbeat for print agents.
 *
 * ClockDeviceService with the nouns renamed, deliberately — the problem is
 * identical: an on-prem device has to vouch for one company and one outlet,
 * chosen by a manager before the box is trusted, with a credential that can
 * be revoked from the UI. Everything that gates an agent request goes
 * through resolve(), so there is one place to be wrong about what counts as
 * a valid agent.
 *
 * One deliberate difference from the kiosk: NO COOKIE. The caller is a
 * native binary, not a browser — the token lives in the agent's config file
 * and arrives only ever in a header. No human sees it at all, which is
 * narrower even than the kiosk's cookie.
 */
class PrintAgentService
{
    /** Header the JSON endpoints authenticate on. */
    public const HEADER = 'X-Agent-Token';

    /**
     * Don't write last_seen_at more often than this.
     *
     * The agent polls every few seconds; stamping the row on every poll
     * would be an UPDATE per outlet every 3 seconds for a column nothing
     * reads more precisely than "within the last two minutes".
     */
    private const HEARTBEAT_THROTTLE_SECONDS = 55;

    /**
     * Start pairing: put a fresh code on the agent row and return it.
     *
     * Regenerating is always allowed — a lost code, or one whose ten
     * minutes ran out, needs another, and the old code dying with it is
     * correct behaviour rather than a side effect.
     */
    public function issuePairingCode(PrintAgent $agent): string
    {
        $code = $this->uniqueCode($agent->company_id);

        $agent->forceFill([
            'pairing_code'       => $code,
            'pairing_expires_at' => now()->addMinutes(PrintAgent::PAIRING_TTL_MINUTES),
        ])->save();

        return $code;
    }

    /**
     * Redeem a pairing code for an agent token.
     *
     * Returns the RAW token — the only time it exists in readable form.
     * Only its hash is stored; the caller's job is to hand it to the agent
     * in the pair response and forget it.
     *
     * $companyId is the subdomain-resolved company when there is one.
     * Locally (no APP_DOMAIN) there is no subdomain to resolve, so the code
     * is matched across companies — codes are random on a 31^6 space and
     * consumed on use, so ambiguity is theoretical, and production always
     * has the company.
     *
     * @return array{agent: PrintAgent, token: string}|null  null when the
     *         code is wrong, expired, or belongs to a revoked agent.
     */
    public function redeem(?int $companyId, string $code, ?Request $request = null): ?array
    {
        $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($code)) ?? '');

        if ($code === '') {
            return null;
        }

        $agent = PrintAgent::withoutGlobalScope(CompanyScope::class)
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
            // Consumed. A code that stays live after use is a second
            // credential sitting in whatever chat app it was pasted into.
            'pairing_code'       => null,
            'pairing_expires_at' => null,
            'paired_at'          => now(),
            'last_seen_at'       => now(),
            'last_seen_ip'       => $request?->ip(),
        ])->save();

        return ['agent' => $agent, 'token' => $token];
    }

    /**
     * The agent holding this token, or null.
     *
     * Null for every way a token can stop being good — unknown, revoked,
     * outlet closed, wrong company — because all of them mean the same
     * thing to the caller: this PC needs pairing again.
     *
     * The hash is globally unique so the lookup needs no company, but when
     * the request arrived on a tenant subdomain the resolved company is
     * checked against the agent's own: a token lifted from one tenant is
     * dead at another, the same property the kiosk has.
     */
    public function resolve(?int $companyId, ?string $token): ?PrintAgent
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        $agent = PrintAgent::withoutGlobalScope(CompanyScope::class)
            ->where('token_hash', $this->hash($token))
            ->active()
            ->first();

        if (! $agent || ($companyId && $agent->company_id !== $companyId)) {
            return null;
        }

        // The outlet has to still exist and still be open — an agent whose
        // outlet closed last month has nothing left to print for.
        if (! $agent->outlet || ! $agent->outlet->is_active) {
            return null;
        }

        return $agent;
    }

    public function resolveFromRequest(Request $request, ?int $companyId): ?PrintAgent
    {
        $token = $request->header(self::HEADER);

        return $this->resolve($companyId, is_string($token) ? $token : null);
    }

    /**
     * Record that this agent is alive. Throttled — see the constant — and
     * saved quietly: a heartbeat is a hint, not an event.
     */
    public function heartbeat(PrintAgent $agent, ?Request $request = null): void
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
     * Replace the agent's reported printer inventory wholesale.
     *
     * @param  array<int, mixed>  $printers  as reported; shape is validated
     *         on the way OUT (PrintAgent::reportedPrinters), not here — a
     *         malformed report should not error the agent's loop.
     */
    public function reportPrinters(PrintAgent $agent, array $printers, ?string $version = null): void
    {
        $agent->forceFill([
            'printers'             => array_values($printers),
            'printers_reported_at' => now(),
            'agent_version'        => $version !== null ? mb_substr($version, 0, 40) : $agent->agent_version,
        ])->save();
    }

    /**
     * Take an agent out of service.
     *
     * The token dies with it. The ROW stays — jobs point at it, and a PC
     * that walked out of the building is exactly when somebody asks which
     * labels went through it.
     */
    public function revoke(PrintAgent $agent, ?User $by = null): void
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

    /**
     * A code nobody else in this company is holding. Scoped per company
     * because that keeps it short enough to read aloud as the platform
     * grows — ClockDeviceService's reasoning, unchanged.
     */
    private function uniqueCode(int $companyId): string
    {
        $alphabet = PrintAgent::CODE_ALPHABET;
        $length   = strlen($alphabet);

        for ($attempt = 0; $attempt < 12; $attempt++) {
            $code = '';

            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, $length - 1)];
            }

            $taken = PrintAgent::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $companyId)
                ->where('pairing_code', $code)
                ->exists();

            if (! $taken) {
                return $code;
            }
        }

        // Twelve collisions on a 31^6 space means the RNG is broken; fall
        // back to something certainly unique rather than a code in play.
        return strtoupper(Str::random(8));
    }
}
