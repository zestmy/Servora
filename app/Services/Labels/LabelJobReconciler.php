<?php

namespace App\Services\Labels;

use App\Models\LabelPrint;
use App\Models\LabelPrintBatch;
use App\Models\LabelSetting;
use App\Scopes\CompanyScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Asks PrintNode what actually happened to queued jobs.
 *
 * Without this, a PrintNode label sits at 'queued' forever and the print
 * log claims a label exists that may never have come out of a printer. For
 * a compliance record that is worse than useless — it is confidently wrong.
 *
 * Runs unattended, so it must never throw: one company with a revoked API
 * key cannot be allowed to stop every other company being reconciled.
 */
class LabelJobReconciler
{
    /**
     * PrintNode's vocabulary mapped onto ours.
     *
     * Anything not listed here is a state still in flight ('new', 'sent',
     * 'queued') and is left alone to be asked about again next run.
     */
    private const STATE_MAP = [
        'done'           => 'done',
        'error'          => 'error',
        'expired'        => 'expired',
        'deleted'        => 'expired',
        'limit_exceeded' => 'error',
    ];

    /**
     * @return array{checked: int, updated: int, companies: int, failures: int}
     */
    public function reconcile(int $withinDays = 7): array
    {
        $stats = ['checked' => 0, 'updated' => 0, 'companies' => 0, 'failures' => 0];

        $since = Carbon::now()->subDays($withinDays);

        // Runs from the scheduler with no authenticated user, so CompanyScope
        // would silently match nothing. Scope by hand instead.
        $batches = LabelPrintBatch::withoutGlobalScope(CompanyScope::class)
            ->where('driver', 'printnode')
            ->whereNotNull('driver_job_id')
            ->where('printed_at', '>=', $since)
            ->whereHas('prints', fn ($q) => $q->whereIn('status', LabelPrint::PENDING_STATUSES))
            ->get();

        foreach ($batches->groupBy('company_id') as $companyId => $companyBatches) {
            $stats['companies']++;
            $stats['checked'] += $companyBatches->count();

            try {
                $stats['updated'] += $this->reconcileCompany((int) $companyId, $companyBatches);
            } catch (\Throwable $e) {
                // Keep going: another company's key being wrong is not this
                // company's problem, and a scheduled job that dies halfway
                // leaves the rest permanently unreconciled.
                $stats['failures']++;
                Log::warning('Label job reconciliation failed', [
                    'company_id' => $companyId,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    private function reconcileCompany(int $companyId, $batches): int
    {
        $key = LabelSetting::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->value('printnode_api_key');

        if (! $key) {
            throw new \RuntimeException('No PrintNode API key for this company.');
        }

        $client  = new PrintNodeClient($key);
        $updated = 0;

        // Chunked so a busy kitchen doesn't produce a URL thousands of ids long.
        foreach ($batches->chunk(50) as $chunk) {
            $states = $client->jobStates($chunk->pluck('driver_job_id')->all());

            foreach ($chunk as $batch) {
                $state  = $states[(string) $batch->driver_job_id] ?? null;
                $mapped = $state ? (self::STATE_MAP[$state] ?? null) : null;

                if (! $mapped) {
                    continue;
                }

                $updated += LabelPrint::withoutGlobalScope(CompanyScope::class)
                    ->where('batch_id', $batch->id)
                    ->whereIn('status', LabelPrint::PENDING_STATUSES)
                    ->update(['status' => $mapped]);
            }
        }

        return $updated;
    }
}
