<?php

namespace App\Services\Labels;

use App\Models\LabelPrinter;

/**
 * Resolves a printer's transport.
 *
 * An UNRECOGNISED driver value falls back to the browser, because that is a
 * data problem and a chef mid-shift should get a label rather than a stack
 * trace.
 *
 * A printer explicitly set to 'printnode' does NOT fall back, even when the
 * key or remote printer is missing. That printer is deliberately not
 * attached to this PC, so browser printing would send the label to whatever
 * local printer happens to be default — or silently nowhere. Printing to the
 * wrong machine is worse than a clear error, and PrintNodeDriver's errors are
 * written to be shown to the person standing there.
 */
class DriverFactory
{
    public function __construct(
        private BrowserDriver $browser,
        private PrintNodeDriver $printNode,
    ) {
    }

    public function for(LabelPrinter $printer): LabelDriver
    {
        return $this->make($printer->driver);
    }

    public function make(?string $driver): LabelDriver
    {
        return match ($driver) {
            'printnode' => $this->printNode,
            default     => $this->browser,
        };
    }
}
