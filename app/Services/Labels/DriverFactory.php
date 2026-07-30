<?php

namespace App\Services\Labels;

use App\Models\LabelPrinter;

/**
 * Resolves a printer's transport.
 *
 * Only 'browser' has an implementation. A printer row set to 'printnode'
 * falls back to the browser driver rather than throwing: a chef mid-shift
 * should get a label out of a misconfigured printer record, not a stack
 * trace. When a PrintNode driver ships, it registers here.
 */
class DriverFactory
{
    public function __construct(private BrowserDriver $browser)
    {
    }

    public function for(LabelPrinter $printer): LabelDriver
    {
        return $this->make($printer->driver);
    }

    public function make(?string $driver): LabelDriver
    {
        return match ($driver) {
            default => $this->browser,
        };
    }
}
