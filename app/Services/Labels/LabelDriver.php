<?php

namespace App\Services\Labels;

use App\Models\LabelPrinter;

/**
 * How a rendered document reaches a printer.
 *
 * Two methods, deliberately. The point of this interface is that transport is
 * NOT an architectural decision: templates, the designer, the shelf life
 * matrix, print sets and the audit log all sit above it and none of them know
 * or care which driver is in use. Swapping browser printing for PrintNode is
 * a per-printer config flag, not a refactor.
 */
interface LabelDriver
{
    /** Key stored in label_printers.driver. */
    public function name(): string;

    /**
     * Send a batch to a printer.
     *
     * @param  array<int, array{template: \App\Models\LabelTemplate, values: array<string, string>, copies?: int}>  $labels
     * @return array{status: string, document: ?string, job_id: ?string}
     *         status   — what to record on label_prints
     *         document — HTML for the client to print, when the client prints
     *         job_id   — remote job reference, when the server prints
     */
    public function submit(array $labels, LabelPrinter $printer): array;
}
