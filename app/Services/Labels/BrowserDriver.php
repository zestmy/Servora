<?php

namespace App\Services\Labels;

use App\Models\LabelPrinter;
use App\Services\LabelRenderService;

/**
 * Prints through the chef's own browser.
 *
 * The outlet PC runs Chrome launched with --kiosk-printing, so window.print()
 * goes straight to the OS default printer with no dialog. This driver doesn't
 * talk to the printer at all — it hands back a rendered document and the
 * Livewire component drops it into a hidden iframe and calls print().
 *
 * Consequences, all of them accepted deliberately:
 *
 *  - Status is always 'sent'. Kiosk printing reports nothing back, so the
 *    log records intent, not outcome. If the roll is empty, Servora believes
 *    it printed. The chef is standing there and will notice.
 *  - The OS default printer wins. A second printer on the same PC needs the
 *    Windows default changed, or PrintNode.
 *  - Copies are pages. The render service expands them; a copy count would
 *    be ignored by kiosk printing.
 *
 * The whole batch is ONE document and ONE print call. Firing a job per label
 * under kiosk printing produces races, out-of-order output, and the dialog
 * reappearing.
 */
class BrowserDriver implements LabelDriver
{
    public function __construct(private LabelRenderService $renderer)
    {
    }

    public function name(): string
    {
        return 'browser';
    }

    public function submit(array $labels, LabelPrinter $printer): array
    {
        $document = $this->renderer->html(
            $labels,
            (float) $printer->width_mm,
            (float) $printer->height_mm,
            (float) $printer->offset_x_mm,
            (float) $printer->offset_y_mm,
            (bool) $printer->rotate_90,
        );

        return [
            'status'   => 'sent',
            'document' => $document,
            'job_id'   => null,
        ];
    }
}
