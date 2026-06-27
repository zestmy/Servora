<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Ingredient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Detects likely-duplicate products on the Market List.
 *
 * Duplicates are a silent data-quality problem: when the same real-world product
 * is entered twice, a recipe line may reference one copy while price updates land
 * on the other — so recipe costing drifts out of sync. This service surfaces those
 * duplicates with a probability score so they can be merged.
 *
 * Strategy is hybrid for cost/accuracy: a cheap local pass (name normalisation +
 * token-blocked fuzzy matching) generates candidate clusters, then only those
 * candidates are sent to the AI to score duplicate probability with a reason.
 * Falls back to the local similarity score when no API key is configured.
 */
class DuplicateProductService
{
    /** Local name-similarity threshold (0–1) for treating two products as merge candidates. */
    private const CANDIDATE_THRESHOLD = 0.55;

    /** Live-warning threshold (0–1) when typing a new product name. */
    private const LIVE_THRESHOLD = 0.60;

    /** Only surface clusters whose final duplicate probability is at least this (0–100). */
    private const DISPLAY_MIN_PROBABILITY = 55;

    /** Caps to bound AI cost on large catalogues. */
    private const MAX_CLUSTERS = 40;
    private const MAX_CLUSTER_SIZE = 8;

    /** Skip ultra-common tokens (e.g. "chicken") that would explode the candidate set. */
    private const MAX_TOKEN_BUCKET = 400;

    private const MODEL = 'anthropic/claude-sonnet-4';

    /**
     * Scan a company's products for likely duplicates.
     *
     * @return array{clusters: array<int,array>, scanned: int, ai: bool, truncated: bool, note: ?string}
     */
    public function detect(int $companyId): array
    {
        $ingredients = Ingredient::where('company_id', $companyId)
            ->where('is_prep', false)
            ->with(['ingredientCategory', 'baseUom'])
            ->withCount(['recipeLines as recipes_count' => function ($q) {
                $q->select(DB::raw('count(distinct recipe_id)'));
            }])
            ->get(['id', 'name', 'code', 'ingredient_category_id', 'base_uom_id', 'purchase_price', 'is_active']);

        $scanned = $ingredients->count();
        $empty = ['clusters' => [], 'scanned' => $scanned, 'ai' => false, 'truncated' => false, 'note' => null];

        if ($scanned < 2) {
            return $empty;
        }

        $ignored = $this->loadIgnoredPairs($companyId);
        $clusters = $this->candidateClusters($ingredients, $ignored);
        if (empty($clusters)) {
            return $empty;
        }

        $truncated = false;
        if (count($clusters) > self::MAX_CLUSTERS) {
            $clusters = array_slice($clusters, 0, self::MAX_CLUSTERS);
            $truncated = true;
        }

        $byId = $ingredients->keyBy('id');
        [$scored, $aiUsed, $note] = $this->score($clusters, $byId);

        $scored = array_values(array_filter($scored, fn ($c) => $c['probability'] >= self::DISPLAY_MIN_PROBABILITY));
        usort($scored, fn ($a, $b) => $b['probability'] <=> $a['probability']);

        return [
            'clusters'  => $scored,
            'scanned'   => $scanned,
            'ai'        => $aiUsed,
            'truncated' => $truncated,
            'note'      => $note,
        ];
    }

    /**
     * Quick local check for the Add/Edit modal: products whose name closely
     * resembles the one being typed. No AI — must be instant.
     *
     * @return array<int,array{id:int,name:string,recipes_count:int,is_active:bool,score:int}>
     */
    public function findSimilar(string $name, int $companyId, ?int $excludeId = null, int $limit = 5): array
    {
        $norm = $this->normalize($name);
        if (strlen($norm) < 3) {
            return [];
        }

        $tokensQ = $this->tokens($norm);

        $rows = Ingredient::where('company_id', $companyId)
            ->where('is_prep', false)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->withCount(['recipeLines as recipes_count' => function ($q) {
                $q->select(DB::raw('count(distinct recipe_id)'));
            }])
            ->get(['id', 'name', 'is_active']);

        $matches = [];
        foreach ($rows as $r) {
            $rn = $this->normalize($r->name);
            $s = $this->similarity($norm, $rn, $tokensQ, $this->tokens($rn));
            if ($s >= self::LIVE_THRESHOLD) {
                $matches[] = [
                    'id'            => $r->id,
                    'name'          => $r->name,
                    'recipes_count' => (int) ($r->recipes_count ?? 0),
                    'is_active'     => (bool) $r->is_active,
                    'score'         => (int) round($s * 100),
                ];
            }
        }

        usort($matches, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($matches, 0, $limit);
    }

    // ── Ignored pairs (user-confirmed "not duplicates") ─────────────────────

    /**
     * Record that the given products are NOT duplicates of one another, so a
     * future scan won't pair them again. Stores every unordered pair (low-high).
     *
     * @param  array<int>  $ingredientIds
     */
    public function ignorePairs(int $companyId, array $ingredientIds): void
    {
        if (! Schema::hasTable('ignored_duplicate_pairs')) {
            return;
        }

        $ids = array_values(array_unique(array_map('intval', $ingredientIds)));
        $count = count($ids);
        if ($count < 2) {
            return;
        }

        $now = now();
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $rows[] = [
                    'company_id'      => $companyId,
                    'ingredient_id_a' => min($ids[$i], $ids[$j]),
                    'ingredient_id_b' => max($ids[$i], $ids[$j]),
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }
        }

        DB::table('ignored_duplicate_pairs')->insertOrIgnore($rows);
    }

    /**
     * @return array<string,bool>  keyed "low-high" for fast lookup
     */
    private function loadIgnoredPairs(int $companyId): array
    {
        if (! Schema::hasTable('ignored_duplicate_pairs')) {
            return [];
        }

        $out = [];
        DB::table('ignored_duplicate_pairs')
            ->where('company_id', $companyId)
            ->get(['ingredient_id_a', 'ingredient_id_b'])
            ->each(function ($r) use (&$out) {
                $out[$r->ingredient_id_a . '-' . $r->ingredient_id_b] = true;
            });

        return $out;
    }

    // ── Candidate generation ────────────────────────────────────────────────

    /**
     * Group products into candidate duplicate clusters using token-blocked
     * fuzzy matching. Returns clusters as ['ids' => int[], 'local_score' => 0-100].
     *
     * @return array<int,array{ids:array<int>,local_score:int}>
     */
    private function candidateClusters(Collection $ingredients, array $ignored = []): array
    {
        $norm = [];
        $tokens = [];
        $index = [];   // significant token => [ids]

        foreach ($ingredients as $ing) {
            $n = $this->normalize($ing->name);
            $norm[$ing->id] = $n;
            $tk = $this->tokens($n);
            $tokens[$ing->id] = $tk;
            foreach ($tk as $t) {
                if (strlen($t) >= 3) {
                    $index[$t][] = $ing->id;
                }
            }
        }

        // Only compare products that share a significant token (blocking).
        $pairScore = [];
        foreach ($index as $ids) {
            $ids = array_values(array_unique($ids));
            $count = count($ids);
            if ($count < 2 || $count > self::MAX_TOKEN_BUCKET) {
                continue;
            }
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = $ids[$i];
                    $b = $ids[$j];
                    $key = $a < $b ? "$a-$b" : "$b-$a";
                    if (isset($pairScore[$key]) || isset($ignored[$key])) {
                        continue;
                    }
                    $s = $this->similarity($norm[$a], $norm[$b], $tokens[$a], $tokens[$b]);
                    if ($s >= self::CANDIDATE_THRESHOLD) {
                        $pairScore[$key] = $s;
                    }
                }
            }
        }

        if (empty($pairScore)) {
            return [];
        }

        $clusters = $this->cluster(array_keys($pairScore));

        $out = [];
        foreach ($clusters as $ids) {
            $ids = array_values(array_unique($ids));
            if (count($ids) < 2) {
                continue;
            }

            $best = 0.0;
            $n = count($ids);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $a = $ids[$i];
                    $b = $ids[$j];
                    $key = $a < $b ? "$a-$b" : "$b-$a";
                    if (isset($pairScore[$key])) {
                        $best = max($best, $pairScore[$key]);
                    }
                }
            }

            if (count($ids) > self::MAX_CLUSTER_SIZE) {
                $ids = array_slice($ids, 0, self::MAX_CLUSTER_SIZE);
            }

            $out[] = ['ids' => $ids, 'local_score' => (int) round($best * 100)];
        }

        usort($out, fn ($a, $b) => $b['local_score'] <=> $a['local_score']);

        return $out;
    }

    /**
     * Union-find clustering over linked "a-b" pairs.
     *
     * @param  array<int,string>  $pairKeys
     * @return array<int,array<int>>
     */
    private function cluster(array $pairKeys): array
    {
        $parent = [];

        $find = function ($x) use (&$parent, &$find) {
            while (($parent[$x] ?? $x) !== $x) {
                $parent[$x] = $parent[$parent[$x]] ?? $parent[$x];
                $x = $parent[$x];
            }
            return $x;
        };

        foreach ($pairKeys as $key) {
            [$a, $b] = array_map('intval', explode('-', $key));
            $parent[$a] = $parent[$a] ?? $a;
            $parent[$b] = $parent[$b] ?? $b;
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) {
                $parent[$rb] = $ra;
            }
        }

        $groups = [];
        foreach (array_keys($parent) as $id) {
            $groups[$find($id)][] = $id;
        }

        return array_values($groups);
    }

    // ── Scoring ─────────────────────────────────────────────────────────────

    /**
     * @param  array<int,array{ids:array<int>,local_score:int}>  $clusters
     * @return array{0:array<int,array>,1:bool,2:?string}  [scored, aiUsed, note]
     */
    private function score(array $clusters, Collection $byId): array
    {
        $apiKey = AppSetting::get('openrouter_api_key');
        if (! $apiKey) {
            return [
                $this->localFallback($clusters, $byId),
                false,
                'AI scoring unavailable — showing name-similarity estimates. Add an OpenRouter API key in Settings → API Keys for smarter detection.',
            ];
        }

        $payload = [];
        foreach ($clusters as $i => $cl) {
            $items = [];
            foreach ($cl['ids'] as $id) {
                $ing = $byId[$id] ?? null;
                if (! $ing) {
                    continue;
                }
                $items[] = [
                    'id'       => $ing->id,
                    'name'     => $ing->name,
                    'code'     => $ing->code,
                    'category' => $ing->ingredientCategory?->name,
                    'uom'      => $ing->baseUom?->abbreviation,
                    'price'    => $ing->purchase_price !== null ? (float) $ing->purchase_price : null,
                ];
            }
            $payload[] = ['index' => $i, 'products' => $items];
        }

        try {
            $aiResults = $this->callAi($apiKey, $payload);
        } catch (\Throwable $e) {
            Log::warning('Duplicate detection AI scoring failed', ['error' => $e->getMessage()]);
            return [
                $this->localFallback($clusters, $byId),
                false,
                'AI scoring failed — showing name-similarity estimates instead.',
            ];
        }

        $out = [];
        foreach ($clusters as $i => $cl) {
            $ai = $aiResults[$i] ?? null;
            $products = $this->productDetails($cl['ids'], $byId);

            $prob = $ai['probability'] ?? $cl['local_score'];
            $prob = max(0, min(100, (int) round((float) $prob)));

            $keep = $ai['suggested_keep_id'] ?? null;
            if (! in_array($keep, $cl['ids'], true)) {
                $keep = $this->suggestKeep($products);
            }

            $out[] = [
                'ids'               => $cl['ids'],
                'products'          => $products,
                'probability'       => $prob,
                'reason'            => $ai['reason'] ?? 'Similar product names.',
                'suggested_keep_id' => $keep,
            ];
        }

        return [$out, true, null];
    }

    /**
     * @param  array<int,array{ids:array<int>,local_score:int}>  $clusters
     * @return array<int,array>
     */
    private function localFallback(array $clusters, Collection $byId): array
    {
        $out = [];
        foreach ($clusters as $cl) {
            $products = $this->productDetails($cl['ids'], $byId);
            $out[] = [
                'ids'               => $cl['ids'],
                'products'          => $products,
                'probability'       => (int) $cl['local_score'],
                'reason'            => 'Estimated from name similarity.',
                'suggested_keep_id' => $this->suggestKeep($products),
            ];
        }
        return $out;
    }

    /**
     * @param  array<int>  $ids
     * @return array<int,array>
     */
    private function productDetails(array $ids, Collection $byId): array
    {
        $items = [];
        foreach ($ids as $id) {
            $ing = $byId[$id] ?? null;
            if (! $ing) {
                continue;
            }
            $items[] = [
                'id'            => $ing->id,
                'name'          => $ing->name,
                'code'          => $ing->code,
                'category'      => $ing->ingredientCategory?->name,
                'uom'           => $ing->baseUom?->abbreviation,
                'price'         => $ing->purchase_price !== null ? (float) $ing->purchase_price : null,
                'recipes_count' => (int) ($ing->recipes_count ?? 0),
                'is_active'     => (bool) $ing->is_active,
            ];
        }
        return $items;
    }

    /**
     * Best canonical product to keep: most recipe usage, then active, then priced,
     * then oldest (smallest id).
     *
     * @param  array<int,array>  $products
     */
    private function suggestKeep(array $products): ?int
    {
        $best = null;
        $bestKey = null;
        foreach ($products as $p) {
            $key = [
                $p['recipes_count'],
                $p['is_active'] ? 1 : 0,
                $p['price'] !== null ? 1 : 0,
                -$p['id'],
            ];
            if ($bestKey === null || $key > $bestKey) {
                $bestKey = $key;
                $best = $p['id'];
            }
        }
        return $best;
    }

    // ── AI call ─────────────────────────────────────────────────────────────

    /**
     * @param  array<int,array>  $payload
     * @return array<int,array{probability:?float,reason:?string,suggested_keep_id:?int}>
     */
    private function callAi(string $apiKey, array $payload): array
    {
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
                'model'      => self::MODEL,
                'max_tokens' => 3000,
                'messages'   => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $this->userPrompt($payload)],
                ],
                'response_format' => ['type' => 'json_object'],
            ]);

        set_time_limit((int) $previousTimeout ?: 60);

        if ($response->failed()) {
            $body = $response->json();
            $msg = $body['error']['message'] ?? ('HTTP ' . $response->status());
            throw new \RuntimeException($msg);
        }

        $content = $response->json('choices.0.message.content') ?? '';
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE && preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $content, $m)) {
            $data = json_decode(trim($m[1]), true);
        }

        if (! is_array($data) || ! isset($data['clusters']) || ! is_array($data['clusters'])) {
            throw new \RuntimeException('Invalid AI response.');
        }

        $byIndex = [];
        foreach ($data['clusters'] as $c) {
            if (! isset($c['index'])) {
                continue;
            }
            $byIndex[(int) $c['index']] = [
                'probability'       => $c['probability'] ?? null,
                'reason'            => isset($c['reason']) ? (string) $c['reason'] : null,
                'suggested_keep_id' => isset($c['suggested_keep_id']) ? (int) $c['suggested_keep_id'] : null,
            ];
        }

        return $byIndex;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a data-quality assistant for an F&B inventory system. You are given groups of products (raw ingredients / purchased goods) whose names look similar. For EACH group, judge the probability (0-100) that the products are duplicate entries for the SAME real-world purchasable product — i.e. the same item entered more than once with slightly different spelling, word order, abbreviation, language (English/Malay), brand, or supplier code.

Critical rules:
- TRUE duplicates: the same product typed differently, e.g. "CHICKEN BREAST" vs "BREAST CHICKEN" vs "CHICKEN BREST" vs "AYAM BREAST". Probability high (80-100).
- NOT duplicates: genuinely different products that merely share words, e.g. "CHICKEN BREAST" vs "CHICKEN THIGH", "MILK" vs "MILK POWDER", "TOMATO" vs "TOMATO PASTE", or distinct grades/sizes stocked separately. Probability low (0-40).
- A different unit of measure or price alone does NOT make them different products — duplicates often differ on price (that mismatch is exactly the problem being detected).
- When unsure, be conservative and lower the probability.
- suggested_keep_id: the id of the product that should be kept as canonical — prefer the clearest, most complete, correctly spelled name.

Return ONLY valid JSON (no markdown) with this exact shape:
{"clusters":[{"index":0,"probability":92,"reason":"short human explanation under 160 chars","suggested_keep_id":12}]}
Echo back the same "index" for every group you were given.
PROMPT;
    }

    /**
     * @param  array<int,array>  $payload
     */
    private function userPrompt(array $payload): string
    {
        return "Evaluate these product groups for duplicates and return the JSON described.\n\n"
            . json_encode(['groups' => $payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    // ── Text helpers ────────────────────────────────────────────────────────

    private function normalize(string $name): string
    {
        $n = strtolower(trim($name));
        $n = preg_replace('/[^a-z0-9]+/', ' ', $n);
        $n = preg_replace('/\s+/', ' ', $n);
        return trim($n);
    }

    /**
     * @return array<int,string>
     */
    private function tokens(string $normalized): array
    {
        return array_values(array_unique(array_filter(
            explode(' ', $normalized),
            fn ($t) => strlen($t) >= 2
        )));
    }

    /**
     * Combined name similarity (0–1): the higher of token-set overlap (Jaccard)
     * and a character-overlap blend, so it catches both reordered words and typos.
     *
     * @param  array<int,string>  $tokensA
     * @param  array<int,string>  $tokensB
     */
    private function similarity(string $a, string $b, array $tokensA, array $tokensB): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }

        $inter = count(array_intersect($tokensA, $tokensB));
        $union = count(array_unique(array_merge($tokensA, $tokensB)));
        $jaccard = $union > 0 ? $inter / $union : 0.0;

        similar_text($a, $b, $pct);
        $char = $pct / 100;

        return max($jaccard, 0.4 * $jaccard + 0.6 * $char);
    }
}
