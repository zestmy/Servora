<?php

namespace App\Services\Marketing;

use App\Jobs\TranslateLandingPage;
use App\Models\AppSetting;
use App\Support\ExecutionTime;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A first draft of a country landing page in another language, through the
 * same OpenRouter key the rest of the product's AI uses (Settings › API Keys).
 *
 * A draft, not a publish: it fills the boxes on Admin › Country Pages and a
 * person who reads the language checks them before the page goes live.
 * Marketing copy is the one place a machine translation embarrasses you in
 * front of the exact people you are trying to sell to.
 */
class LandingTranslator
{
    private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    /**
     * Fixed, NOT the admin's Settings › API Keys model. Production has that
     * set to deepseek/deepseek-r1, a reasoning model: it took 42 s for 15
     * short strings and then answered with empty content, which the admin saw
     * as "Could not read the AI response". Sonnet did the longest batch (the
     * FAQ answers) in 8 s. VisionService pins its model for the same reason.
     */
    private const MODEL = 'anthropic/claude-sonnet-4';

    /** Keys per request: small enough to finish well inside the timeout. */
    private const CHUNK = TranslateLandingPage::BATCH;

    /**
     * @param  array<string, string>  $strings  key => English
     * @return array<string, string>  key => translation, for the keys that came back
     */
    public function translate(array $strings, string $language, string $locale): array
    {
        $apiKey = (string) AppSetting::get('openrouter_api_key', '');
        if ($apiKey === '') {
            throw new \RuntimeException('No OpenRouter API key is set. Add one under Settings › API Keys.');
        }

        $model = self::MODEL;
        $previous = ExecutionTime::raise(90);
        $out = [];

        try {
            foreach (array_chunk($strings, self::CHUNK, true) as $chunk) {
                $out += $this->ask($apiKey, $model, $chunk, $language, $locale);
            }
        } finally {
            ExecutionTime::restore($previous);
        }

        return $out;
    }

    private function ask(string $apiKey, string $model, array $chunk, string $language, string $locale): array
    {
        $system = <<<TXT
        You translate the marketing website of Servora, restaurant operations software for food and
        beverage businesses (recipe costing, purchasing, inventory, food safety labels, HR and payroll).
        Translate into {$language} ({$locale}) as a native marketing copywriter would write it for
        restaurant owners and chefs in that country: natural, direct and warm, never literal.
        Rules:
        - Return ONLY a JSON object with exactly the same keys as the input, values translated.
        - Keep every placeholder that starts with a colon (:days, :basic, :full, :hr, :ck, :addon,
          :price, :target, :currency) exactly as written.
        - Keep product and module names as they are: Servora, POS Sync, Learn SOP, HACCP, WIP, GRN,
          PO, UOM, COGS, EA forms, Z-report, PIN, QR, CSV, PDF, Excel, Free, Basic, Full.
        - Keep "&", numbers, percentages and currency figures unchanged.
        - Short labels (buttons, tabs, tags) must stay short.
        TXT;

        // Inside the job's 85 s: a batch that cannot answer in 70 s fails cleanly.
        $response = Http::connectTimeout(10)->timeout(70)
            ->withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'HTTP-Referer'  => config('app.url', 'http://localhost'),
                'X-Title'       => config('app.name', 'Servora'),
            ])
            ->post(self::ENDPOINT, [
                'model'           => $model,
                'max_tokens'      => 4000,
                'response_format' => ['type' => 'json_object'],
                'messages'        => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => json_encode($chunk, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)],
                ],
            ]);

        if ($response->failed()) {
            Log::warning('Landing page translation failed', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);
            throw new \RuntimeException('The AI request failed: '.($response->json('error.message') ?? 'HTTP '.$response->status()));
        }

        $raw  = (string) $response->json('choices.0.message.content', '');
        $data = json_decode($raw, true);
        if (! is_array($data) && preg_match('/\{.*\}/s', $raw, $m)) {
            $data = json_decode($m[0], true);
        }
        if (! is_array($data)) {
            Log::warning('Landing page translation: unreadable response', [
                'finish_reason' => $response->json('choices.0.finish_reason'),
                'raw'           => mb_substr($raw, 0, 500),
            ]);
            throw new \RuntimeException($raw === ''
                ? 'The AI returned an empty answer. Please try again.'
                : 'Could not read the AI response. Please try again.');
        }

        // Only keys we asked for, only strings, and only where every
        // placeholder in the English survived — a dropped :days prints a
        // literal gap in the page.
        $clean = [];
        foreach ($chunk as $key => $english) {
            $value = $data[$key] ?? null;
            if (! is_string($value) || trim($value) === '') {
                continue;
            }
            preg_match_all('/:[a-z]+/', $english, $need);
            if (collect($need[0])->every(fn ($p) => str_contains($value, $p))) {
                $clean[$key] = trim($value);
            }
        }

        return $clean;
    }
}
