<?php

namespace App\Mail\Marketing;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The costing a visitor built, as a PDF they can keep.
 *
 * Sent synchronously rather than queued: it is one page of dompdf, well inside
 * the limits that force the SOP exports onto the queue, and somebody is
 * watching the screen for the confirmation.
 */
class RecipeCostPdfMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipeName,
        public array $lines,
        public array $totals,
        public float $portions,
        public float $targetFoodCostPct,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your recipe costing: ' . $this->recipeName);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.marketing.recipe-cost');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $pdf = Pdf::loadView('pdf.marketing.recipe-cost', [
            'recipeName'        => $this->recipeName,
            'lines'             => $this->lines,
            'totals'            => $this->totals,
            'portions'          => $this->portions,
            'targetFoodCostPct' => $this->targetFoodCostPct,
        ]);

        return [
            Attachment::fromData(fn () => $pdf->output(), \Illuminate\Support\Str::slug($this->recipeName) . '-costing.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
