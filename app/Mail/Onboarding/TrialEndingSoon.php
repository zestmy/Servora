<?php

namespace App\Mail\Onboarding;

use App\Models\Company;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrialEndingSoon extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Company $company, public Subscription $subscription) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Servora trial ends in 3 days');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.onboarding.trial-ending-soon',
            with: ['company' => $this->company, 'subscription' => $this->subscription],
        );
    }
}
