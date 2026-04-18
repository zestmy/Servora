<?php

namespace App\Livewire\Ingredients;

use App\Models\AppSetting;
use App\Models\ScannedDocument;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Step 1 of Price Watcher — upload a supplier document (invoice / quotation /
 * price list / delivery order) from file picker or camera, run AI extraction,
 * and stage the result as a ScannedDocument for later review.
 *
 * Spreadsheet imports are NOT supported here — they go through the classic
 * review flow directly (and still need a pre-picked supplier). Photos / PDFs
 * are the main mobile-friendly path.
 */
class ScanDocument extends Component
{
    use WithFileUploads;

    public $file = null;
    public ?int $supplierId = null;
    public ?int $lastScannedId = null;

    protected function rules(): array
    {
        return [
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ];
    }

    protected function messages(): array
    {
        return [
            'file.mimes' => 'Supported: PDF or image (JPG / PNG / WEBP).',
            'file.max'   => 'File size must not exceed 10 MB.',
        ];
    }

    public function processUpload(): void
    {
        $this->validate();

        $user      = Auth::user();
        $companyId = $user->company_id;

        // Persist the file under a company-scoped folder so documents stay
        // isolated across tenants.
        $disk = 'local';
        $path = $this->file->store("scanned-documents/{$companyId}", $disk);

        $doc = ScannedDocument::create([
            'company_id'        => $companyId,
            'uploaded_by'       => $user->id,
            'original_filename' => $this->file->getClientOriginalName(),
            'file_path'         => $path,
            'mime_type'         => $this->file->getMimeType(),
            'size_bytes'        => $this->file->getSize(),
            'status'            => 'pending',
            'supplier_id'       => $this->supplierId ?: null,
        ]);

        $this->lastScannedId = $doc->id;

        // Run AI extraction inline. Even a multi-page PDF finishes in ~30s —
        // acceptable for an interactive upload.
        $this->extractWithAi($doc);
    }

    private function extractWithAi(ScannedDocument $doc): void
    {
        $apiKey = AppSetting::get('openrouter_api_key');
        if (! $apiKey) {
            $doc->update([
                'status'        => 'failed',
                'error_message' => 'AI extraction requires an OpenRouter API key. Configure it in Settings → API Keys.',
            ]);
            return;
        }

        $absolutePath = Storage::disk('local')->path($doc->file_path);
        $mimeType     = $doc->mime_type ?: (mime_content_type($absolutePath) ?: 'application/pdf');
        $base64       = base64_encode(file_get_contents($absolutePath));
        $dataUri      = "data:{$mimeType};base64,{$base64}";

        $prompt = <<<'PROMPT'
Extract the supplier metadata AND all product/ingredient items from this supplier document (invoice, quotation, delivery order, or price list).

Return a JSON object:
{
  "supplier_name": "exactly as shown on the letterhead, or null",
  "document_date": "YYYY-MM-DD (invoice / quotation / DO date), or null",
  "items": [
    {
      "name": "clean product name",
      "code": "supplier SKU/code or null",
      "category": "product category or null",
      "uom": "purchasing unit (kg, pcs, box, bottle, etc.)",
      "pack_size": 0,
      "recipe_uom": "smaller recipe unit (g, ml, pcs, etc.)",
      "price": 0.00,
      "quantity": 0
    }
  ]
}

## Rules:
- "supplier_name": the company issuing the document. Preserve capitalisation. Use null if not shown.
- "document_date": the date printed on the document. Convert to YYYY-MM-DD. Use null if absent.
- Extract EVERY line item from the document.
- "name": clean product name. Remove weight/volume info (1KG, 500ML) but keep seafood size grades (6-8, 16/20).
- "code": supplier's SKU/product code if shown.
- "uom": the unit the item is SOLD in (box, ctn, kg, pail, bag, bottle, pack, tray, etc.).
- "recipe_uom": the smaller unit used in recipes (g, ml, pcs, etc.).
- "pack_size": how many recipe_uom in 1 purchasing uom. Set to 0 if unknown.
- "price": the unit price (per purchasing uom). Extract actual prices; never default to 0 if visible.
- "quantity": ordered/delivered quantity if shown, otherwise 0.
- Use numeric values for price, pack_size, quantity.

IMPORTANT: Return ONLY valid JSON. No markdown, no commentary.
PROMPT;

        $previousTimeout = ini_get('max_execution_time');
        set_time_limit(120);

        try {
            $response = Http::timeout(90)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                    'HTTP-Referer'  => config('app.url', 'http://localhost'),
                    'X-Title'       => config('app.name', 'Servora'),
                ])
                ->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model'      => 'google/gemini-2.5-flash',
                    'max_tokens' => 16384,
                    'messages'   => [
                        ['role' => 'user', 'content' => [
                            ['type' => 'image_url', 'image_url' => ['url' => $dataUri]],
                            ['type' => 'text', 'text' => $prompt],
                        ]],
                    ],
                    'response_format' => ['type' => 'json_object'],
                ]);

            set_time_limit((int) $previousTimeout ?: 60);

            if (! $response->successful()) {
                $body = $response->json();
                $msg  = $body['error']['message'] ?? $body['message'] ?? ('HTTP ' . $response->status());
                throw new \RuntimeException($msg);
            }

            $data    = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';
            $result  = $this->robustJsonDecode($content);

            $extracted = null;
            $detectedSupplier = null;
            $detectedDate     = null;
            if (is_array($result)) {
                $extracted        = $result['items'] ?? $result['ingredients'] ?? $result['products'] ?? $result['data'] ?? null;
                $detectedSupplier = $result['supplier_name'] ?? $result['supplier'] ?? null;
                $detectedDate     = $result['document_date'] ?? $result['date'] ?? $result['invoice_date'] ?? null;
            }

            if (empty($extracted) || ! is_array($extracted)) {
                throw new \RuntimeException('AI could not extract any items from this document.');
            }

            $docDate = null;
            if ($detectedDate) {
                try { $docDate = Carbon::parse($detectedDate)->toDateString(); } catch (\Throwable $e) {}
            }

            $doc->update([
                'status'                 => 'extracted',
                'supplier_name_detected' => $detectedSupplier ? trim($detectedSupplier) : null,
                'document_date_detected' => $docDate,
                'effective_date'         => $docDate ?: now()->toDateString(),
                'extracted_items'        => array_values($extracted),
                'error_message'          => null,
            ]);

            // Auto-match supplier by exact name (case-insensitive) for convenience.
            if (! $doc->supplier_id && $doc->supplier_name_detected) {
                $companyId = Auth::user()->company_id;
                $match = Supplier::where('company_id', $companyId)
                    ->whereRaw('LOWER(name) = ?', [strtolower($doc->supplier_name_detected)])
                    ->first();
                if ($match) {
                    $doc->update(['supplier_id' => $match->id]);
                }
            }

        } catch (\Throwable $e) {
            set_time_limit((int) $previousTimeout ?: 60);
            Log::error('Price Watcher scan extraction failed: ' . $e->getMessage());
            $doc->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    public function scanAnother(): void
    {
        $this->file          = null;
        $this->supplierId    = null;
        $this->lastScannedId = null;
        $this->resetValidation();
    }

    private function robustJsonDecode(string $raw): ?array
    {
        $raw = trim($raw);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) return $decoded;

        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?\s*```/s', $raw, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) return $decoded;
        }

        $start = strpos($raw, '{'); $end = strrpos($raw, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);
            if (is_array($decoded)) return $decoded;
        }
        return null;
    }

    public function render()
    {
        $companyId = Auth::user()->company_id;

        $suppliers = Supplier::where('company_id', $companyId)
            ->orderBy('name')
            ->select('id', 'name')
            ->get();

        $lastScan = $this->lastScannedId ? ScannedDocument::find($this->lastScannedId) : null;

        $pendingReviewCount = ScannedDocument::where('status', 'extracted')->count();

        return view('livewire.ingredients.scan-document', compact(
            'suppliers', 'lastScan', 'pendingReviewCount'
        ))->layout('layouts.app', ['title' => 'Scan Documents']);
    }
}
