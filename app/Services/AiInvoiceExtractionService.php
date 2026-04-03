<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AiInvoiceExtractionService
{
    /**
     * Extract structured invoice data from an uploaded file using OpenRouter vision API.
     *
     * @param string $filePath Path relative to the public disk (e.g. "invoices/abc.jpg")
     * @return array ['data' => [...extracted], 'tokens' => [...], 'model' => string]
     */
    public static function extract(string $filePath): array
    {
        $apiKey = AppSetting::get('openrouter_api_key');
        if (! $apiKey) {
            throw new \RuntimeException('OpenRouter API key not configured. Go to Settings > API Keys.');
        }

        $model = AppSetting::get('openrouter_model') ?: 'anthropic/claude-sonnet-4-5-20250514';
        $fullPath = Storage::disk('public')->path($filePath);

        if (! file_exists($fullPath)) {
            throw new \RuntimeException('Uploaded file not found.');
        }

        $mimeType = mime_content_type($fullPath);
        $base64 = base64_encode(file_get_contents($fullPath));

        // For PDFs, use application/pdf mime; many vision models support it directly
        $dataUri = "data:{$mimeType};base64,{$base64}";

        $previousTimeout = ini_get('max_execution_time');
        set_time_limit(120);

        $response = Http::timeout(90)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => config('app.url', 'http://localhost'),
                'X-Title'       => config('app.name', 'Servora'),
            ])
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model'      => $model,
                'max_tokens' => 4096,
                'messages'   => [
                    ['role' => 'system', 'content' => self::systemPrompt()],
                    ['role' => 'user', 'content' => [
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUri]],
                        ['type' => 'text', 'text' => self::extractionPrompt()],
                    ]],
                ],
                'response_format' => ['type' => 'json_object'],
            ]);

        set_time_limit((int) $previousTimeout ?: 60);

        if ($response->failed()) {
            Log::error('AI Invoice Extraction failed', ['status' => $response->status(), 'body' => $response->body()]);
            $body = $response->json();
            $msg = $body['error']['message'] ?? ('HTTP ' . $response->status());
            throw new \RuntimeException('AI invoice extraction failed: ' . $msg);
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? '';

        $extracted = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try to extract JSON from markdown code blocks
            if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $content, $m)) {
                $extracted = json_decode(trim($m[1]), true);
            }
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('AI returned invalid JSON. Please try again.');
            }
        }

        return [
            'data'   => $extracted,
            'tokens' => [
                'input'  => $data['usage']['prompt_tokens'] ?? null,
                'output' => $data['usage']['completion_tokens'] ?? null,
            ],
            'model' => $data['model'] ?? $model,
        ];
    }

    private static function systemPrompt(): string
    {
        return <<<'PROMPT'
You are an invoice data extraction specialist for F&B/restaurant procurement. Extract structured data from supplier invoices with high accuracy. Return ONLY valid JSON with no commentary or markdown formatting.
PROMPT;
    }

    private static function extractionPrompt(): string
    {
        return <<<'PROMPT'
Extract all data from this supplier invoice and return as JSON with this exact structure:
{
  "supplier_name": "string - the supplier/vendor company name",
  "supplier_address": "string or null",
  "invoice_number": "string - the invoice number printed on this document",
  "invoice_date": "YYYY-MM-DD",
  "due_date": "YYYY-MM-DD or null",
  "currency": "3-letter code, default MYR",
  "line_items": [
    {
      "description": "item name/description exactly as printed",
      "quantity": 0.00,
      "unit": "unit of measure as printed (kg, pcs, box, ctn, etc)",
      "unit_price": 0.00,
      "total_price": 0.00,
      "tax_amount": 0.00
    }
  ],
  "subtotal": 0.00,
  "tax_amount": 0.00,
  "tax_rate_percent": 0.00,
  "delivery_charges": 0.00,
  "total_amount": 0.00,
  "payment_terms": "string or null",
  "notes": "any additional notes or null"
}

Rules:
- Use numeric values (not strings) for all amounts and quantities
- If a field cannot be determined, use null
- For line items, capture EVERY line item on the invoice
- If tax per line is not shown, set tax_amount to null for each line
- Dates must be in YYYY-MM-DD format
- The description should match what is printed on the invoice as closely as possible
PROMPT;
    }
}
