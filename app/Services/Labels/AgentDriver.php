<?php

namespace App\Services\Labels;

use App\Models\LabelPrinter;
use App\Models\PrintAgent;
use App\Models\PrintJob;
use App\Scopes\CompanyScope;
use App\Services\LabelRenderService;
use RuntimeException;

/**
 * Prints via a Servora Print Agent: the server queues a PDF, the agent on
 * the outlet PC collects and prints it. The self-hosted PrintNode
 * replacement — see docs/print-agent-plan.md.
 *
 * Deliberate parallels with PrintNodeDriver, because the shape is the
 * whole point:
 *
 *  - It sends a PDF, not HTML, and returns a job id with no document —
 *    the Livewire components must not print anything client-side.
 *  - Status is 'queued': the job is accepted, not yet on paper. The agent
 *    acks done/error itself, which PrintNode could only be polled for.
 *  - A printer set to 'agent' NEVER falls back to the browser. It is
 *    deliberately not attached to this PC; the errors here are written to
 *    be shown to the person standing at the printer.
 *
 * One divergence: rotation is baked into the PDF (PdfRotate) rather than
 * carried as a job option, because the agent's print command has no rotate
 * flag to receive one.
 *
 * The job row is inserted inside LabelPrintService's transaction, so it
 * becomes visible to the polling agent atomically with its batch.
 */
class AgentDriver implements LabelDriver
{
    public function __construct(private LabelRenderService $renderer)
    {
    }

    public function name(): string
    {
        return 'agent';
    }

    public function submit(array $labels, LabelPrinter $printer): array
    {
        if (! $printer->print_agent_id || ! $printer->agent_printer_name) {
            throw new RuntimeException(
                'This printer has no agent printer selected. Pick one in Label Printers.'
            );
        }

        // Without the global scope: the staff app prints with no
        // authenticated user, the same reason LabelPrintService takes its
        // company from the printer.
        $agent = PrintAgent::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $printer->company_id)
            ->find($printer->print_agent_id);

        if (! $agent || $agent->isRevoked() || ! $agent->isPaired()) {
            throw new RuntimeException(
                'The print agent for this printer is not paired. Check Print Agents.'
            );
        }

        /*
         * An OFFLINE agent still queues. The PC may be seconds from its
         * next poll, the badge already warns, and the job expires in
         * minutes if nobody collects it — refusing here would turn a
         * momentary network blip into a chef who cannot print at all.
         */
        $pdf = $this->renderer->pdf(
            $labels,
            (float) $printer->width_mm,
            (float) $printer->height_mm,
            (float) $printer->offset_x_mm,
            (float) $printer->offset_y_mm,
        );

        if ($printer->rotate_90) {
            $pdf = PdfRotate::rotate90($pdf);
        }

        $job = PrintJob::create([
            'company_id'       => $printer->company_id,
            'print_agent_id'   => $agent->id,
            'label_printer_id' => $printer->id,
            // Copied, not referenced: an edit to the printer record must
            // not retarget a job already in flight.
            'printer_name'     => (string) $printer->agent_printer_name,
            'title'            => $this->title($labels, $printer),
            'payload'          => $pdf,
            'options'          => [
                // Null means "the driver default is already the label
                // stock" — same semantics as printnode_paper.
                'paper' => $printer->agent_paper ?: null,
            ],
            'status'           => 'pending',
            'expires_at'       => now()->addMinutes(PrintJob::TTL_MINUTES),
        ]);

        return [
            'status'   => 'queued',
            'document' => null,
            'job_id'   => (string) $job->id,
        ];
    }

    /**
     * Shown in the agent's local log, so make it identifiable there —
     * whoever is debugging a stuck queue is looking at a console on the
     * outlet PC, not at Servora.
     */
    private function title(array $labels, LabelPrinter $printer): string
    {
        $count = array_sum(array_map(fn ($l) => max(1, (int) ($l['copies'] ?? 1)), $labels));

        return sprintf('Servora %s — %d label%s', $printer->name, $count, $count === 1 ? '' : 's');
    }
}
