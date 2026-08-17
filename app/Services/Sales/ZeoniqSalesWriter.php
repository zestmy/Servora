<?php

namespace App\Services\Sales;

use App\Models\SalesCategory;
use App\Models\SalesRecord;
use App\Scopes\CompanyScope;

/**
 * Writes one parsed Zeoniq record (the ZeoniqImportService vocabulary:
 * total_sales/net_sales/departments{name => revenue}/...) as a SalesRecord
 * plus one line per mapped Sales Category.
 *
 * Extracted from the ZeoniqExcelImport Livewire component so the POS sync
 * agent's queued ingest can share the exact write path the manual upload
 * has always used. Two rules follow from that split:
 *
 *  - No ambient authority. Every query names its company explicitly and
 *    drops CompanyScope, because the scope reads auth() and a queue worker
 *    has nobody logged in — it would silently scope to NOTHING there.
 *  - $replaceOnlySources: the automated path passes ['pos_agent'] so a
 *    re-exported report can only ever replace the agent's own earlier
 *    writes; hitting a record a human typed returns 'conflict' instead of
 *    force-deleting it. The interactive screen passes null — a person
 *    clicking "Replace existing" is the review step.
 */
class ZeoniqSalesWriter
{
    /**
     * @param  array       $record             Parsed Zeoniq record/session data
     * @param  array       $departmentMapping  [zeoniq_department_name => sales_category_id|null]
     * @param  ?array      $replaceOnlySources Sources replace mode may delete, null = any
     * @return true|string true | 'replaced' | 'duplicate' | 'conflict' | error message
     */
    public function write(
        int $companyId,
        int $outletId,
        ?int $userId,
        string $date,
        string $mealPeriod,
        array $record,
        array $departmentMapping,
        bool $replaceExisting,
        string $source = SalesRecord::SOURCE_MANUAL,
        ?int $batchId = null,
        ?array $replaceOnlySources = null,
    ): string|bool {
        $existing = SalesRecord::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('outlet_id', $outletId)
            ->whereDate('sale_date', $date)
            ->get(['id', 'meal_period', 'source']);

        $didReplace = false;

        if ($existing->isNotEmpty()) {
            $existingPeriods = $existing->pluck('meal_period');

            // A conflict exists if: an all_day record already covers the date,
            // we're writing all_day over existing records, or the same meal
            // period is already present.
            $conflicts = $existingPeriods->contains('all_day')
                || $mealPeriod === 'all_day'
                || $existingPeriods->contains($mealPeriod);

            if ($conflicts) {
                if (! $replaceExisting) {
                    return 'duplicate';
                }

                // Scope replacement to the meal period being written (plus any
                // stale all_day record) so per-session writes in the same run
                // don't wipe each other.
                $periodsToReplace = $mealPeriod === 'all_day'
                    ? $existingPeriods->all()
                    : array_values(array_unique(['all_day', $mealPeriod]));

                $toReplace = $existing->whereIn('meal_period', $periodsToReplace);

                if ($replaceOnlySources !== null) {
                    $protected = $toReplace->first(
                        fn ($r) => ! in_array($r->source, $replaceOnlySources, true)
                    );
                    if ($protected) {
                        return 'conflict';
                    }
                }

                // Force-delete so the department lines cascade
                // (sales_record_lines has cascadeOnDelete and no soft-delete,
                // so a soft delete would orphan the old lines).
                SalesRecord::withoutGlobalScope(CompanyScope::class)
                    ->where('company_id', $companyId)
                    ->whereKey($toReplace->pluck('id'))
                    ->get()
                    ->each
                    ->forceDelete();

                $didReplace = true;
            }
        }

        // Determine revenue values
        // For session_sales: total_sales is the inclusive amount
        // For daily_summary: total_sales is the final amount
        $totalSales     = (float) ($record['total_sales']     ?? 0);
        $netSales       = (float) ($record['net_sales']       ?? $totalSales);
        $grossRevenue   = (float) ($record['gross_revenue']   ?? $totalSales);
        $discountAmount = (float) ($record['discount_amount'] ?? 0);
        $taxAmount      = (float) ($record['tax_amount']      ?? 0);
        $serviceCharges = (float) ($record['service_charges'] ?? 0);
        $roundingAmount = (float) ($record['rounding_amount'] ?? 0);
        $transactions   = (int)   ($record['transactions']    ?? 1);
        $pax            = (int)   ($record['pax']             ?? $transactions);

        // Use net_sales as total_revenue (consistent with existing pattern)
        $totalRevenue = $netSales > 0 ? $netSales : $totalSales;

        $salesRecord = SalesRecord::create([
            'company_id'      => $companyId,
            'outlet_id'       => $outletId,
            'sale_date'       => $date,
            'meal_period'     => $mealPeriod,
            'pax'             => max(1, $pax),
            'transactions'    => $transactions > 0 ? $transactions : null,
            'total_revenue'   => round($totalRevenue, 4),
            'gross_revenue'   => $grossRevenue > 0 ? round($grossRevenue, 4) : null,
            'discount_amount' => $discountAmount > 0 ? round($discountAmount, 4) : null,
            'tax_amount'      => $taxAmount > 0 ? round($taxAmount, 4) : null,
            'service_charges' => $serviceCharges > 0 ? round($serviceCharges, 4) : null,
            'rounding_amount' => $roundingAmount != 0 ? round($roundingAmount, 4) : null,
            'total_cost'      => 0,
            'source'          => $source,
            'created_by'      => $userId,
        ]);

        $departments = $record['departments'] ?? [];

        if (! empty($departments)) {
            $categoryNames = SalesCategory::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $companyId)
                ->whereIn('id', array_filter(array_values($departmentMapping)))
                ->pluck('name', 'id')
                ->toArray();

            // Merge departments that map to the same Sales Category
            $mergedByCategory = [];
            foreach ($departments as $deptName => $deptRevenue) {
                $categoryId = $departmentMapping[$deptName] ?? null;

                if ($categoryId && $deptRevenue > 0) {
                    $mergedByCategory[$categoryId] = ($mergedByCategory[$categoryId] ?? 0) + $deptRevenue;
                }
            }

            // Create one line per Sales Category with merged revenue
            foreach ($mergedByCategory as $categoryId => $categoryRevenue) {
                $salesRecord->lines()->create([
                    'sales_category_id'      => $categoryId,
                    'ingredient_category_id' => null,
                    'item_name'              => $categoryNames[$categoryId] ?? 'Unknown',
                    'quantity'               => 1,
                    'unit_price'             => round($categoryRevenue, 4),
                    'unit_cost'              => 0,
                    'total_revenue'          => round($categoryRevenue, 4),
                    'total_cost'             => 0,
                ]);
            }
        } else {
            // Fallback: single line carrying the whole period's revenue
            $salesRecord->lines()->create([
                'sales_category_id'      => null,
                'ingredient_category_id' => null,
                'item_name'              => ucfirst(str_replace('_', ' ', $mealPeriod)) . ' Sales',
                'quantity'               => 1,
                'unit_price'             => round($totalRevenue, 4),
                'unit_cost'              => 0,
                'total_revenue'          => round($totalRevenue, 4),
                'total_cost'             => 0,
            ]);
        }

        return $didReplace ? 'replaced' : true;
    }
}
