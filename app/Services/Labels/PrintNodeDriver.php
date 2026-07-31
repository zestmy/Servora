<?php

namespace App\Services\Labels;

use App\Models\LabelPrinter;
use App\Models\LabelSetting;
use App\Services\LabelRenderService;
use RuntimeException;

/**
 * Prints via PrintNode: the server sends a PDF to a printer somewhere else.
 *
 * How this differs from the browser driver, and why the differences matter:
 *
 *  - It sends a PDF, not HTML. PrintNode has no browser. This is what the
 *    pdf() half of LabelRenderService was always for, and why the label
 *    Blade had to stay inside dompdf's CSS subset.
 *  - It returns a job id and no document. The Livewire components must not
 *    try to print anything client-side when that happens.
 *  - Status is 'queued', not 'sent'. PrintNode has accepted the job; the
 *    printer has not necessarily produced a label yet. That is a genuine
 *    improvement on browser printing, which can only ever record intent.
 *  - Rotation goes through PrintNode's job options, not CSS, because dompdf
 *    cannot apply transforms.
 */
class PrintNodeDriver implements LabelDriver
{
    public function __construct(private LabelRenderService $renderer)
    {
    }

    public function name(): string
    {
        return 'printnode';
    }

    public function submit(array $labels, LabelPrinter $printer): array
    {
        if (! $printer->printnode_printer_id) {
            throw new RuntimeException(
                'This printer has no PrintNode printer selected. Pick one in Label Printers.'
            );
        }

        $pdf = $this->renderer->pdf(
            $labels,
            (float) $printer->width_mm,
            (float) $printer->height_mm,
            (float) $printer->offset_x_mm,
            (float) $printer->offset_y_mm,
        );

        $jobId = $this->client($printer)->printPdf(
            (int) $printer->printnode_printer_id,
            $pdf,
            $this->title($labels, $printer),
            (bool) $printer->rotate_90,
            $printer->printnode_paper,
        );

        return [
            'status'   => 'queued',
            'document' => null,
            'job_id'   => $jobId,
        ];
    }

    /**
     * Shown in PrintNode's own job list, so make it identifiable there —
     * whoever is debugging a stuck queue is not looking at Servora.
     */
    private function title(array $labels, LabelPrinter $printer): string
    {
        $count = array_sum(array_map(fn ($l) => max(1, (int) ($l['copies'] ?? 1)), $labels));

        return sprintf('Servora %s — %d label%s', $printer->name, $count, $count === 1 ? '' : 's');
    }

    private function client(LabelPrinter $printer): PrintNodeClient
    {
        $key = LabelSetting::forCompany($printer->company_id)->printnode_api_key;

        if (! $key) {
            throw new RuntimeException('No PrintNode API key set. Add it in Label Settings.');
        }

        return new PrintNodeClient($key);
    }
}
