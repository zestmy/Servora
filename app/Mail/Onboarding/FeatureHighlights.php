<?php

namespace App\Mail\Onboarding;

use App\Models\Company;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeatureHighlights extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Company $company, public Subscription $subscription) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Did you know? Top features in Servora');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.onboarding.feature-highlights',
            with: ['company' => $this->company, 'subscription' => $this->subscription],
        );
    }
}
