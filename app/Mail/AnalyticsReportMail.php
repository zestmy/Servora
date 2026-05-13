<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AnalyticsReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $reportType,
        public array $reportData,
        public ?array $insights,
        public array $charts,
        public string $outletName,
        public string $companyName,
        public string $periodLabel,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->reportType) {
            'daily_sales' => "Daily Sales Report - {$this->outletName} - {$this->periodLabel}",
            'weekly_performance' => "Weekly Performance Report - {$this->outletName} - {$this->periodLabel}",
            'monthly_summary' => "Monthly Summary Report - {$this->outletName} - {$this->periodLabel}",
            default => "Analytics Report - {$this->outletName}",
        };

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $view = match ($this->reportType) {
            'daily_sales' => 'emails.reports.daily',
            'weekly_performance' => 'emails.reports.weekly',
            'monthly_summary' => 'emails.reports.monthly',
            default => 'emails.reports.daily',
        };

        return new Content(
            view: $view,
        );
    }
}
