<?php

namespace App\Http\Controllers\Labels;

use App\Http\Controllers\Controller;
use App\Http\Middleware\PrintAgentAuthenticate;
use App\Models\PrintAgent;
use App\Models\PrintJob;
use App\Scopes\CompanyScope;
use App\Services\Labels\AgentJobs;
use App\Services\Labels\PrintAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The print agent's whole wire surface: pair, poll, ack, report printers.
 *
 * Everything here answers JSON to a native binary, never HTML to a person.
 * Error bodies are written to be shown in the agent's local log, so they
 * say what to do, not what class threw.
 */
class PrintAgentController extends Controller
{
    /** Jobs handed out per poll. A kitchen never queues more than this. */
    private const POLL_LIMIT = 10;

    public function __construct(
        private PrintAgentService $agents,
        private AgentJobs $jobs,
    ) {
    }

    /**
     * Redeem a pairing code for the agent's token.
     *
     * The one unauthenticated POST — the code IS the credential, worth ten
     * minutes and one outlet. CSRF-exempt because the caller has no cookies
     * for CSRF to spend (unlike the kiosk's pairing form, which is a
     * browser page and stays protected).
     */
    public function pair(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'     => 'required|string|max:20',
            'hostname' => 'nullable|string|max:120',
            'os'       => 'nullable|string|max:60',
            'version'  => 'nullable|string|max:40',
        ]);

        $companyId = app()->bound('currentCompany') ? app('currentCompany')->id : null;

        $redeemed = $this->agents->redeem($companyId, $data['code'], $request);

        if (! $redeemed) {
            return response()->json([
                'status'  => 'invalid',
                'message' => 'That pairing code is wrong or has expired. Ask a manager to issue a new one.',
            ], 422);
        }

        $agent = $redeemed['agent'];

        $agent->forceFill([
            'hostname'      => isset($data['hostname']) ? mb_substr($data['hostname'], 0, 120) : $agent->hostname,
            'os'            => isset($data['os']) ? mb_substr($data['os'], 0, 60) : $agent->os,
            'agent_version' => isset($data['version']) ? mb_substr($data['version'], 0, 40) : $agent->agent_version,
        ])->save();

        return response()->json([
            'status' => 'paired',
            // The only time the raw token ever leaves the server.
            'token'  => $redeemed['token'],
            'agent'  => [
                'id'     => $agent->id,
                'name'   => $agent->name,
                'outlet' => $agent->outlet?->name,
            ],
        ]);
    }

    /**
     * The poll — and, via the middleware's heartbeat, the pulse.
     *
     * Payloads ride inline as base64 rather than behind a second download
     * URL: a batch PDF is tens of KB, and one round trip per poll is the
     * entire latency budget.
     */
    public function jobs(): JsonResponse
    {
        $agent = $this->agent();

        $jobs = PrintJob::withoutGlobalScope(CompanyScope::class)
            ->where('print_agent_id', $agent->id)
            ->collectable()
            ->orderBy('id')
            ->limit(self::POLL_LIMIT)
            ->get();

        $out = [];

        foreach ($jobs as $job) {
            $job->forceFill([
                'status'     => 'delivered',
                'claimed_at' => now(),
            ])->save();

            $out[] = [
                'id'             => $job->id,
                'printer_name'   => $job->printer_name,
                'title'          => $job->title,
                'options'        => $job->options ?: (object) [],
                'payload_base64' => base64_encode((string) $job->payload),
            ];
        }

        return response()->json(['jobs' => $out]);
    }

    /**
     * Terminal ack: done or error, nothing in between. The agent reports
     * once per job; a repeat after a crash is ignored (first answer wins).
     */
    public function jobStatus(Request $request): JsonResponse
    {
        // By name, not by signature position: the production mount puts
        // {companySlug} before {job}, and a positional scalar receives the
        // slug there. The local /agent-api mount has no slug parameter, so
        // only the subdomain shape ever saw the mismatch.
        $job = (int) $request->route('job');

        $data = $request->validate([
            'status'  => 'required|in:done,error',
            'message' => 'nullable|string|max:500',
        ]);

        $agent = $this->agent();

        // Looked up through the agent that owns it, never by bare id — a
        // token must not be able to settle another outlet's jobs.
        $row = PrintJob::withoutGlobalScope(CompanyScope::class)
            ->where('print_agent_id', $agent->id)
            ->where('id', $job)
            ->first();

        if (! $row) {
            return response()->json([
                'status'  => 'unknown',
                'message' => 'No such job for this agent.',
            ], 404);
        }

        $this->jobs->finish($row, $data['status'], $data['message'] ?? null);

        return response()->json(['status' => 'recorded']);
    }

    /**
     * Replace the reported printer inventory. Sent on startup and every few
     * minutes; the printer form's picker reads it instead of calling out to
     * anything.
     */
    public function printers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'printers'   => 'present|array|max:100',
            'printers.*' => 'array',
            'version'    => 'nullable|string|max:40',
        ]);

        $this->agents->reportPrinters($this->agent(), $data['printers'], $data['version'] ?? null);

        return response()->json(['status' => 'recorded']);
    }

    private function agent(): PrintAgent
    {
        return app(PrintAgentAuthenticate::AGENT_KEY);
    }
}
