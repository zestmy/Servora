<?php

namespace App\Mail;

use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Services\Payroll\PayslipPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One employee's payslip, attached as a PDF.
 *
 * Rendered from the LOCKED line, the same as the download — a payslip that
 * arrives by email and one printed from the screen must be the same document.
 *
 * The body deliberately carries no figures. Email is not private, it gets
 * forwarded and it sits on phones; the amounts belong in the attachment, which
 * is at least a deliberate act to open.
 */
class PayslipMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PayrollRun $run,
        public PayrollRunLine $line,
        public string $brandName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payslip — ' . $this->run->periodLabel() . ' — ' . $this->brandName,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.payslip',
            with: [
                'name'      => $this->line->employee_name,
                'period'    => $this->run->periodLabel(),
                'brandName' => $this->brandName,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $builder = app(PayslipPdf::class);
        $pdf     = $builder->make($this->run, collect([$this->line]));

        return [
            Attachment::fromData(
                fn () => $pdf->output(),
                $builder->filename($this->run, $this->line->employee_name),
            )->withMime('application/pdf'),
        ];
    }
}
