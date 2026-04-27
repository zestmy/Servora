<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VisionService
{
    private string $apiKey;
    private string $endpoint = 'https://vision.googleapis.com/v1/images:annotate';

    public function __construct()
    {
        // DB setting takes priority over .env config
        $this->apiKey = AppSetting::get('google_vision_api_key')
                     ?? config('services.google_vision.key', '');
    }

    /**
     * Extract text from an image file using Google Vision OCR.
     */
    public function extractText(string $imagePath): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Google Vision API key not configured.');
        }

        $imageData = base64_encode(file_get_contents($imagePath));

        $response = Http::timeout(30)->post($this->endpoint . '?key=' . $this->apiKey, [
            'requests' => [[
                'image'    => ['content' => $imageData],
                'features' => [['type' => 'DOCUMENT_TEXT_DETECTION', 'maxResults' => 1]],
            ]],
        ]);

        if ($response->failed()) {
            Log::error('Google Vision API error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Google Vision API request failed: ' . $response->status());
        }

        return $response->json('responses.0.fullTextAnnotation.text', '');
    }

    /**
     * Parse Z-report OCR text into structured data.
     *
     * Extracts:
     *  - date
     *  - departments: [{name, amount, bill_count}]  (from DEPARTMENT SALES Z-READ section)
     *  - sessions:    [{label, meal_period, amount, bill_count}]  (from SESSION REPORT section)
     *  - net_sales, total_bills
     */
    public function parseZReport(string $text): array
    {
        $result = [
            'date'        => null,
            'departments' => [],
            'sessions'    => [],
            'net_sales'   => null,
            'total_bills' => null,
        ];

        $lines = preg_split('/\r\n|\r|\n/', $text);

        // --- Date (DD/MM/YYYY or YYYY-MM-DD) ---
        foreach ($lines as $line) {
            if (preg_match('/DATE\s*:\s*(\d{2})\/(\d{2})\/(\d{4})/i', $line, $m)) {
                $result['date'] = "{$m[3]}-{$m[2]}-{$m[1]}";
                break;
            }
            if (preg_match('/DATE\s*:\s*(\d{4})-(\d{2})-(\d{2})/i', $line, $m)) {
                $result['date'] = "{$m[1]}-{$m[2]}-{$m[3]}";
                break;
            }
        }

        // --- Department breakdown: "3  [PD001] Food  107.60" ---
        foreach ($lines as $line) {
            if (preg_match('/^\s*(\d+)?\s*\[PD\d+\]\s+(.+?)\s+([\d,]+\.?\d{0,2})\s*$/i', $line, $m)) {
                $amount = floatval(str_replace(',', '', $m[3]));
                if ($amount > 0) {
                    $result['departments'][] = [
                        'name'       => trim($m[2]),
                        'amount'     => $amount,
                        'bill_count' => !empty($m[1]) ? (int) $m[1] : null,
                    ];
                }
            }
        }

        // --- Session report (inside SESSION REPORT block) ---
        $inSession = false;
        $sessionMap = [
            'breakfast' => 'breakfast',
            'lunch'     => 'lunch',
            'dinner'    => 'dinner',
            'supper'    => 'supper',
            'tea time'  => 'tea_time',
            'tea'       => 'tea_time',
            'high tea'  => 'tea_time',
            'brunch'    => 'lunch',
            'all day'   => 'all_day',
            'all-day'   => 'all_day',
        ];

        foreach ($lines as $line) {
            if (preg_match('/SESSION\s+REPORT/i', $line)) {
                $inSession = true;
                continue;
            }
            if ($inSession) {
                // Stop at next non-session section
                if (preg_match('/^(ITEM\s+ENTRY|TOTAL\s+DISCOUNT|TAKEAWAY|DINE|DELIVERY|={4,}|\[PD)/i', $line)) {
                    $inSession = false;
                    continue;
                }
                // "3  Breakfast (06:00-11:59)  121.45"
                if (preg_match('/^\s*(\d+)\s+(.+?)(?:\s*\(\d{2}:\d{2}[^)]*\))?\s+([\d,]+\.\d{2})\s*$/', $line, $m)) {
                    $label      = trim($m[2]);
                    $labelLower = strtolower($label);
                    $mealPeriod = null;
                    foreach ($sessionMap as $keyword => $key) {
                        if (str_contains($labelLower, $keyword)) {
                            $mealPeriod = $key;
                            break;
                        }
                    }
                    $result['sessions'][] = [
                        'label'       => $label,
                        'meal_period' => $mealPeriod,
                        'amount'      => floatval(str_replace(',', '', $m[3])),
                        'bill_count'  => (int) $m[1],
                    ];
                }
            }
        }

        // --- Net Sales & Total Bills ---
        foreach ($lines as $line) {
            if ($result['net_sales'] === null && preg_match('/NET\s+SALES\s+([\d,]+\.\d{2})/i', $line, $m)) {
                $result['net_sales'] = floatval(str_replace(',', '', $m[1]));
            }
            if ($result['total_bills'] === null && preg_match('/TOTAL\s+(?:TRANSACTIONS|BILL)\s+(\d+)/i', $line, $m)) {
                $result['total_bills'] = (int) $m[1];
            }
        }

        return $result;
    }

    /**
     * Parse raw OCR text into structured sales line items.
     *
     * Handles two common POS report formats:
     *  1. Tabular: "Item Name    10    12.00    120.00"
     *  2. Compact: "Item Name x10 @ 12.00"
     *
     * Returns array of ['item_name', 'quantity', 'unit_price', 'total_revenue']
     */
    public function parseSalesText(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $items = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Skip header/footer lines
            if (preg_match('/^(item|description|name|total|subtotal|tax|date|time|receipt|report|cashier|table|order|qty|quantity|price|amount)/i', $line)) {
                continue;
            }

            // Pattern 1: Tabular — "Item Name   Qty   UnitPrice   Total"
            // e.g. "Nasi Lemak Ayam   5   12.50   62.50"
            if (preg_match('/^(.+?)\s{2,}(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)$/', $line, $m)) {
                $itemName = trim($m[1]);
                $qty      = floatval($m[2]);
                $price    = floatval($m[3]);
                $total    = floatval($m[4]);

                // Validate: total ≈ qty * price (within 5% tolerance)
                if ($qty > 0 && $price > 0 && abs($qty * $price - $total) / max($total, 0.01) < 0.05) {
                    $items[] = [
                        'item_name'     => $itemName,
                        'quantity'      => $qty,
                        'unit_price'    => $price,
                        'total_revenue' => $total,
                    ];
                    continue;
                }
            }

            // Pattern 2: "Item Name x5 @ 12.50 = 62.50"
            if (preg_match('/^(.+?)\s+x(\d+(?:\.\d+)?)\s+@\s+(\d+(?:\.\d+)?)(?:\s+=\s+(\d+(?:\.\d+)?))?/i', $line, $m)) {
                $qty   = floatval($m[2]);
                $price = floatval($m[3]);
                $items[] = [
                    'item_name'     => trim($m[1]),
                    'quantity'      => $qty,
                    'unit_price'    => $price,
                    'total_revenue' => isset($m[4]) ? floatval($m[4]) : round($qty * $price, 2),
                ];
                continue;
            }

            // Pattern 3: "5x Item Name 12.50" (compact receipt style)
            if (preg_match('/^(\d+(?:\.\d+)?)\s*x\s+(.+?)\s+(\d+(?:\.\d+)?)$/', $line, $m)) {
                $qty   = floatval($m[1]);
                $total = floatval($m[3]);
                $items[] = [
                    'item_name'     => trim($m[2]),
                    'quantity'      => $qty,
                    'unit_price'    => $qty > 0 ? round($total / $qty, 4) : 0,
                    'total_revenue' => $total,
                ];
                continue;
            }

            // Pattern 4: Two-column "Item Name   Total" (assume qty=1)
            if (preg_match('/^(.+?)\s{2,}(\d+(?:\.\d+)?)$/', $line, $m)) {
                $name  = trim($m[1]);
                $total = floatval($m[2]);
                if (strlen($name) >= 3 && $total > 0) {
                    $items[] = [
                        'item_name'     => $name,
                        'quantity'      => 1,
                        'unit_price'    => $total,
                        'total_revenue' => $total,
                    ];
                }
            }
        }

        return $items;
    }
}
