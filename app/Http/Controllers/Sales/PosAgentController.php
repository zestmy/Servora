<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Middleware\PosAgentAuthenticate;
use App\Jobs\ProcessPosSalesBatch;
use App\Models\PosAgent;
use App\Models\PosSalesBatch;
use App\Scopes\CompanyScope;
use App\Services\Sales\PosAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The POS sync agent's whole wire surface: pair, ping, upload.
 *
 * Everything answers JSON to a native binary. Error bodies land in the
 * agent's local log on the terminal, so they say what to do next, not what
 * class threw.
 */
class PosAgentController extends Controller
{
    /** Recent batch outcomes returned on ping, for the agent's local log. */
    private const RECENT_BATCHES = 10;

    /** Upload cap in kilobytes. A Zeoniq daily report is ~10 KB. */
    private const MAX_UPLOAD_KB = 10240;

    public function __construct(private PosAgentService $agents)
    {
    }

    /** Redeem a pairing code for the agent's token — see PrintAgentController::pair(). */
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
     * Heartbeat + config + recent outcomes, all in one round trip. The
     * middleware already stamped last_seen_at; this hands back everything
     * the agent adopts or logs.
     */
    public function ping(Request $request): JsonResponse
    {
        $agent = $this->agent();

        if ($version = $request->query('version')) {
            if (is_string($version) && $version !== $agent->agent_version) {
                $agent->forceFill(['agent_version' => mb_substr($version, 0, 40)])->saveQuietly();
            }
        }

        $recent = PosSalesBatch::withoutGlobalScope(CompanyScope::class)
            ->where('pos_agent_id', $agent->id)
            ->latest('id')
            ->limit(self::RECENT_BATCHES)
            ->get()
            ->map(fn (PosSalesBatch $b) => [
                'id'     => $b->id,
                'sha256' => $b->sha256,
                'status' => $b->status,
                'result' => $b->result,
                'error'  => $b->error,
            ]);

        return response()->json([
            'status' => 'ok',
            'agent'  => [
                'name'            => $agent->name,
                'outlet'          => $agent->outlet?->name,
                'current_version' => PosAgent::CURRENT_VERSION,
            ],
            'config' => [
                'sync_interval_minutes' => $agent->syncIntervalMinutes(),
            ],
            'recent_batches' => $recent,
        ]);
    }

    /**
     * Accept one report file, stage it, queue processing.
     *
     * Idempotent per (agent, sha256 of the bytes): the agent's spool replays
     * after crashes and offline weekends, and a replay must answer with the
     * original batch rather than a second one.
     */
    public function storeBatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file'        => 'required|file|mimes:xlsx,xls,csv|max:' . self::MAX_UPLOAD_KB,
            'version'     => 'nullable|string|max:40',
            'extractor'   => 'nullable|string|max:40',
            'source_path' => 'nullable|string|max:255',
        ]);

        $agent = $this->agent();
        $file  = $data['file'];

        // Server-side hash — the agent sends bytes, not claims.
        $sha = hash_file('sha256', $file->getRealPath());

        $existing = PosSalesBatch::withoutGlobalScope(CompanyScope::class)
            ->where('pos_agent_id', $agent->id)
            ->where('sha256', $sha)
            ->first();

        if ($existing) {
            return response()->json([
                'status'       => 'duplicate',
                'batch_id'     => $existing->id,
                'batch_status' => $existing->status,
            ]);
        }

        $path = Storage::disk('local')->putFileAs(
            'pos-batches/' . $agent->company_id,
            $file,
            $sha . '.' . strtolower($file->getClientOriginalExtension()),
        );

        $batch = PosSalesBatch::withoutGlobalScope(CompanyScope::class)->create([
            'company_id'      => $agent->company_id,
            'pos_agent_id'    => $agent->id,
            'outlet_id'       => $agent->outlet_id,
            'source_filename' => mb_substr($file->getClientOriginalName(), 0, 255),
            'sha256'          => $sha,
            'file_path'       => $path,
            'status'          => PosSalesBatch::STATUS_RECEIVED,
        ]);

        if (isset($data['version']) && $data['version'] !== $agent->agent_version) {
            $agent->forceFill(['agent_version' => mb_substr($data['version'], 0, 40)])->saveQuietly();
        }

        ProcessPosSalesBatch::dispatch($batch->id);

        return response()->json([
            'status'   => 'received',
            'batch_id' => $batch->id,
        ], 202);
    }

    private function agent(): PosAgent
    {
        return app(PosAgentAuthenticate::AGENT_KEY);
    }
}
