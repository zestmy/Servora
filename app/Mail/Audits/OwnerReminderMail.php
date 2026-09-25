<?php

namespace App\Mail\Audits;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "You have N corrective actions overdue" — to the employee who owns them,
 * with the Staff Portal link where they can mark them done.
 */
class OwnerReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array{employee:\App\Models\Employee, actions:\Illuminate\Support\Collection} $digest
     */
    public function __construct(public array $digest, public string $brandName, public string $portalUrl)
    {
    }

    public function envelope(): Envelope
    {
        $n = $this->digest['actions']->count();

        return new Envelope(subject: $n . ' audit fix' . ($n === 1 ? ' is' : 'es are') . ' overdue — ' . $this->brandName);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.audits.owner-reminder',
            with: [
                'name'      => $this->digest['employee']->name,
                'actions'   => $this->digest['actions'],
                'brandName' => $this->brandName,
                'portalUrl' => $this->portalUrl,
            ],
        );
    }
}
