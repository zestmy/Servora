<?php

namespace App\Mail\Hr;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The sign-in code itself.
 *
 * Sent synchronously, not queued: somebody is holding a phone waiting for it,
 * and a code that arrives after the queue worker gets to it is a code that has
 * already expired.
 */
class StaffLoginCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public string $code,
        public int $minutes,
    ) {}

    public function envelope(): Envelope
    {
        // The code is in the subject as well as the body: on a phone it is
        // often readable from the notification without opening anything.
        return new Envelope(subject: $this->code . ' is your sign-in code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.hr.staff-login-code');
    }
}
