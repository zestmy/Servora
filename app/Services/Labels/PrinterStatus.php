<?php

namespace App\Services\Labels;

use App\Models\LabelPrinter;
use App\Models\LabelSetting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Is this printer reachable?
 *
 * Only answerable for the PrintNode driver, which reports a state per printer.
 * A browser-driver printer prints through the chef's own Chrome: the document
 * goes to the OS print system and nothing comes back, which is the same reason
 * LabelPrint records 'sent' rather than an outcome for that driver. Rather
 * than guess, those return LOCAL and the UI explains itself instead.
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
