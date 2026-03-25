<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOnboardingEmails extends Command
{
    protected $signature = 'onboarding:send-emails';
    protected $description = 'Send automated onboarding email sequence based on trial day';

    public function handle(): int
    {
        $sent = 0;

        // Get all trialing companies
        $subscriptions = Subscription::where('status', Subscription::STATUS_TRIALING)
            ->with('company')
            ->get();

        foreach ($subscriptions as $sub) {
            $company = $sub->company;
            if (!$company || !$company->email) {
                continue;
            }

            $trialDay = (int) $sub->created_at->diffInDays(now());

            $emailClass = match ($trialDay) {
                1  => \App\Mail\Onboarding\CompleteSetup::class,
                3  => \App\Mail\Onboarding\FeatureHighlights::class,
                7  => \App\Mail\Onboarding\HalfwayReminder::class,
                11 => \App\Mail\Onboarding\TrialEndingSoon::class,
                14 => \App\Mail\Onboarding\TrialExpired::class,
                default => null,
            };

            if ($emailClass && class_exists($emailClass)) {
                try {
                    Mail::to($company->email)->send(new $emailClass($company, $sub));
                    $sent++;
                    $this->line("  Day {$trialDay}: Sent to {$company->name} ({$company->email})");
                } catch (\Exception $e) {
                    Log::warning("Onboarding email failed for {$company->name}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Sent {$sent} onboarding emails.");
        return self::SUCCESS;
    }
}
