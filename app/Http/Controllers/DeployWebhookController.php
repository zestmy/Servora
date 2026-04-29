<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Secure deploy webhook for running post-deploy artisan commands remotely.
 *
 * Security layers:
 *   1. HMAC-SHA256 signature (same pattern as GitHub/Stripe webhooks)
 *   2. Timestamp replay protection — requests older than 5 minutes are rejected
 *   3. Hard-coded command allowlist — arbitrary commands are impossible
 *   4. Throttled to 10 requests/minute at the route level
 *   5. HTTPS enforced by the server (Nginx/Caddy terminates TLS)
 *
 * Calling convention:
 *   POST /internal/deploy-hook
 *   Headers:
 *     X-Timestamp: <unix timestamp>
 *     X-Signature: <hmac_sha256(secret, "$timestamp:$command")>
 *     Content-Type: application/json
 *   Body:
 *     { "command": "migrate" }
 */
class DeployWebhookController extends Controller
{
    /** Commands that may be triggered remotely. Add here only when needed. */
    private const ALLOWED_COMMANDS = [
        'migrate',
        'config:cache',
        'config:clear',
        'cache:clear',
        'queue:restart',
        'route:cache',
        'route:clear',
        'view:cache',
        'view:clear',
        'optimize',
        'optimize:clear',
    ];

    /** Commands that need --force flag (non-interactive environments). */
    private const FORCE_COMMANDS = ['migrate'];

    /** Max age of a request in seconds (replay protection). */
    private const MAX_DRIFT_SECONDS = 300; // 5 minutes

    public function __invoke(Request $request): JsonResponse
    {
        // ── 1. Timestamp check (replay protection) ───────────────────────────
        $timestamp = $request->header('X-Timestamp');

        if (! $timestamp || ! ctype_digit((string) $timestamp)) {
            $this->deny('Missing or invalid X-Timestamp header.');
        }

        $age = abs(time() - (int) $timestamp);
        if ($age > self::MAX_DRIFT_SECONDS) {
            $this->deny("Request timestamp too old ({$age}s). Max allowed: " . self::MAX_DRIFT_SECONDS . 's.');
        }

        // ── 2. HMAC signature verification ───────────────────────────────────
        $command  = (string) $request->input('command', '');
        $secret   = (string) config('app.deploy_webhook_secret', '');

        if (empty($secret)) {
            Log::error('DeployWebhook: DEPLOY_WEBHOOK_SECRET is not configured.');
            abort(500, 'Webhook secret not configured on server.');
        }

        $expected = hash_hmac('sha256', $timestamp . ':' . $command, $secret);
        $provided = (string) $request->header('X-Signature', '');

        if (! hash_equals($expected, $provided)) {
            $this->deny('Invalid signature.');
        }

        // ── 3. Command allowlist ──────────────────────────────────────────────
        if (! in_array($command, self::ALLOWED_COMMANDS, true)) {
            $this->deny("Command not permitted: [{$command}].");
        }

        // ── 4. Execute ────────────────────────────────────────────────────────
        $args = in_array($command, self::FORCE_COMMANDS) ? ['--force' => true] : [];
        Artisan::call($command, $args);
        $output = trim(Artisan::output());

        Log::info("DeployWebhook: [{$command}] executed successfully.", [
            'ip'     => $request->ip(),
            'output' => $output,
        ]);

        return response()->json([
            'ok'          => true,
            'command'     => $command,
            'output'      => $output,
            'executed_at' => now()->toISOString(),
        ]);
    }

    private function deny(string $reason): never
    {
        Log::warning("DeployWebhook: Denied — {$reason}", [
            'ip' => request()->ip(),
        ]);
        abort(403, $reason);
    }
}
