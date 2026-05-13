<?php

namespace App\Console\Commands;

use App\Models\ReportSubscription;
use App\Services\ReportGeneratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendScheduledReports extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reports:send-scheduled
                            {--force : Force send all due reports regardless of last_sent_at}
                            {--subscription= : Send only a specific subscription ID}
                            {--dry-run : List reports that would be sent without actually sending}';

    /**
     * The console command description.
     */
    protected $description = 'Send all scheduled analytics reports that are due';

    public function __construct(
        protected ReportGeneratorService $reportService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for scheduled reports...');

        $query = ReportSubscription::withoutGlobalScopes()
            ->with(['user', 'outlet', 'company']);

        if ($subscriptionId = $this->option('subscription')) {
            $query->where('id', $subscriptionId);
            $this->info("Filtering to subscription ID: {$subscriptionId}");
        } else {
            // Get subscriptions due today
            $query->dueToday();
        }

        $subscriptions = $query->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No reports are due at this time.');
            return Command::SUCCESS;
        }

        $this->info("Found {$subscriptions->count()} report(s) to send.");

        if ($this->option('dry-run')) {
            $this->info('Dry run mode - listing reports:');
            $this->table(
                ['ID', 'Company', 'Outlet', 'Type', 'User Email', 'Frequency', 'Last Sent'],
                $subscriptions->map(fn($s) => [
                    $s->id,
                    $s->company?->name ?? 'N/A',
                    $s->outlet?->name ?? 'All Outlets',
                    $s->report_type,
                    $s->user?->email ?? 'N/A',
                    $s->frequency,
                    $s->last_sent_at?->format('Y-m-d H:i') ?? 'Never',
                ])->toArray()
            );
            return Command::SUCCESS;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($subscriptions as $subscription) {
            $outletName = $subscription->outlet?->name ?? 'All Outlets';
            $this->info("Processing: {$subscription->report_type} for {$outletName}...");

            try {
                $log = $this->reportService->generateFromSubscription($subscription);

                if ($log->delivery_status === 'sent') {
                    $this->info("  -> Sent successfully to {$subscription->user->email}");
                    $successCount++;
                } else {
                    $this->error("  -> Failed: {$log->error_message}");
                    $failCount++;
                }
            } catch (\Exception $e) {
                $this->error("  -> Exception: {$e->getMessage()}");
                Log::error('Scheduled report failed', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
                $failCount++;
            }
        }

        $this->newLine();
        $this->info("Completed: {$successCount} sent, {$failCount} failed.");

        return $failCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
