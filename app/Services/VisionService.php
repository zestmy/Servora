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
     * Suggest preparation (SOP) steps for a recipe using OpenRouter.
     * Analyses the recipe name, ingredient list, and optional dish images.
     *
     * @param  array<string>  $ingredientNames
     * @param  array<string>  $imagePaths  Absolute paths to dish images (optional, max 3 used).
     * @return array<int, array{title:string, instruction:string}>
     */
    public function suggestPreparationSteps(string $recipeName, array $ingredientNames, array $imagePaths = []): array
    {
        $recipeName      = trim($recipeName);
        $ingredientNames = array_values(array_filter(array_map('trim', $ingredientNames)));

        if ($recipeName === '' && empty($ingredientNames)) {
            throw new \RuntimeException('Add a recipe name and at least one ingredient before generating steps.');
        }

        [$imageParts, $hasImages] = $this->buildImageParts($imagePaths);

        $ingredientList = empty($ingredientNames) ? '(none provided)' : implode(', ', $ingredientNames);
        $userText = "Recipe name: {$recipeName}\n" .
            "Ingredients: {$ingredientList}\n\n" .
            'Write clear, professional kitchen preparation steps (SOP) for this dish. ' .
            'If dish photo(s) are provided, use them to infer plating and presentation. ' .
            'Each step should be concise and actionable.';

        $data = $this->chatJson([
            ['role' => 'system', 'content' => $this->stepSuggestionSystemPrompt()],
            ['role' => 'user', 'content' => array_merge($imageParts, [['type' => 'text', 'text' => $userText]])],
        ], $hasImages);

        $rawSteps = $data['steps'] ?? (array_is_list($data) ? $data : []);
        $steps    = [];
        foreach ($rawSteps as $s) {
            $one = $this->normalizeStep($s);
            if ($one['instruction'] !== '') {
                $steps[] = $one;
            }
        }

        if (empty($steps)) {
            throw new \RuntimeException('The AI did not return any usable steps. Please try again.');
        }

        return $steps;
    }

    /**
     * Regenerate a single preparation step in the context of the full step list.
     *
     * @param  array<string>  $ingredientNames
     * @param  array<int, array{title?:string, instruction?:string}>  $existingSteps
     * @param  int  $stepNumber  1-based index of the step to rewrite.
     * @param  array<string>  $imagePaths
     * @return array{title:string, instruction:string}
     */
    public function regeneratePreparationStep(string $recipeName, array $ingredientNames, array $existingSteps, int $stepNumber, array $imagePaths = []): array
    {
        $recipeName      = trim($recipeName);
        $ingredientNames = array_values(array_filter(array_map('trim', $ingredientNames)));

        [$imageParts, $hasImages] = $this->buildImageParts($imagePaths);

        $ingredientList = empty($ingredientNames) ? '(none provided)' : implode(', ', $ingredientNames);

        $stepsText = '';
        foreach ($existingSteps as $i => $st) {
            $n      = $i + 1;
            $title  = trim((string) ($st['title'] ?? ''));
            $instr  = trim((string) ($st['instruction'] ?? ''));
            $marker = ($n === $stepNumber) ? '   <-- REWRITE THIS STEP' : '';
            $stepsText .= "{$n}. " . ($title !== '' ? "[{$title}] " : '') . $instr . $marker . "\n";
        }

        $userText = "Recipe name: {$recipeName}\n" .
            "Ingredients: {$ingredientList}\n\n" .
            "Current preparation steps:\n{$stepsText}\n" .
            "Rewrite ONLY step {$stepNumber} so it is clearer and more professional, consistent with the surrounding steps and without duplicating them. " .
            'Return JSON for that one step only: {"title":"...","instruction":"..."}.';

        $data = $this->chatJson([
            ['role' => 'system', 'content' => $this->stepSuggestionSystemPrompt()],
            ['role' => 'user', 'content' => array_merge($imageParts, [['type' => 'text', 'text' => $userText]])],
        ], $hasImages);

        // Accept {title,instruction}, {step:{...}}, or {steps:[{...}]}.
        $one = $data;
        if (isset($data['step']) && is_array($data['step'])) {
            $one = $data['step'];
        } elseif (isset($data['steps'][0]) && is_array($data['steps'][0])) {
            $one = $data['steps'][0];
        }

        $step = $this->normalizeStep($one);
        if ($step['instruction'] === '') {
            throw new \RuntimeException('The AI did not return a usable step. Please try again.');
        }

        return $step;
    }

    /**
     * Build OpenRouter image content parts (base64 data URIs) from file paths.
     *
     * @param  array<string>  $imagePaths
     * @return array{0: array, 1: bool}  [content parts, whether any image was attached]
     */
    private function buildImageParts(array $imagePaths): array
    {
        $parts = [];
        $has   = false;
        foreach (array_slice($imagePaths, 0, 3) as $path) {
            if (! is_string($path) || ! file_exists($path)) {
                continue;
            }
            try {
                $mime   = mime_content_type($path) ?: 'image/jpeg';
                $base64 = base64_encode(file_get_contents($path));
                $parts[] = ['type' => 'image_url', 'image_url' => ['url' => "data:{$mime};base64,{$base64}"]];
                $has = true;
            } catch (\Throwable $e) {
                // skip unreadable image
            }
        }

        return [$parts, $has];
    }

    /** Normalise one AI step payload into [title, instruction]. */
    private function normalizeStep(mixed $s): array
    {
        if (is_string($s)) {
            return ['title' => '', 'instruction' => trim($s)];
        }
        if (! is_array($s)) {
            return ['title' => '', 'instruction' => ''];
        }

        return [
            'title'       => trim((string) ($s['title'] ?? '')),
            'instruction' => trim((string) ($s['instruction'] ?? $s['text'] ?? $s['description'] ?? '')),
        ];
    }

    /**
     * POST a chat-completion expecting a JSON object response, and return the decoded array.
     * Uses a vision-capable model when images are attached (the configured text model may not accept images).
     */
    private function chatJson(array $messages, bool $hasImages, int $maxTokens = 2048): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OpenRouter API key not configured. Go to Settings > API Keys.');
        }

        // Always use the fast, vision-capable default model for step generation.
        // The admin's configured openrouter_model may be a slow reasoning model
        // (e.g. deepseek-r1) whose long "thinking" phase times the request out.
        $model = $this->model;

        $previousTimeout = ini_get('max_execution_time');
        set_time_limit(180);

        $response = Http::connectTimeout(15)->timeout(120)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => config('app.url', 'http://localhost'),
                'X-Title'       => config('app.name', 'Servora'),
            ])
            ->post($this->endpoint, [
                'model'           => $model,
                'max_tokens'      => $maxTokens,
                'messages'        => $messages,
                'response_format' => ['type' => 'json_object'],
            ]);

        set_time_limit((int) $previousTimeout ?: 60);

        if ($response->failed()) {
            Log::error('OpenRouter chat request failed', ['status' => $response->status(), 'body' => $response->body()]);
            $body = $response->json();
            $msg  = $body['error']['message'] ?? ('HTTP ' . $response->status());
            throw new \RuntimeException('AI request failed: ' . $msg);
        }

        $raw  = $response->json('choices.0.message.content', '');
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE && preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $raw, $m)) {
            $data = json_decode(trim($m[1]), true);
        }
        if (! is_array($data)) {
            throw new \RuntimeException('Could not parse the AI response. Please try again.');
        }

        return $data;
    }

    private function stepSuggestionSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a professional executive chef writing standard operating procedure (SOP) preparation steps for restaurant kitchen training. Given a dish name, its ingredients, and optional photos, produce a concise, ordered list of preparation steps a line cook can follow. Each step has a short title and a clear instruction. Return ONLY valid JSON, no markdown, in this exact shape:
{"steps":[{"title":"Mise en place","instruction":"..."},{"title":"Cook","instruction":"..."}]}
Keep to between 4 and 8 steps. Do not invent ingredients that are not listed, except basic staples (salt, pepper, cooking oil, water).
PROMPT;
    }

    /**
     * Extract and parse Z-report data from an image file.
     *
     * Uses OpenRouter vision API with Claude to directly extract structured data.
     *
     * Z-report flow: Gross - Discount = Nett + Tax + Charges + Rounding = Total
     * Session amounts are Total Sales (inclusive of tax/charges).
     *
     * @return array{
     *     outlet_name: ?string,
     *     date: ?string,
     *     departments: array,
     *     sessions: array,
     *     summary: array{
     *         gross_amount: ?float,
     *         discount_incl_tax: ?float,
     *         net_sales: ?float,
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
        // Z-report flow: Gross - Discount = Nett + Tax + Charges + Rounding = Total
        return [
            'outlet_name' => $data['outlet_name'] ?? null,
            'date'        => $data['date'] ?? null,
            'departments' => $data['departments'] ?? [],
            'sessions'    => $this->normalizeSessions($data['sessions'] ?? []),
            'summary'     => [
                // gross_amount = Gross Sales (before discount - HIGHEST amount)
                'gross_amount'       => $this->toFloat($data['summary']['gross_sales'] ?? $data['gross_sales'] ?? null),
                // discount = Discount amount
                'discount_incl_tax'  => $this->toFloat($data['summary']['discount'] ?? $data['discount'] ?? null),
                // net_sales = Nett Sales (after discount, before tax/charges)
                'net_sales'          => $this->toFloat($data['summary']['net_sales'] ?? $data['net_sales'] ?? null),
                // tax, service charge, rounding
                'exclusive_tax'      => $this->toFloat($data['summary']['tax'] ?? $data['tax'] ?? null),
                'exclusive_charges'  => $this->toFloat($data['summary']['service_charge'] ?? $data['service_charge'] ?? null),
                'bill_rounding'      => $this->toFloat($data['summary']['rounding'] ?? $data['rounding'] ?? null),
                // total_sales = Total Sales (final inclusive - what session amounts show)
                'total_sales'        => $this->toFloat($data['summary']['total_sales'] ?? $data['total_sales'] ?? null),
                'total_guests'       => $this->toInt($data['summary']['total_guests'] ?? $data['total_guests'] ?? $data['total_pax'] ?? null),
                'total_transactions' => $this->toInt($data['summary']['total_transactions'] ?? $data['total_bills'] ?? null),
            ],
        ];
    }

    private function zReportSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a Z-report data extraction specialist for Zeoniq POS systems used in F&B/restaurants. Extract structured data from Zeoniq Z-report receipts with high accuracy. Return ONLY valid JSON with no commentary or markdown formatting.
PROMPT;
    }

    private function zReportExtractionPrompt(): string
    {
        return <<<'PROMPT'
Extract all data from this Z-report receipt and return as JSON with this exact structure:
{
  "outlet_name": "outlet/branch/store name if visible, or null",
  "date": "YYYY-MM-DD or null if not visible",
  "departments": [
    {"name": "department name", "amount": 0.00, "bill_count": 0}
  ],
  "sessions": [
    {"label": "session name as printed", "meal_period": "breakfast|lunch|tea_time|dinner|supper|all_day", "amount": 0.00, "transactions": 0}
  ],
  "summary": {
    "gross_sales": 0.00,
    "discount": 0.00,
    "net_sales": 0.00,
    "tax": 0.00,
    "service_charge": 0.00,
    "rounding": 0.00,
    "total_sales": 0.00,
    "total_guests": 0,
    "total_transactions": 0
  }
}

IMPORTANT - Z-report calculation flow:
  GROSS SALES (before discount - highest amount)
  - DISCOUNT
  = NETT SALES (after discount, before tax/charges)
  + TAX (SST/GST/VAT)
  + SERVICE CHARGE
  + BILL ROUNDING
  = TOTAL SALES (final inclusive amount - what customers pay)

Session report amounts (Breakfast, Lunch, Dinner, etc.) are TOTAL SALES amounts (inclusive of tax/charges).

Rules:
- Use numeric values (not strings) for all amounts
- If a field cannot be determined, use null
- gross_sales: "GROSS SALES" - the HIGHEST base amount before any discount
- discount: "DISCOUNT" or "TOTAL DISCOUNT" amount
- net_sales: "NETT SALES" or "NET SALES" - after discount, before tax/charges
- tax: "SST", "GST", "VAT", or "TAX" amount
- service_charge: "SERVICE CHARGE" or "SVC" amount
- rounding: "ROUNDING" or "BILL ROUNDING" amount (can be negative)
- total_sales: "TOTAL SALES" - the final inclusive amount (net + tax + charges + rounding)
- Session amounts match total_sales breakdown by meal period
- For outlet_name: Look for branch name, outlet name, store name at the top
- For sessions, map: Breakfast→breakfast, Lunch→lunch, Tea/Tea Time/High Tea→tea_time, Dinner→dinner, Supper→supper, All Day→all_day
- Dates: Convert DD/MM/YYYY to YYYY-MM-DD format
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
