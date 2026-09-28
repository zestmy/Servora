<?php

namespace App\Jobs;

use App\Models\LandingPage;
use App\Services\Marketing\LandingTranslator;
use App\Support\Marketing\HomeCopy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * An AI draft of a country landing page, one small batch per job.
 *
 * It ran inside the admin's browser request first, 40 strings a time, and a
 * batch took ~90 s on production — past PHP's 60 s limit and nginx's 60 s
 * read timeout, so the request died and the screen sat on "Translating…"
 * for good. Now each job translates one small batch (BATCH strings or
 * CHARS of English, whichever comes first — about 30-45 s),
 * then queues the next with what is left. The worker's own limit is 60 s
 * and Redis re-delivers a job after 90 s, so a job must finish inside that.
 *
 * Progress and results live in the cache under state($pageId), not on the
 * page: a draft of a PUBLISHED page must not go live before a person reads
 * it. Admin › Country Pages merges the results into its blank boxes and
 * nothing is stored until Save.
 */
class TranslateLandingPage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const BATCH = 15;
    public const CHARS = 1200;

    public $tries = 1;
    public $timeout = 85;

    /** @param array<int, string> $keys HomeCopy keys still to translate */
    public function __construct(public int $pageId, public array $keys) {}

    public static function key(int $pageId): string
    {
        return "landing-ai:{$pageId}";
    }

    /** @return array{status: string, total: int, done: int, error: ?string, results: array<string, string>}|null */
    public static function state(int $pageId): ?array
    {
        return Cache::get(self::key($pageId));
    }

    /** Start a draft of $keys, replacing any earlier one. */
    public static function start(LandingPage $page, array $keys): void
    {
        Cache::put(self::key($page->id), [
            'status' => 'running', 'total' => count($keys), 'done' => 0, 'error' => null, 'results' => [],
        ], now()->addDay());

        if ($keys) {
            self::dispatch($page->id, array_values($keys));
        }
    }

    public function handle(LandingTranslator $translator): void
    {
        $state = self::state($this->pageId);
        $page = LandingPage::find($this->pageId);
        if (! $state || $state['status'] !== 'running' || ! $page) {
            return; // cancelled, replaced or deleted
        }

        // Up to BATCH strings, but no more than CHARS of English: 15 short
        // labels took 42 s on production, and a batch of FAQ answers at the
        // same count would not fit inside the timeout.
        $defaults = HomeCopy::defaults();
        $batch = [];
        $chars = 0;
        foreach ($this->keys as $key) {
            $len = mb_strlen($defaults[$key] ?? '');
            if ($batch && (count($batch) >= self::BATCH || $chars + $len > self::CHARS)) {
                break;
            }
            $batch[] = $key;
            $chars += $len;
        }
        $rest = array_slice($this->keys, count($batch));
        $english = array_intersect_key($defaults, array_flip($batch));

        try {
            $done = $translator->translate($english, $page->language_name, $page->locale);
        } catch (\Throwable $e) {
            $this->finish('failed', $e->getMessage());
            return;
        }

        $state = self::state($this->pageId) ?? $state;
        $state['results'] = array_merge($state['results'], $done);
        $state['done'] = min($state['total'], $state['done'] + count($batch));
        $state['status'] = $rest ? 'running' : 'done';
        Cache::put(self::key($this->pageId), $state, now()->addDay());

        if ($rest) {
            self::dispatch($this->pageId, $rest);
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->finish('failed', 'The translation stopped: '.$e->getMessage());
    }

    private function finish(string $status, string $error): void
    {
        $state = self::state($this->pageId);
        if ($state) {
            $state['status'] = $status;
            $state['error'] = $error;
            Cache::put(self::key($this->pageId), $state, now()->addDay());
        }
    }
}
