<?php

namespace App\Services;

use App\Models\AppSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generates official public holidays for a given location and year using the
 * configured OpenRouter LLM. Returned holidays are intended to be stored as
 * CalendarEvents (category "holiday") so they feed AI Analytics as factors.
 */
class PublicHolidayService
{
    /**
     * @return array<int, array{date: string, name: string, impact: string}>
     *
     * @throws \RuntimeException when AI is not configured or the request fails.
     */
    public function generate(string $country, ?string $state, int $year): array
    {
        $apiKey = AppSetting::get('openrouter_api_key');
        if (empty($apiKey)) {
            throw new \RuntimeException('AI is not configured. Add an OpenRouter API key in Settings > API Keys.');
        }

        $location = $state ? "{$state}, {$country}" : $country;

        $system = 'You are an authoritative reference for official public holidays. '
            . 'You respond with ONLY valid JSON — no prose, no markdown code fences.';

        $user = "List all official public holidays for {$location} in the year {$year}. "
            . ($state
                ? "Include both national public holidays and the state-level public holidays observed specifically in {$state}. "
                : "Include all national public holidays. ")
            . 'For each holiday, estimate its expected impact on food & beverage / restaurant dining sales as exactly one of: '
            . '"positive" (festive or celebratory days when people tend to dine out more), '
            . '"negative" (somber, fasting, or observance days when dining out tends to drop), or "neutral". '
            . 'If a holiday is observed across multiple dates, return each observed date as a separate entry. '
            . 'Return JSON in exactly this shape and nothing else: '
            . '{"holidays":[{"date":"YYYY-MM-DD","name":"Holiday name","impact":"positive|negative|neutral"}]}';

        $previousTimeout = ini_get('max_execution_time');
        set_time_limit(120);

        try {
            $response = Http::connectTimeout(15)
                ->timeout(90)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                    'HTTP-Referer'  => config('app.url', 'http://localhost'),
                    'X-Title'       => config('app.name', 'Servora'),
                ])
                ->post('https://openrouter.ai/api/v1/chat/completions', [
                    // Fast, capable default model (mirrors AiAnalyticsService) — a
                    // slow reasoning model would risk timing the request out.
                    'model'      => 'anthropic/claude-sonnet-4',
                    'max_tokens' => 4096,
                    'messages'   => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user',   'content' => $user],
                    ],
                ]);
        } finally {
            set_time_limit((int) $previousTimeout ?: 60);
        }

        if ($response->failed()) {
            Log::error('PublicHolidayService OpenRouter error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            $body = $response->json();
            $msg = $body['error']['message'] ?? ('HTTP ' . $response->status());
            throw new \RuntimeException('Holiday generation failed: ' . $msg);
        }

        $content = $response->json()['choices'][0]['message']['content'] ?? '';

        return $this->parseHolidays($content, $year);
    }

    /**
     * @return array<int, array{date: string, name: string, impact: string}>
     */
    private function parseHolidays(string $content, int $year): array
    {
        $content = trim($content);

        // Strip markdown fences if the model added them anyway.
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $m)) {
            $content = $m[1];
        }
        // Isolate the JSON object.
        if (preg_match('/\{[\s\S]*\}/', $content, $m)) {
            $content = $m[0];
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($data['holidays']) || ! is_array($data['holidays'])) {
            throw new \RuntimeException('Could not read holidays from the AI response. Please try again.');
        }

        $validImpacts = ['positive', 'negative', 'neutral'];
        $out  = [];
        $seen = [];

        foreach ($data['holidays'] as $h) {
            if (! is_array($h)) {
                continue;
            }

            $name    = trim((string) ($h['name'] ?? ''));
            $rawDate = trim((string) ($h['date'] ?? ''));
            $impact  = strtolower(trim((string) ($h['impact'] ?? 'neutral')));

            if ($name === '' || $rawDate === '') {
                continue;
            }

            try {
                $date = Carbon::parse($rawDate)->format('Y-m-d');
            } catch (\Throwable $e) {
                continue;
            }

            // Keep only dates that fall in the requested year.
            if (substr($date, 0, 4) !== (string) $year) {
                continue;
            }

            if (! in_array($impact, $validImpacts, true)) {
                $impact = 'neutral';
            }

            $key = $date . '|' . mb_strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $out[] = ['date' => $date, 'name' => $name, 'impact' => $impact];
        }

        usort($out, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $out;
    }
}
