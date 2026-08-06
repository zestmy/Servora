<?php

namespace App\Mail\Hr;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Your leave was approved / rejected" — to the person who asked.
 *
 * The outcome is in the SUBJECT, because that is the whole message and a
 * decision people are waiting on should not need the email opened to read.
 */
class LeaveDecided extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $employeeName,
        public string $typeName,
        public string $dates,
        public string $days,
        public string $outcome,          // Approved | Rejected
        public ?string $decisionNote,
        public ?string $decidedBy,
        public string $brandName,
        public bool $isTimeOff = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->isTimeOff ? 'Time off' : 'Leave')
                . ' ' . strtolower($this->outcome) . ' — ' . $this->dates,
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.hr.leave-decided');
    }
}
