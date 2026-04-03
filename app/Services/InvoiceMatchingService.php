<?php

namespace App\Services;

use App\Models\GoodsReceivedNote;
use App\Models\ProcurementInvoice;
use App\Models\PurchaseOrder;
use App\Models\Supplier;

class InvoiceMatchingService
{
    /**
     * Match extracted invoice data against company records (suppliers, POs, GRNs, ingredients).
     *
     * @param array $extractedData Parsed AI extraction output
     * @param int   $companyId
     * @return array ['supplier' => [...], 'purchase_order' => [...], 'grn' => [...], 'lines' => [...], 'exceptions' => [...]]
     */
    public static function match(array $extractedData, int $companyId): array
    {
        $supplier = self::matchSupplier($extractedData['supplier_name'] ?? '', $companyId);
        $po = $supplier['id'] ? self::matchPurchaseOrder($supplier['id'], $extractedData['line_items'] ?? [], $companyId) : ['id' => null, 'po_number' => null, 'confidence' => 0, 'model' => null];
        $grn = $po['id'] ? self::matchGrn($po['id']) : ['id' => null, 'grn_number' => null];

        $poLines = $po['model'] ? $po['model']->lines()->with('ingredient', 'uom')->get() : collect();
        $grnLines = $grn['id'] ? GoodsReceivedNote::find($grn['id'])?->lines()->with('ingredient', 'uom')->get() ?? collect() : collect();

        $lines = self::matchLines($extractedData['line_items'] ?? [], $poLines, $grnLines, $companyId);

        $exceptions = self::detectExceptions($extractedData, $lines, $supplier, $po, $grn, $companyId);

        return [
            'supplier'       => ['id' => $supplier['id'], 'name' => $supplier['name'], 'confidence' => $supplier['confidence']],
            'purchase_order' => ['id' => $po['id'], 'po_number' => $po['po_number'], 'confidence' => $po['confidence']],
            'grn'            => $grn,
            'lines'          => $lines,
            'exceptions'     => $exceptions,
        ];
    }

    private static function matchSupplier(string $supplierName, int $companyId): array
    {
        if (! $supplierName) {
            return ['id' => null, 'name' => $supplierName, 'confidence' => 0];
        }

        // Exact match
        $exact = Supplier::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereRaw('UPPER(name) = ?', [strtoupper(trim($supplierName))])
            ->first();

        if ($exact) {
            return ['id' => $exact->id, 'name' => $exact->name, 'confidence' => 1.0];
        }

        // Fuzzy word-overlap match
        $words = array_filter(explode(' ', strtoupper(trim($supplierName))), fn ($w) => mb_strlen($w) > 2);
        if (empty($words)) {
            return ['id' => null, 'name' => $supplierName, 'confidence' => 0];
        }

        $suppliers = Supplier::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->get(['id', 'name']);

        $bestMatch = null;
        $bestScore = 0;

        foreach ($suppliers as $s) {
            $sWords = array_filter(explode(' ', strtoupper($s->name)), fn ($w) => mb_strlen($w) > 2);
            if (empty($sWords)) continue;

            $overlap = count(array_intersect($words, $sWords));
            $score = $overlap / max(count($words), count($sWords));

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $s;
            }
        }

        if ($bestMatch && $bestScore >= 0.3) {
            return ['id' => $bestMatch->id, 'name' => $bestMatch->name, 'confidence' => round(min(0.5 + $bestScore * 0.5, 0.95), 2)];
        }

        return ['id' => null, 'name' => $supplierName, 'confidence' => 0];
    }

    private static function matchPurchaseOrder(int $supplierId, array $lineItems, int $companyId): array
    {
        $pos = PurchaseOrder::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('supplier_id', $supplierId)
            ->whereIn('status', ['approved', 'sent', 'partial', 'received'])
            ->orderByDesc('order_date')
            ->limit(20)
            ->with('lines.ingredient')
            ->get();

        if ($pos->isEmpty()) {
            return ['id' => null, 'po_number' => null, 'confidence' => 0, 'model' => null];
        }

        $extractedNames = collect($lineItems)->pluck('description')->map(fn ($d) => self::normalizeItemName($d))->filter()->toArray();

        $bestPo = null;
        $bestScore = 0;

        foreach ($pos as $po) {
            $poNames = $po->lines->map(fn ($l) => self::normalizeItemName($l->ingredient?->name ?? ''))->filter()->toArray();
            if (empty($poNames)) continue;

            $matched = 0;
            foreach ($extractedNames as $eName) {
                foreach ($poNames as $pName) {
                    if (self::fuzzyNameMatch($eName, $pName) >= 0.5) {
                        $matched++;
                        break;
                    }
                }
            }

            $score = $matched / max(count($extractedNames), 1);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestPo = $po;
            }
        }

        if ($bestPo && $bestScore >= 0.3) {
            return ['id' => $bestPo->id, 'po_number' => $bestPo->po_number, 'confidence' => round($bestScore, 2), 'model' => $bestPo];
        }

        // Fallback: return most recent PO for this supplier
        $fallback = $pos->first();
        return ['id' => $fallback->id, 'po_number' => $fallback->po_number, 'confidence' => 0.2, 'model' => $fallback];
    }

    private static function matchGrn(int $poId): array
    {
        $grn = GoodsReceivedNote::withoutGlobalScopes()
            ->where('purchase_order_id', $poId)
            ->where('status', 'received')
            ->orderByDesc('received_date')
            ->first(['id', 'grn_number']);

        return $grn
            ? ['id' => $grn->id, 'grn_number' => $grn->grn_number]
            : ['id' => null, 'grn_number' => null];
    }

    private static function matchLines(array $extractedLines, $poLines, $grnLines, int $companyId): array
    {
        $result = [];

        foreach ($extractedLines as $i => $extracted) {
            $eName = self::normalizeItemName($extracted['description'] ?? '');
            $bestMatch = null;
            $bestScore = 0;

            foreach ($poLines as $poLine) {
                $pName = self::normalizeItemName($poLine->ingredient?->name ?? '');
                $score = self::fuzzyNameMatch($eName, $pName);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = $poLine;
                }
            }

            // Find corresponding GRN line
            $grnLine = null;
            if ($bestMatch && $grnLines->isNotEmpty()) {
                $grnLine = $grnLines->firstWhere('ingredient_id', $bestMatch->ingredient_id);
            }

            $result[] = [
                'index'                  => $i,
                'extracted_description'  => $extracted['description'] ?? '',
                'extracted_quantity'     => floatval($extracted['quantity'] ?? 0),
                'extracted_unit'        => $extracted['unit'] ?? '',
                'extracted_unit_price'  => floatval($extracted['unit_price'] ?? 0),
                'extracted_total_price' => floatval($extracted['total_price'] ?? 0),
                'extracted_tax_amount'  => $extracted['tax_amount'] ?? null,
                'matched_ingredient_id'   => $bestMatch?->ingredient_id,
                'matched_ingredient_name' => $bestMatch?->ingredient?->name,
                'matched_uom_id'          => $bestMatch?->uom_id,
                'matched_uom_abbr'        => $bestMatch?->uom?->abbreviation,
                'po_quantity'             => $bestMatch ? floatval($bestMatch->quantity) : null,
                'po_unit_price'           => $bestMatch ? floatval($bestMatch->unit_cost ?? $bestMatch->unit_price ?? 0) : null,
                'grn_received_qty'        => $grnLine ? floatval($grnLine->received_quantity) : null,
                'match_confidence'        => round($bestScore, 2),
            ];
        }

        return $result;
    }

    private static function detectExceptions(array $extracted, array $lines, array $supplier, array $po, array $grn, int $companyId): array
    {
        $exceptions = [];

        // Header-level exceptions
        if (! $supplier['id']) {
            $exceptions[] = [
                'type' => 'supplier_unmatched',
                'severity' => 'error',
                'line_index' => null,
                'message' => "Supplier \"{$supplier['name']}\" not found in system.",
            ];
        }

        if (! $po['id']) {
            $exceptions[] = [
                'type' => 'po_unmatched',
                'severity' => 'warning',
                'line_index' => null,
                'message' => 'No matching Purchase Order found.',
            ];
        }

        // Duplicate invoice check
        $invoiceNumber = $extracted['invoice_number'] ?? null;
        if ($invoiceNumber && $supplier['id']) {
            $duplicate = ProcurementInvoice::withoutGlobalScopes()
                ->where('supplier_id', $supplier['id'])
                ->where('supplier_invoice_number', $invoiceNumber)
                ->exists();

            if ($duplicate) {
                $exceptions[] = [
                    'type' => 'duplicate_invoice',
                    'severity' => 'error',
                    'line_index' => null,
                    'message' => "Invoice #{$invoiceNumber} already exists for this supplier.",
                ];
            }
        }

        // Total mismatch
        $calculatedTotal = collect($lines)->sum('extracted_total_price');
        $invoiceTotal = floatval($extracted['total_amount'] ?? 0);
        $invoiceSubtotal = floatval($extracted['subtotal'] ?? 0);
        if ($invoiceSubtotal > 0 && abs($invoiceSubtotal - $calculatedTotal) > 0.50) {
            $exceptions[] = [
                'type' => 'total_mismatch',
                'severity' => 'warning',
                'line_index' => null,
                'message' => "Subtotal mismatch: sum of lines = " . number_format($calculatedTotal, 2) . " vs invoice subtotal = " . number_format($invoiceSubtotal, 2),
            ];
        }

        // Line-level exceptions
        foreach ($lines as $line) {
            $idx = $line['index'];

            // Unmatched line
            if ($line['match_confidence'] < 0.3 && $po['id']) {
                $exceptions[] = [
                    'type' => 'extra_item',
                    'severity' => 'warning',
                    'line_index' => $idx,
                    'message' => "\"{$line['extracted_description']}\" not found in PO.",
                ];
                continue;
            }

            if ($line['match_confidence'] > 0 && $line['match_confidence'] < 0.5) {
                $exceptions[] = [
                    'type' => 'low_confidence_match',
                    'severity' => 'warning',
                    'line_index' => $idx,
                    'message' => "Low confidence match: \"{$line['extracted_description']}\" → \"{$line['matched_ingredient_name']}\"",
                ];
            }

            // Price mismatch
            if ($line['po_unit_price'] !== null && $line['po_unit_price'] > 0) {
                $priceDiff = abs($line['extracted_unit_price'] - $line['po_unit_price']);
                $pricePct = $priceDiff / $line['po_unit_price'] * 100;

                if ($pricePct > 1) {
                    $exceptions[] = [
                        'type' => 'price_mismatch',
                        'severity' => $pricePct >= 5 ? 'error' : 'warning',
                        'line_index' => $idx,
                        'message' => sprintf(
                            'Price differs by %.1f%%: Invoice %.2f vs PO %.2f',
                            $pricePct, $line['extracted_unit_price'], $line['po_unit_price']
                        ),
                    ];
                }
            }

            // Quantity mismatch (vs GRN received)
            if ($line['grn_received_qty'] !== null) {
                $qtyDiff = abs($line['extracted_quantity'] - $line['grn_received_qty']);
                if ($qtyDiff > 0.01) {
                    $qtyPct = $line['grn_received_qty'] > 0 ? ($qtyDiff / $line['grn_received_qty'] * 100) : 100;
                    $exceptions[] = [
                        'type' => 'quantity_mismatch',
                        'severity' => $qtyPct >= 10 ? 'error' : 'warning',
                        'line_index' => $idx,
                        'message' => sprintf(
                            'Qty differs: Invoice %.2f vs GRN received %.2f',
                            $line['extracted_quantity'], $line['grn_received_qty']
                        ),
                    ];
                }
            }
        }

        // Check for PO lines missing from invoice
        if ($po['id'] && $po['model']) {
            $matchedIngredientIds = collect($lines)
                ->where('match_confidence', '>=', 0.3)
                ->pluck('matched_ingredient_id')
                ->filter()
                ->toArray();

            foreach ($po['model']->lines as $poLine) {
                if ($poLine->ingredient_id && ! in_array($poLine->ingredient_id, $matchedIngredientIds)) {
                    $exceptions[] = [
                        'type' => 'missing_item',
                        'severity' => 'info',
                        'line_index' => null,
                        'message' => "PO item \"{$poLine->ingredient?->name}\" not found in invoice.",
                    ];
                }
            }
        }

        return $exceptions;
    }

    /**
     * Normalize an item name for fuzzy matching.
     */
    private static function normalizeItemName(string $name): string
    {
        $name = strtoupper(trim($name));
        $name = preg_replace('/[^A-Z0-9\s]/', '', $name);
        return preg_replace('/\s+/', ' ', $name);
    }

    /**
     * Fuzzy match two normalized item names by word overlap.
     * Returns a score between 0 and 1.
     */
    private static function fuzzyNameMatch(string $a, string $b): float
    {
        if (! $a || ! $b) return 0;
        if ($a === $b) return 1.0;

        $wordsA = array_filter(explode(' ', $a), fn ($w) => mb_strlen($w) > 1);
        $wordsB = array_filter(explode(' ', $b), fn ($w) => mb_strlen($w) > 1);

        if (empty($wordsA) || empty($wordsB)) return 0;

        // Check for partial word matches too (e.g. "CHKN" in "CHICKEN")
        $matched = 0;
        foreach ($wordsA as $wa) {
            foreach ($wordsB as $wb) {
                if ($wa === $wb || str_contains($wb, $wa) || str_contains($wa, $wb)) {
                    $matched++;
                    break;
                }
            }
        }

        return $matched / max(count($wordsA), count($wordsB));
    }
}
