<?php

namespace App\Console\Commands;

use App\Models\Ingredient;
use App\Models\StockTake;
use App\Scopes\CompanyScope;
use App\Services\UomService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repair draft stock takes priced in the wrong unit.
 *
 * Reopening a draft used to re-price every line off ingredient->current_cost,
 * which is the cost per BASE uom, while the line is counted in the RECIPE uom —
 * so a dough bought by the 10-piece batch was stored at the batch price against
 * a per-piece count (RM2.845 written back as RM28.45), and the draft's
 * total_stock_cost was inflated by the same factor.
 *
 * A draft always shows the live cost in its own count uom — that is what the
 * form does on load — so the repair is simply to make the stored rows agree
 * with what the screen now computes. Completed stock takes are never touched:
 * they deliberately keep the price they were counted at.
 */
class RepriceDraftStockTakes extends Command
{
    protected $signature = 'stock-takes:reprice-drafts
        {--apply : Write the corrections (without this the command only reports)}
        {--company= : Limit to one company id}';

    protected $description = 'Re-price draft stock take lines in the UOM they are counted in (dry run unless --apply)';

    public function handle(UomService $uom): int
    {
        $apply = (bool) $this->option('apply');

        $drafts = StockTake::withoutGlobalScope(CompanyScope::class)
            ->where('status', '!=', 'completed')
            ->where('method', 'detailed')
            ->when($this->option('company'), fn ($q, $id) => $q->where('company_id', (int) $id))
            ->with(['lines.uom', 'company'])
            ->orderBy('company_id')->orderBy('id')
            ->get();

        if ($drafts->isEmpty()) {
            $this->info('No detailed draft stock takes found.');
            return self::SUCCESS;
        }

        // Ingredients are company-scoped and soft-deletable; a draft can outlive
        // its ingredient, so load them unscoped and treat a miss as "leave alone".
        $ingredients = Ingredient::withoutGlobalScope(CompanyScope::class)
            ->withTrashed()
            ->with(['baseUom', 'recipeUom', 'uomConversions'])
            ->whereIn('id', $drafts->pluck('lines')->flatten()->pluck('ingredient_id')->filter()->unique())
            ->get()->keyBy('id');

        $rows = [];
        $plan = [];   // stock take id => ['lines' => [id => [cost, variance]], 'stock' => x, 'variance' => y]
        $linesTouched = 0;
        $bugShaped = 0;
        $skipped = 0;

        foreach ($drafts as $draft) {
            $lineUpdates = [];
            $totalStock = 0.0;
            $totalVariance = 0.0;
            $draftDelta = 0.0;

            foreach ($draft->lines as $line) {
                $stored     = (float) $line->unit_cost;
                $ingredient = $ingredients->get($line->ingredient_id);
                $correct    = $stored;

                if ($ingredient) {
                    $countUom = $line->uom ?: ($ingredient->recipeUom ?: $ingredient->baseUom);
                    $correct  = $countUom
                        ? round($uom->convertCost($ingredient, $countUom), 4)
                        : round((float) $ingredient->current_cost, 4);
                } else {
                    $skipped++;
                }

                $varianceQty  = (float) $line->variance_quantity;
                $varianceCost = round($varianceQty * $correct, 4);

                $totalStock    += (float) $line->actual_quantity * $correct;
                $totalVariance += $varianceCost;

                if (abs($correct - $stored) > 0.00005) {
                    $lineUpdates[$line->id] = [$correct, $varianceCost];
                    $linesTouched++;
                    $draftDelta += ((float) $line->actual_quantity) * ($correct - $stored);

                    // Fingerprint of the bug itself: the stored price is the
                    // ingredient's base-uom cost, not just a stale number.
                    if ($ingredient && abs($stored - (float) $ingredient->current_cost) < 0.00005) {
                        $bugShaped++;
                    }
                }
            }

            $totalStock    = round($totalStock, 4);
            $totalVariance = round($totalVariance, 4);
            $storedStock   = (float) $draft->total_stock_cost;

            $totalsMoved = abs($totalStock - $storedStock) > 0.00005
                || abs($totalVariance - (float) $draft->total_variance_cost) > 0.00005;

            if (! $lineUpdates && ! $totalsMoved) {
                continue;
            }

            $plan[$draft->id] = [
                'lines'    => $lineUpdates,
                'stock'    => $totalStock,
                'variance' => $totalVariance,
            ];

            $rows[] = [
                $draft->id,
                $draft->company?->name ?? ('#' . $draft->company_id),
                $draft->reference_number ?? '—',
                $draft->stock_take_date?->format('Y-m-d') ?? '—',
                count($lineUpdates) . '/' . $draft->lines->count(),
                number_format($storedStock, 2),
                number_format($totalStock, 2),
                ($draftDelta >= 0 ? '+' : '') . number_format($draftDelta, 2),
            ];
        }

        if (! $plan) {
            $this->info("Checked {$drafts->count()} draft stock take(s) — every line already priced in its count UOM.");
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Company', 'Ref', 'Date', 'Lines', 'Stored total', 'Correct total', 'Delta'],
            $rows
        );

        $this->line(sprintf(
            '%d draft(s), %d line(s) to re-price — %d carry the base-UOM price exactly (the bug), %d line(s) skipped for a missing ingredient.',
            count($plan), $linesTouched, $bugShaped, $skipped
        ));

        if (! $apply) {
            $this->warn('Dry run — nothing written. Re-run with --apply to save these corrections.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($plan) {
            foreach ($plan as $stockTakeId => $change) {
                foreach ($change['lines'] as $lineId => [$cost, $varianceCost]) {
                    DB::table('stock_take_lines')
                        ->where('id', $lineId)
                        ->update(['unit_cost' => $cost, 'variance_cost' => $varianceCost]);
                }

                DB::table('stock_takes')->where('id', $stockTakeId)->update([
                    'total_stock_cost'    => $change['stock'],
                    'total_variance_cost' => $change['variance'],
                ]);
            }
        });

        $this->info(sprintf('Re-priced %d line(s) across %d draft stock take(s).', $linesTouched, count($plan)));

        return self::SUCCESS;
    }
}
