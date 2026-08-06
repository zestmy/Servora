<?php

namespace App\Mail\Hr;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "X has applied for leave" — to whoever decides it.
 *
 * Carries the dates and the remaining balance, because the decision is
 * usually made on the phone and the answer to "can we spare them" should not
 * require opening a laptop.
 */
class LeaveSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $employeeName,
        public string $typeName,
        public string $dates,
        public string $days,
        public ?string $reason,
        public string $remaining,
        public string $brandName,
        public string $url,
        public bool $isTimeOff = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->isTimeOff ? 'Time off request' : 'Leave request')
                . ' — ' . $this->employeeName . ' — ' . $this->dates,
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.hr.leave-submitted');
    }
}
