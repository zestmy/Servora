<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZeoniqDepartmentMatchingService
{
    /**
     * Suggest matches between Zeoniq department names and Servora sales categories.
     *
     * @param array $zeoniqDepartments List of department names from Excel
     * @param Collection $salesCategories Available SalesCategory models
     * @return array Array of suggestions with structure:
     *               [{zeoniq_department, suggested_category_id, category_name, confidence, reasoning}]
     */
    public function suggestMatches(array $zeoniqDepartments, Collection $salesCategories): array
    {
        if (empty($zeoniqDepartments) || $salesCategories->isEmpty()) {
            return [];
        }

        $provider = AppSetting::get('ai_provider', 'anthropic');
        $apiKey = $this->resolveApiKey($provider);

        try {
            $prompt = $this->buildPrompt($zeoniqDepartments, $salesCategories);

            $result = $provider === 'openrouter'
                ? $this->callOpenRouter($apiKey, $prompt)
                : $this->callAnthropic($apiKey, $prompt);

            return $this->parseResponse($result['response'], $salesCategories);
        } catch (\Exception $e) {
            Log::warning('Zeoniq department matching failed', [
                'error' => $e->getMessage(),
                'departments' => $zeoniqDepartments,
            ]);
            throw $e;
        }
    }

    private function resolveApiKey(string $provider): string
    {
        if ($provider === 'openrouter') {
            $key = AppSetting::get('openrouter_api_key');
            if (empty($key)) {
                throw new \RuntimeException('OpenRouter API key not configured. Contact your system administrator.');
            }
            return $key;
        }

        $key = AppSetting::get('anthropic_api_key');
        if (empty($key)) {
            throw new \RuntimeException('Anthropic API key not configured. Contact your system administrator.');
        }
        return $key;
    }

    private function buildPrompt(array $zeoniqDepartments, Collection $salesCategories): string
    {
        $deptList = implode("\n", array_map(fn($d) => "- $d", $zeoniqDepartments));

        $catList = $salesCategories->map(fn($cat) => "- {$cat->name} (ID: {$cat->id})")->implode("\n");

        return <<<PROMPT
Match these Zeoniq POS department names to the most appropriate Servora sales categories:

**Zeoniq Departments:**
$deptList

**Available Servora Sales Categories:**
$catList

Return a JSON array with the following structure for each department:
[
  {
    "zeoniq_department": "exact department name from above",
    "suggested_category_id": numeric ID or null if no good match,
    "confidence": "high" | "medium" | "low",
    "reasoning": "brief explanation of why this match was chosen"
  }
]

**Confidence Guidelines:**
- "high" (90%+): Exact or near-exact name match (e.g., "Food" → "Food")
- "medium" (60-89%): Related but not exact (e.g., "Alcoholic Drinks" → "Beverages")
- "low" (<60%): Weak match or uncertain (e.g., "Sundries" → "Retail")

If no reasonable match exists for a department, set suggested_category_id to null and confidence to "low".

Return ONLY the JSON array, no additional text.
PROMPT;
    }

    private function callAnthropic(string $apiKey, string $prompt): array
    {
        $model = 'claude-sonnet-4-20250514';

        $response = Http::timeout(30)
            ->withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => $model,
                'max_tokens' => 2048,
                'system'     => 'You are an expert at matching point-of-sale department names to standardized sales categories for restaurant accounting systems. Analyze department names and suggest the most appropriate category match with confidence levels.',
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            Log::error('Anthropic API error (department matching)', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('AI department matching failed (HTTP ' . $response->status() . '). Please try again or map manually.');
        }

        $data = $response->json();

        return [
            'response' => $data['content'][0]['text'] ?? '',
            'model'    => $model,
        ];
    }

    private function callOpenRouter(string $apiKey, string $prompt): array
    {
        $model = AppSetting::get('openrouter_model') ?: 'anthropic/claude-sonnet-4';

        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => config('app.url', 'http://localhost'),
                'X-Title'       => config('app.name', 'Servora'),
            ])
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model'      => $model,
                'max_tokens' => 2048,
                'messages'   => [
                    [
                        'role'    => 'system',
                        'content' => 'You are an expert at matching point-of-sale department names to standardized sales categories for restaurant accounting systems. Analyze department names and suggest the most appropriate category match with confidence levels.',
                    ],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            Log::error('OpenRouter API error (department matching)', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            $body = $response->json();
            $msg  = $body['error']['message'] ?? ('HTTP ' . $response->status());
            throw new \RuntimeException('AI department matching failed via OpenRouter: ' . $msg);
        }

        $data        = $response->json();
        $actualModel = $data['model'] ?? $model;

        return [
            'response' => $data['choices'][0]['message']['content'] ?? '',
            'model'    => $actualModel,
        ];
    }

    private function parseResponse(string $responseText, Collection $salesCategories): array
    {
        // Extract JSON from response (may have markdown code fences)
        $json = $this->extractJson($responseText);

        if (!$json) {
            // Fallback to empty suggestions if parsing fails
            Log::warning('Failed to parse AI response for department matching', ['response' => $responseText]);
            return [];
        }

        $suggestions = json_decode($json, true);

        if (!is_array($suggestions)) {
            return [];
        }

        // Validate and enrich suggestions
        $results = [];
        foreach ($suggestions as $suggestion) {
            if (!isset($suggestion['zeoniq_department'])) {
                continue;
            }

            $categoryId   = $suggestion['suggested_category_id'] ?? null;
            $categoryName = null;

            if ($categoryId) {
                $category = $salesCategories->firstWhere('id', $categoryId);
                if ($category) {
                    $categoryName = $category->name;
                } else {
                    // Invalid category ID, set to null
                    $categoryId = null;
                }
            }

            $confidence = strtolower($suggestion['confidence'] ?? 'low');
            if (!in_array($confidence, ['high', 'medium', 'low'])) {
                $confidence = 'low';
            }

            $results[] = [
                'zeoniq_department'      => $suggestion['zeoniq_department'],
                'suggested_category_id'  => $categoryId,
                'category_name'          => $categoryName,
                'confidence'             => $confidence,
                'reasoning'              => $suggestion['reasoning'] ?? '',
            ];
        }

        return $results;
    }

    private function extractJson(string $text): ?string
    {
        // Try to extract JSON from markdown code fences
        if (preg_match('/```json\s*(\[[\s\S]*?\])\s*```/i', $text, $matches)) {
            return $matches[1];
        }

        if (preg_match('/```\s*(\[[\s\S]*?\])\s*```/', $text, $matches)) {
            return $matches[1];
        }

        // Try direct JSON parse
        $trimmed = trim($text);
        if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
            return $trimmed;
        }

        return null;
    }
}
