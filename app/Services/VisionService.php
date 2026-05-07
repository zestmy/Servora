<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VisionService
{
    private string $apiKey;
    private string $model = 'anthropic/claude-sonnet-4';
    private string $endpoint = 'https://openrouter.ai/api/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = AppSetting::get('openrouter_api_key') ?? '';
    }

    /**
     * Extract text from an image file using OpenRouter vision API.
     */
    public function extractText(string $imagePath): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OpenRouter API key not configured. Go to Settings > API Keys.');
        }

        if (! file_exists($imagePath)) {
            throw new \RuntimeException('Image file not found.');
        }

        $mimeType = mime_content_type($imagePath);
        $base64 = base64_encode(file_get_contents($imagePath));
        $dataUri = "data:{$mimeType};base64,{$base64}";

        $response = Http::timeout(60)
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
                    ['role' => 'user', 'content' => [
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUri]],
                        ['type' => 'text', 'text' => 'Extract ALL text from this image exactly as it appears. Preserve the layout and formatting as much as possible. Return only the extracted text, nothing else.'],
                    ]],
                ],
            ]);

        if ($response->failed()) {
            Log::error('OpenRouter Vision API error', ['status' => $response->status(), 'body' => $response->body()]);
            $body = $response->json();
            $msg = $body['error']['message'] ?? ('HTTP ' . $response->status());
            throw new \RuntimeException('Vision API request failed: ' . $msg);
        }

        return $response->json('choices.0.message.content', '');
    }

    /**
     * Extract and parse Z-report data from an image file.
     *
     * Uses OpenRouter vision API with Claude to directly extract structured data.
     *
     * @return array{
     *     date: ?string,
     *     departments: array,
     *     sessions: array,
     *     summary: array{
     *         gross_amount: ?float,
     *         net_sales: ?float,
     *         discount_incl_tax: ?float,
     *         exclusive_tax: ?float,
     *         exclusive_charges: ?float,
     *         bill_rounding: ?float,
     *         total_sales: ?float,
     *         total_guests: ?int,
     *         total_transactions: ?int
     *     }
     * }
     */
    public function extractZReportData(string $imagePath): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OpenRouter API key not configured. Go to Settings > API Keys.');
        }

        if (! file_exists($imagePath)) {
            throw new \RuntimeException('Image file not found.');
        }

        $mimeType = mime_content_type($imagePath);
        $base64 = base64_encode(file_get_contents($imagePath));
        $dataUri = "data:{$mimeType};base64,{$base64}";

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
                    ['role' => 'system', 'content' => $this->zReportSystemPrompt()],
                    ['role' => 'user', 'content' => [
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUri]],
                        ['type' => 'text', 'text' => $this->zReportExtractionPrompt()],
                    ]],
                ],
                'response_format' => ['type' => 'json_object'],
            ]);

        set_time_limit((int) $previousTimeout ?: 60);

        if ($response->failed()) {
            Log::error('OpenRouter Z-Report extraction failed', ['status' => $response->status(), 'body' => $response->body()]);
            $body = $response->json();
            $msg = $body['error']['message'] ?? ('HTTP ' . $response->status());
            throw new \RuntimeException('Z-Report extraction failed: ' . $msg);
        }

        $content = $response->json('choices.0.message.content', '');
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try to extract JSON from markdown code blocks
            if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $content, $m)) {
                $data = json_decode(trim($m[1]), true);
            }
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Failed to parse Z-report data. Please try again with a clearer image.');
            }
        }

        // Normalize the response structure
        return [
            'date'        => $data['date'] ?? null,
            'departments' => $data['departments'] ?? [],
            'sessions'    => $this->normalizeSessions($data['sessions'] ?? []),
            'summary'     => [
                'gross_amount'       => $this->toFloat($data['summary']['gross_amount'] ?? $data['gross_sales'] ?? null),
                'net_sales'          => $this->toFloat($data['summary']['net_sales'] ?? $data['net_sales'] ?? null),
                'discount_incl_tax'  => $this->toFloat($data['summary']['discount'] ?? $data['discount'] ?? null),
                'exclusive_tax'      => $this->toFloat($data['summary']['tax'] ?? $data['tax'] ?? null),
                'exclusive_charges'  => $this->toFloat($data['summary']['service_charge'] ?? $data['service_charge'] ?? null),
                'bill_rounding'      => $this->toFloat($data['summary']['rounding'] ?? $data['rounding'] ?? null),
                'total_sales'        => $this->toFloat($data['summary']['total_sales'] ?? $data['total_sales'] ?? null),
                'total_guests'       => $this->toInt($data['summary']['total_guests'] ?? $data['total_guests'] ?? $data['total_pax'] ?? null),
                'total_transactions' => $this->toInt($data['summary']['total_transactions'] ?? $data['total_bills'] ?? null),
            ],
        ];
    }

    private function zReportSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a Z-report data extraction specialist for F&B/restaurant POS systems. Extract structured data from Z-report receipts with high accuracy. Return ONLY valid JSON with no commentary or markdown formatting.
PROMPT;
    }

    private function zReportExtractionPrompt(): string
    {
        return <<<'PROMPT'
Extract all data from this Z-report receipt and return as JSON with this exact structure:
{
  "date": "YYYY-MM-DD or null if not visible",
  "departments": [
    {"name": "department name", "amount": 0.00, "bill_count": 0}
  ],
  "sessions": [
    {"label": "session name as printed", "meal_period": "breakfast|lunch|tea_time|dinner|supper|all_day", "amount": 0.00, "transactions": 0}
  ],
  "summary": {
    "gross_amount": 0.00,
    "net_sales": 0.00,
    "discount": 0.00,
    "tax": 0.00,
    "service_charge": 0.00,
    "rounding": 0.00,
    "total_sales": 0.00,
    "total_guests": 0,
    "total_transactions": 0
  }
}

Rules:
- Use numeric values (not strings) for all amounts
- If a field cannot be determined, use null
- For departments, extract from "DEPARTMENT SALES" or similar sections
- For sessions, map labels to meal_period: Breakfast→breakfast, Lunch→lunch, Tea/Tea Time/High Tea→tea_time, Dinner→dinner, Supper→supper, All Day→all_day
- Look for: NET SALES, GROSS SALES, TOTAL DISCOUNT, SST/GST/VAT (tax), SERVICE CHARGE, ROUNDING, TOTAL TRANSACTIONS/BILLS, GUESTS/PAX/COVERS
- Dates may appear as DD/MM/YYYY or YYYY-MM-DD - always convert to YYYY-MM-DD format
PROMPT;
    }

    private function normalizeSessions(array $sessions): array
    {
        $normalized = [];
        foreach ($sessions as $session) {
            $normalized[] = [
                'label'        => $session['label'] ?? '',
                'meal_period'  => $session['meal_period'] ?? null,
                'amount'       => $this->toFloat($session['amount'] ?? 0),
                'transactions' => $this->toInt($session['transactions'] ?? $session['bill_count'] ?? 1),
            ];
        }
        return $normalized;
    }

    private function toFloat($value): ?float
    {
        if ($value === null || $value === '') return null;
        return (float) $value;
    }

    private function toInt($value): ?int
    {
        if ($value === null || $value === '') return null;
        return (int) $value;
    }

    /**
     * Parse Z-report OCR text into structured data.
     *
     * @deprecated Use extractZReportData() instead for better accuracy with vision AI.
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
                if (preg_match('/^(ITEM\s+ENTRY|TOTAL\s+DISCOUNT|TAKEAWAY|DINE|DELIVERY|={4,}|\[PD)/i', $line)) {
                    $inSession = false;
                    continue;
                }
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
            if (preg_match('/^(.+?)\s{2,}(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)$/', $line, $m)) {
                $itemName = trim($m[1]);
                $qty      = floatval($m[2]);
                $price    = floatval($m[3]);
                $total    = floatval($m[4]);

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

            // Pattern 3: "5x Item Name 12.50"
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

            // Pattern 4: Two-column "Item Name   Total"
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
