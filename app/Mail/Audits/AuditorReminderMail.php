<?php

namespace App\Mail\Audits;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The auditor's morning digest: overdue scheduled audits, overdue corrective
 * actions, and findings nobody has acted on. The counts are in the subject
 * so the email says its piece from the inbox list.
 */
class AuditorReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array{user:\App\Models\User, schedules:\Illuminate\Support\Collection, actions:\Illuminate\Support\Collection, unactioned:\Illuminate\Support\Collection} $digest
     */
    public function __construct(public array $digest, public string $brandName)
    {
    }

    public function envelope(): Envelope
    {
        $parts = [];

        if ($n = $this->digest['schedules']->count()) {
            $parts[] = $n . ' overdue audit' . ($n === 1 ? '' : 's');
        }
        if ($n = $this->digest['actions']->count()) {
            $parts[] = $n . ' overdue corrective action' . ($n === 1 ? '' : 's');
        }
        if ($n = $this->digest['unactioned']->count()) {
            $parts[] = $n . ' finding' . ($n === 1 ? '' : 's') . ' with no action';
        }

        return new Envelope(subject: 'Audits: ' . implode(', ', $parts) . ' — ' . $this->brandName);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.audits.auditor-reminder',
            with: [
                'name'          => $this->digest['user']->name,
                'schedules'     => $this->digest['schedules'],
                'actions'       => $this->digest['actions'],
                'unactioned'    => $this->digest['unactioned'],
                'brandName'     => $this->brandName,
                'schedulesUrl'  => route('audits.schedules', ['filter' => 'overdue']),
                'actionsUrl'    => route('audits.actions', ['status' => 'overdue']),
                'unactionedUrl' => route('audits.actions', ['status' => 'unassigned']),
            ],
        );
    }
}
