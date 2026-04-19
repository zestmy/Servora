<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VisionService
{
    private string $apiKey;
    private string $model    = 'anthropic/claude-sonnet-4';
    private string $endpoint = 'https://openrouter.ai/api/v1/chat/completions';

    public function __construct()
    {
        // DB setting takes priority over .env config
        $this->apiKey = AppSetting::get('openrouter_api_key')
                     ?? config('services.openrouter.key', '');
    }

    /**
     * Extract text from a Z-report / receipt image or PDF using OpenRouter Claude Vision.
     *
     * Returns raw text preserving line breaks, which parseZReport() / parseSalesText() then parse.
     */
    public function extractText(string $imagePath): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OpenRouter API key not configured. Go to Settings > API Keys.');
        }

        if (! file_exists($imagePath)) {
            throw new \RuntimeException('Uploaded file not found.');
        }

        $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';
        $dataUri  = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($imagePath));

        $previousTimeout = ini_get('max_execution_time');
        set_time_limit(120);

        $response = Http::timeout(90)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => config('app.url', 'http://localhost'),
                'X-Title'       => config('app.name', 'Servora'),
            ])
            ->post($this->endpoint, [
                'model'      => $this->model,
                'max_tokens' => 4096,
                'messages'   => [
                    ['role' => 'system', 'content' => 'You are an OCR assistant. Transcribe the document exactly — preserve line breaks, spacing between columns, punctuation, and all numbers. Return ONLY the raw transcribed text with no commentary, no markdown, no code fences.'],
                    ['role' => 'user', 'content' => [
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUri]],
                        ['type' => 'text', 'text' => 'Transcribe every character of this Z-report / sales receipt, line by line, preserving the original column alignment. Keep numeric values, currency, dates, and punctuation exactly as shown. Do not summarise.'],
                    ]],
                ],
            ]);

        set_time_limit((int) $previousTimeout ?: 60);

        if ($response->failed()) {
            Log::error('OpenRouter Vision OCR error', ['status' => $response->status(), 'body' => $response->body()]);
            $msg = $response->json('error.message') ?? ('HTTP ' . $response->status());
            throw new \RuntimeException('Vision OCR request failed: ' . $msg);
        }

        $text = $response->json('choices.0.message.content', '');

        // Strip accidental markdown code fences
        if (preg_match('/^```(?:\w+)?\s*\n?(.*?)\n?```\s*$/s', $text, $m)) {
            $text = $m[1];
        }

        return trim($text);
    }

    /**
     * End-to-end Z-report extraction — single Claude Vision call that returns
     * structured JSON (totals, guest/transaction metrics, sessions, departments).
     *
     * Preferred over extractText()+parseZReport() for new code; handles POS
     * variations (Artisan Ilusi, BMS, StoreHub, etc.) without regex tuning.
     *
     * Returns:
     *  [
     *    'date'         => 'YYYY-MM-DD' | null,
     *    'summary'      => [gross_amount, discount_incl_tax, net_sales, exclusive_tax,
     *                       exclusive_charges, bill_rounding, total_sales, total_guests,
     *                       total_transactions, avg_guest_value, atv_net, atv_gross],
     *    'sessions'     => [{label, meal_period, transactions, amount}],
     *    'departments'  => [{name, amount, transactions}],
     *    'service_types'=> [{label, transactions, amount}],   // Takeaway / Dine In / Delivery
     *  ]
     */
    public function extractZReportData(string $imagePath): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OpenRouter API key not configured. Go to Settings > API Keys.');
        }

        if (! file_exists($imagePath)) {
            throw new \RuntimeException('Uploaded file not found.');
        }

        $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';
        $dataUri  = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($imagePath));

        $previousTimeout = ini_get('max_execution_time');
        set_time_limit(120);

        $response = Http::timeout(90)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => config('app.url', 'http://localhost'),
                'X-Title'       => config('app.name', 'Servora'),
            ])
            ->post($this->endpoint, [
                'model'      => $this->model,
                'max_tokens' => 4096,
                'messages'   => [
                    ['role' => 'system', 'content' => 'You extract structured data from F&B Z-reports / daily sales receipts. Return ONLY valid JSON — no commentary, no markdown, no code fences.'],
                    ['role' => 'user', 'content' => [
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUri]],
                        ['type' => 'text', 'text' => self::zReportPrompt()],
                    ]],
                ],
                'response_format' => ['type' => 'json_object'],
            ]);

        set_time_limit((int) $previousTimeout ?: 60);

        if ($response->failed()) {
            Log::error('OpenRouter Z-report extraction error', ['status' => $response->status(), 'body' => $response->body()]);
            $msg = $response->json('error.message') ?? ('HTTP ' . $response->status());
            throw new \RuntimeException('Z-report extraction failed: ' . $msg);
        }

        $content = $response->json('choices.0.message.content', '');
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE && preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $content, $m)) {
            $data = json_decode(trim($m[1]), true);
        }
        if (! is_array($data)) {
            throw new \RuntimeException('Z-report extraction returned invalid JSON.');
        }

        // Normalise output shape
        return [
            'date'          => $data['date'] ?? null,
            'summary'       => array_merge([
                'gross_amount'       => null,
                'discount_incl_tax'  => null,
                'net_sales'          => null,
                'exclusive_tax'      => null,
                'exclusive_charges'  => null,
                'bill_rounding'      => null,
                'total_sales'        => null,
                'total_guests'       => null,
                'total_transactions' => null,
                'avg_guest_value'    => null,
                'atv_net'            => null,
                'atv_gross'          => null,
            ], is_array($data['summary'] ?? null) ? $data['summary'] : []),
            'sessions'      => is_array($data['sessions'] ?? null) ? $data['sessions'] : [],
            'departments'   => is_array($data['departments'] ?? null) ? $data['departments'] : [],
            'service_types' => is_array($data['service_types'] ?? null) ? $data['service_types'] : [],
        ];
    }

    private static function zReportPrompt(): string
    {
        return <<<'PROMPT'
Extract the Z-report / daily sales receipt into this JSON shape:

{
  "date": "YYYY-MM-DD or null",
  "summary": {
    "gross_amount":       number | null,
    "discount_incl_tax":  number | null,  // signed — negative if shown negative
    "net_sales":          number | null,
    "exclusive_tax":      number | null,
    "exclusive_charges":  number | null,  // service charge
    "bill_rounding":      number | null,  // signed
    "total_sales":        number | null,  // gross tender / total collected
    "total_guests":       integer | null, // pax / covers
    "total_transactions": integer | null, // bills / chits / checks
    "avg_guest_value":    number | null,
    "atv_net":            number | null,
    "atv_gross":          number | null
  },
  "sessions": [
    {
      "label": "Breakfast",
      "meal_period": "breakfast" | "lunch" | "tea_time" | "dinner" | "supper" | "all_day",
      "transactions": integer | null,   // bills in this session (NOT guests)
      "amount": number
    }
  ],
  "departments": [
    { "name": "Food", "amount": number, "transactions": integer | null }
  ],
  "service_types": [
    { "label": "Takeaway", "transactions": integer | null, "amount": number }
  ]
}

Rules:
- ALL amounts are numbers (not strings). Preserve sign for discounts and rounding.
- "transactions" means bills/chits — NEVER confuse with guests/pax/covers.
- Pax/guest count is ONLY in summary.total_guests, never per session unless the receipt explicitly shows per-session guest count.
- Map session labels to meal_period using the time window or name ("Breakfast"→breakfast, "Lunch"→lunch, "Tea Time"/"Tea"/"High Tea"→tea_time, "Dinner"→dinner, "Supper"→supper, "All Day"→all_day).
- service_types captures order fulfilment type (Dine In / Takeaway / Delivery) when listed; skip if not present.
- departments is the F&B category breakdown (Food / Beverage / Dessert etc). Skip if the receipt only has payment/tender breakdown.
- If a field cannot be determined from the image, use null — do NOT invent values.
PROMPT;
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
