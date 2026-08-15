<?php

namespace App\Services\Labels;

use App\Models\LabelPrinter;
use App\Models\LabelSetting;
use App\Models\PrintAgent;
use App\Scopes\CompanyScope;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Is this printer reachable?
 *
 * Answerable for the PrintNode driver, which reports a state per printer,
 * and for the agent driver, whose heartbeat is two local columns — no HTTP
 * at all, cheaper than either alternative. A browser-driver printer prints
 * through the chef's own Chrome: the document goes to the OS print system
 * and nothing comes back, which is the same reason LabelPrint records
 * 'sent' rather than an outcome for that driver. Rather than guess, those
 * return LOCAL and the UI explains itself instead.
 *
 * Two rules this must never break:
 *
 *   Fail open. A missing API key, an expired one, a PrintNode outage or a
 *   timeout all resolve to UNKNOWN. An integration problem must never look
 *   like a broken printer, and must never stop someone printing — the driver
 *   itself will raise a real error if the job actually fails.
 *
 *   Stay cheap. The staff print screen is the most-loaded page in the app and
 *   this is an outbound HTTP call, so results are cached per company for a
 *   minute and every printer in a list shares the one lookup.
 */
class PrinterStatus
{
    public const ONLINE  = 'online';
    public const OFFLINE = 'offline';
    public const UNKNOWN = 'unknown';
    /** Browser driver — there is no remote printer to ask. */
    public const LOCAL   = 'local';

    private const TTL = 60;

    /** Per-request memo, so one page render never hits the cache store twice. */
    private array $memo = [];

    public function for(LabelPrinter $printer): string
    {
        if ($printer->driver === 'agent') {
            return $this->agentStatus($printer);
        }

        if ($printer->driver !== 'printnode') {
            return self::LOCAL;
        }

        $remoteId = (int) $printer->printnode_printer_id;

        if ($remoteId <= 0) {
            // Configured as PrintNode but never linked to a remote printer.
            return self::UNKNOWN;
        }

        return $this->states((int) $printer->company_id)[$remoteId] ?? self::UNKNOWN;
    }

    /**
     * Agent-driver status: is the paired agent polling, and did it report
     * this printer as anything other than working?
     *
     * Reads two local columns — no HTTP call, no cache TTL subtlety. The
     * heartbeat window is minutes-tight (PrintAgent::HEARTBEAT_STALE_MINUTES)
     * because the reader is a chef deciding whether to walk to the printer.
     */
    private function agentStatus(LabelPrinter $printer): string
    {
        if (! $printer->print_agent_id || ! $printer->agent_printer_name) {
            // Configured as agent but never linked — same reading as a
            // PrintNode printer with no remote id.
            return self::UNKNOWN;
        }

        $key = 'agent:' . $printer->print_agent_id;

        if (! array_key_exists($key, $this->memo)) {
            // Without the global scope: the staff print screen runs with no
            // authenticated user. Company-filtered by hand instead.
            $this->memo[$key] = PrintAgent::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $printer->company_id)
                ->find($printer->print_agent_id);
        }

        $agent = $this->memo[$key];

        if (! $agent || ! $agent->isPaired()) {
            return self::UNKNOWN;
        }

        if (! $agent->isOnline()) {
            return self::OFFLINE;
        }

        // The agent is alive; believe what it said about this queue. A
        // printer it no longer reports, or reports as erroring, is offline
        // in every sense a chef cares about. Fail open to ONLINE when the
        // inventory hasn't arrived yet — an integration gap must not read
        // as a broken printer.
        $reported = collect($agent->reportedPrinters())
            ->firstWhere('name', $printer->agent_printer_name);

        if ($agent->printers !== null && ! $reported) {
            return self::OFFLINE;
        }

        $state = strtolower((string) ($reported['status'] ?? ''));

        if (in_array($state, ['offline', 'error'], true)) {
            return self::OFFLINE;
        }

        return self::ONLINE;
    }

    /**
     * Remote printer id => state, for one company.
     *
     * @return array<int, string>
     */
    private function states(int $companyId): array
    {
        if (isset($this->memo[$companyId])) {
            return $this->memo[$companyId];
        }

        return $this->memo[$companyId] = Cache::remember(
            "label-printer-states:{$companyId}",
            self::TTL,
            function () use ($companyId): array {
                try {
                    $key = LabelSetting::forCompany($companyId)->printnode_api_key;

                    if (! $key) {
                        return [];
                    }

                    return collect((new PrintNodeClient($key))->printers())
                        ->mapWithKeys(fn (array $p) => [
                            (int) $p['id'] => $p['state'] === 'online' ? self::ONLINE : self::OFFLINE,
                        ])
                        ->all();
                } catch (Throwable) {
                    // Deliberately swallowed. See the fail-open rule above —
                    // the caller renders UNKNOWN and printing is unaffected.
                    return [];
                }
            }
        );
    }
}
