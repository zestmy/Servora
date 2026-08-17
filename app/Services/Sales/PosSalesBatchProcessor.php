<?php

namespace App\Services\Sales;

use App\Models\Outlet;
use App\Models\PosSalesBatch;
use App\Models\SalesCategory;
use App\Models\SalesClosure;
use App\Models\SalesRecord;
use App\Models\User;
use App\Models\ZeoniqDepartmentMapping;
use App\Scopes\CompanyScope;
use App\Services\ZeoniqDepartmentMatchingService;
use App\Services\ZeoniqImportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Takes an uploaded POS report batch from `received` to a terminal status.
 *
 * The automation of what a person does on the Zeoniq import screen — parse,
 * resolve outlets, resolve department mappings, write — with one deliberate
 * difference: where the screen ASKS when something is off, this PARKS the
 * batch (needs_mapping / needs_review) and writes nothing. All-or-nothing
 * per batch, so a re-apply after fixing mappings never has to reason about
 * a half-written report.
 *
 * Runs on the queue: no auth user exists, so every query is explicit about
 * its company and CompanyScope is dropped throughout.
 */
class PosSalesBatchProcessor
{
    public function __construct(
        private ZeoniqImportService $parser,
        private ZeoniqSalesWriter $writer,
    ) {
    }

    public function process(PosSalesBatch $batch): void
    {
        $batch->forceFill(['status' => PosSalesBatch::STATUS_PROCESSING])->save();

        try {
            $this->run($batch, replaceOnlySources: [SalesRecord::SOURCE_POS_AGENT], appliedBy: null);
        } catch (Throwable $e) {
            Log::error('POS batch processing failed', ['batch' => $batch->id, 'error' => $e->getMessage()]);

            $batch->forceFill([
                'status'       => PosSalesBatch::STATUS_FAILED,
                'error'        => mb_substr($e->getMessage(), 0, 2000),
                'processed_at' => now(),
            ])->save();
        }
    }

    /**
     * Re-run a parked batch from the POS Sync screen — after mappings were
     * added (needs_mapping) or with a human's decision to overwrite manual
     * records (needs_review + $overrideConflicts).
     */
    public function reapply(PosSalesBatch $batch, ?User $by = null, bool $overrideConflicts = false): void
    {
        $batch->forceFill(['status' => PosSalesBatch::STATUS_PROCESSING, 'error' => null])->save();

        try {
            $this->run(
                $batch,
                replaceOnlySources: $overrideConflicts ? null : [SalesRecord::SOURCE_POS_AGENT],
                appliedBy: $by?->id,
            );
        } catch (Throwable $e) {
            $batch->forceFill([
                'status'       => PosSalesBatch::STATUS_FAILED,
                'error'        => mb_substr($e->getMessage(), 0, 2000),
                'processed_at' => now(),
            ])->save();
        }
    }

    private function run(PosSalesBatch $batch, ?array $replaceOnlySources, ?int $appliedBy): void
    {
        $records = $this->parse($batch);
        if ($records === null) {
            return; // parse() already parked the batch
        }

        $summary = [
            'records'  => count($records),
            'warnings' => $this->parser->validateDepartmentTotals($records),
        ];

        // ── Outlets ────────────────────────────────────────────────────
        $outletCodes = $this->parser->extractOutlets($records);
        $summary['outlets'] = $outletCodes;

        [$outletMap, $unresolved] = $this->resolveOutlets($batch, $outletCodes);
        if ($unresolved !== []) {
            $summary['unresolved_outlets'] = $unresolved;
            $this->park($batch, PosSalesBatch::STATUS_NEEDS_REVIEW, $summary,
                'Report covers outlets Servora could not match: ' . implode(', ', $unresolved)
                . '. Set the outlet code on Settings › Branches to match Zeoniq, then re-apply.');

            return;
        }

        // ── Department mappings ────────────────────────────────────────
        $departments = $this->parser->extractDepartmentNames($records);
        $summary['departments'] = $departments;

        $mapping = ZeoniqDepartmentMapping::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $batch->company_id)
            ->whereIn('zeoniq_department_name', $departments)
            ->pluck('sales_category_id', 'zeoniq_department_name')
            ->all();

        $unmapped = array_values(array_diff($departments, array_keys($mapping)));
        if ($unmapped !== []) {
            $summary['unmapped_departments'] = $unmapped;
            $summary['suggestions'] = $this->suggestMappings($batch->company_id, $unmapped);
            $this->park($batch, PosSalesBatch::STATUS_NEEDS_MAPPING, $summary,
                'New Zeoniq departments need a Sales Category: ' . implode(', ', $unmapped) . '.');

            return;
        }

        // ── Dates & closures ───────────────────────────────────────────
        $dates = collect($records)->pluck('date')->filter()->unique()->sort()->values();
        $batch->forceFill([
            'date_from' => $dates->first(),
            'date_to'   => $dates->last(),
        ])->save();

        // Compared as Y-m-d strings in PHP rather than whereIn on the
        // column: the date may be stored with a time component (SQLite),
        // where a string whereIn silently matches nothing.
        $closedDates = SalesClosure::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $batch->company_id)
            ->whereIn('outlet_id', array_values($outletMap) ?: [$batch->outlet_id])
            ->whereBetween('closure_date', [$dates->first(), $dates->last() . ' 23:59:59'])
            ->pluck('closure_date')
            ->map(fn ($d) => $d instanceof \Carbon\CarbonInterface ? $d->toDateString() : substr((string) $d, 0, 10))
            ->intersect($dates)
            ->values()
            ->all();

        if ($closedDates !== []) {
            $summary['closed_dates'] = $closedDates;
            $this->park($batch, PosSalesBatch::STATUS_NEEDS_REVIEW, $summary,
                'The POS reports sales on a date marked closed: ' . implode(', ', $closedDates)
                . '. Remove the closure or apply anyway from the review screen.');

            return;
        }

        // ── Write ──────────────────────────────────────────────────────
        $counts = ['created' => 0, 'replaced' => 0, 'skipped' => 0];
        $conflicts = [];
        $errors = [];

        try {
            DB::transaction(function () use (
                $batch, $records, $outletMap, $mapping, $replaceOnlySources, &$counts, &$conflicts, &$errors
            ) {
                foreach ($records as $record) {
                    $outletId = $outletMap[$record['outlet_code'] ?? ''] ?? $batch->outlet_id;

                    $sessions = isset($record['sessions']) && is_array($record['sessions'])
                        ? $record['sessions']
                        : [$record];

                    foreach ($sessions as $session) {
                        $result = $this->writer->write(
                            companyId: $batch->company_id,
                            outletId: $outletId,
                            userId: null,
                            date: $record['date'],
                            mealPeriod: $session['meal_period'] ?? 'all_day',
                            record: $session,
                            departmentMapping: $mapping,
                            replaceExisting: true,
                            source: SalesRecord::SOURCE_POS_AGENT,
                            batchId: $batch->id,
                            replaceOnlySources: $replaceOnlySources,
                        );

                        if ($result === true) {
                            $counts['created']++;
                        } elseif ($result === 'replaced') {
                            $counts['replaced']++;
                        } elseif ($result === 'conflict') {
                            $conflicts[] = $record['date'] . ' ' . ($session['meal_period'] ?? 'all_day');
                            // Roll the whole batch back: a half-applied
                            // report is harder to reason about than none.
                            throw new ConflictsWithManualRecords();
                        } elseif ($result === 'duplicate') {
                            $counts['skipped']++;
                        } else {
                            $errors[] = (string) $result;
                        }
                    }
                }
            });
        } catch (ConflictsWithManualRecords) {
            $summary['conflicts'] = $conflicts;
            $this->park($batch, PosSalesBatch::STATUS_NEEDS_REVIEW, $summary,
                'Sales records entered by hand already exist for: ' . implode(', ', $conflicts)
                . '. Nothing was changed — use "Apply anyway" to replace them with the POS figures.');

            return;
        }

        if ($errors !== []) {
            $summary['write_errors'] = $errors;
        }

        $batch->forceFill([
            'status'         => PosSalesBatch::STATUS_APPLIED,
            'parsed_summary' => $summary,
            'result'         => $counts,
            'error'          => null,
            'processed_at'   => now(),
            'applied_at'     => now(),
            'applied_by'     => $appliedBy,
        ])->save();
    }

    /**
     * Parse the stored file into the ZeoniqImportService record vocabulary,
     * or park the batch and return null.
     */
    private function parse(PosSalesBatch $batch): ?array
    {
        if (! $batch->file_path || ! Storage::disk('local')->exists($batch->file_path)) {
            $this->park($batch, PosSalesBatch::STATUS_FAILED, null,
                'The uploaded file is no longer on the server (pruned or lost). Upload it again.');

            return null;
        }

        $path = Storage::disk('local')->path($batch->file_path);

        $type = $this->parser->detectReportType($path);

        if ($type === 'unknown') {
            $this->park($batch, PosSalesBatch::STATUS_FAILED, null,
                'Could not recognise this file as a Zeoniq report. Expected the Daily Summary Listing '
                . '(with Statistics, Session and Department sections) or a Session Sales report.');

            return null;
        }

        $batch->forceFill(['report_type' => $type])->save();

        $records = $type === 'session_sales'
            ? $this->parser->parseSessionSalesExcel($path)
            : $this->parser->parseDailySummaryExcel($path);

        if ($records === []) {
            $this->park($batch, PosSalesBatch::STATUS_FAILED, null,
                'The report parsed but contained no sales rows.');

            return null;
        }

        return $records;
    }

    /**
     * Map report outlet codes to outlet ids.
     *
     * A single-outlet report belongs to the agent's paired outlet
     * unconditionally — that pairing is a fact a manager configured, and it
     * beats whatever label Zeoniq printed. Multi-outlet reports resolve
     * per code: outlets.code exact first, then case-insensitive name.
     *
     * @return array{0: array<string, int>, 1: array<int, string>}  [map, unresolved codes]
     */
    private function resolveOutlets(PosSalesBatch $batch, array $outletCodes): array
    {
        if (count($outletCodes) <= 1) {
            $code = $outletCodes[0] ?? '';

            return [[$code => $batch->outlet_id], []];
        }

        $outlets = Outlet::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $batch->company_id)
            ->whereNull('deleted_at')
            ->get(['id', 'name', 'code']);

        $map = [];
        $unresolved = [];

        foreach ($outletCodes as $code) {
            $match = $outlets->first(fn ($o) => $o->code !== null && strcasecmp($o->code, $code) === 0)
                ?? $outlets->first(fn ($o) => strcasecmp($o->name, $code) === 0);

            if ($match) {
                $map[$code] = $match->id;
            } else {
                $unresolved[] = $code;
            }
        }

        return [$map, $unresolved];
    }

    /**
     * AI mapping suggestions for the review screen, cached on the batch so
     * the screen never re-runs the matcher. Best-effort: the matcher calls
     * out to an LLM, and its failure must not change the batch's fate —
     * needs_mapping without suggestions is still actionable.
     */
    private function suggestMappings(int $companyId, array $unmapped): array
    {
        try {
            $categories = SalesCategory::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->get();

            return app(ZeoniqDepartmentMatchingService::class)->suggestMatches($unmapped, $categories);
        } catch (Throwable $e) {
            Log::warning('POS batch mapping suggestions failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function park(PosSalesBatch $batch, string $status, ?array $summary, string $error): void
    {
        $batch->forceFill([
            'status'         => $status,
            'parsed_summary' => $summary ?? $batch->parsed_summary,
            'error'          => $error,
            'processed_at'   => now(),
        ])->save();
    }
}

/** Internal: rolls the write transaction back when a manual record blocks a replace. */
class ConflictsWithManualRecords extends \RuntimeException
{
}
