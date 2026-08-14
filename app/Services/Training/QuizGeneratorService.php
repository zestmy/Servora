<?php

namespace App\Services\Training;

use App\Models\AppSetting;
use App\Models\TrainingCourse;
use App\Models\TrainingQuiz;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Write a quiz from a course's material.
 *
 * Same transport as AiAnalyticsService — OpenRouter, keyed from
 * Settings > API Keys — and the same reason for pinning the model rather than
 * honouring `openrouter_model`: a reasoning model's thinking phase on a
 * document-sized prompt reliably times the request out, and a manager pressing
 * "Generate" is watching a spinner while it does.
 *
 * THE PARSER IS THE INTERESTING PART, and it is deliberately separable from the
 * HTTP call. A model asked for JSON returns JSON most of the time and JSON
 * inside a ```json fence with a sentence of preamble the rest of the time.
 * Worse, it occasionally returns a perfectly-formed question whose `correct`
 * index points past the end of its own options — which would silently create a
 * question nobody can ever get right, and which no amount of prompt wording
 * fully prevents. So every question is validated against its own options here,
 * and a bad one is DROPPED rather than repaired: inventing a correct answer for
 * a question about food safety is worse than asking one question fewer.
 */
class QuizGeneratorService
{
    /**
     * Pinned for the same reason AiAnalyticsService pins it — see the class
     * note. Recorded on the quiz as `ai_model` so an author can tell which
     * questions came from which generation.
     */
    public const MODEL = 'anthropic/claude-sonnet-4';

    public const MAX_QUESTIONS = 30;

    public function __construct(private SourceTextExtractor $extractor)
    {
    }

    public function isConfigured(): bool
    {
        return filled(AppSetting::get('openrouter_api_key'));
    }

    /**
     * Generate questions for a course and attach them to a quiz.
     *
     * `$replace` empties the quiz first. Regenerating is the common case —
     * an author reads the first draft, does not like the balance, and asks
     * again — and appending would leave them deleting twenty questions by hand.
     *
     * @return array{questions: int, model: string, dropped: int}
     */
    public function generateForCourse(
        TrainingCourse $course,
        TrainingQuiz $quiz,
        int $count = 8,
        string $difficulty = 'mixed',
        bool $replace = true,
    ): array {
        $material = trim((string) $course->content);

        if ($material === '') {
            throw new \RuntimeException(
                'This course has no material to read yet. Add the text (or re-import the SOP) and try again.'
            );
        }

        $raw = $this->ask($material, $course, min($count, self::MAX_QUESTIONS), $difficulty, $quiz->language ?? 'en');

        $parsed  = $this->parseQuestions($raw);
        $dropped = $parsed['dropped'];

        if ($parsed['questions'] === []) {
            throw new \RuntimeException(
                'The AI did not return any usable questions. Try again, or shorten the material.'
            );
        }

        DB::transaction(function () use ($quiz, $parsed, $replace) {
            if ($replace) {
                $quiz->questions()->delete();
            }

            $offset = $replace ? 0 : (int) $quiz->questions()->max('sort_order') + 1;

            foreach ($parsed['questions'] as $i => $question) {
                $quiz->questions()->create($question + ['sort_order' => $offset + $i]);
            }

            $quiz->forceFill([
                'generated_by_ai' => true,
                'ai_model'        => self::MODEL,
                'generated_at'    => now(),
            ])->save();
        });

        return [
            'questions' => count($parsed['questions']),
            'model'     => self::MODEL,
            'dropped'   => $dropped,
        ];
    }

    // ── The call ──────────────────────────────────────────────────────────

    private function ask(
        string $material,
        TrainingCourse $course,
        int $count,
        string $difficulty,
        string $language = 'en',
    ): string {
        $apiKey = AppSetting::get('openrouter_api_key');

        if (empty($apiKey)) {
            throw new \RuntimeException('OpenRouter API key not configured. Go to Settings > API Keys.');
        }

        $material = $this->extractor->tidy($material);

        $previousTimeout = ini_get('max_execution_time');
        set_time_limit(180);

        $response = Http::connectTimeout(15)
            ->timeout(150)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => config('app.url', 'http://localhost'),
                'X-Title'       => config('app.name', 'Servora'),
            ])
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model'      => self::MODEL,
                'max_tokens' => 4096,
                // Low but not zero. Zero makes every regeneration return the
                // same eight questions, which defeats the "ask again, I want a
                // different balance" flow the button exists for.
                'temperature' => 0.4,
                'messages'   => [
                    ['role' => 'system', 'content' => $this->systemPrompt($language)],
                    ['role' => 'user',   'content' => $this->userPrompt($material, $course, $count, $difficulty, $language)],
                ],
            ]);

        set_time_limit((int) $previousTimeout ?: 60);

        if ($response->failed()) {
            Log::error('[training] OpenRouter error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            $message = $response->json('error.message') ?? ('HTTP ' . $response->status());

            throw new \RuntimeException('Quiz generation failed: ' . $message);
        }

        return (string) ($response->json('choices.0.message.content') ?? '');
    }

    /**
     * How to ask for each language.
     *
     * NAMED SPECIFICALLY, not "translate it". The failure a vague instruction
     * produces is a half-translation: an English prompt with Malay options, or
     * Indonesian vocabulary in a Malaysian paper. Each entry states the variety
     * wanted, warns off the neighbouring one, and pins the true/false wording so
     * it cannot come back as "True/False" in an otherwise Malay quiz.
     *
     * Kitchen English is left alone on purpose in both. Staff say "chiller" and
     * "medium rare" on the floor; translating those produces a quiz about words
     * nobody uses, which tests vocabulary instead of the material.
     */
    private function languageBrief(string $language): string
    {
        [$yes, $no] = TrainingQuiz::booleanOptionsFor($language);

        return match ($language) {
            'ms' => <<<PROMPT

            WRITE EVERYTHING IN BAHASA MALAYSIA — every prompt, every option and
            every explanation. Malaysian Malay, NOT Indonesian: use `wang` not
            `uang`, `kereta` not `mobil`, `boleh` not `bisa`. The material below
            may be in English; read it in English and ask in Malay.

            Keep kitchen and service terms that Malaysian staff actually say in
            English — chiller, medium rare, allergen, briefing — rather than
            translating them into words nobody uses on a floor. You are testing
            the method, not their vocabulary.

            For "true_false" the options are exactly ["{$yes}", "{$no}"].
            PROMPT,

            'id' => <<<PROMPT

            WRITE EVERYTHING IN BAHASA INDONESIA — every prompt, every option and
            every explanation. Indonesian, NOT Malaysian Malay: use `uang` not
            `wang`, `bisa` not `boleh`, `mobil` not `kereta`. The material below
            may be in English; read it in English and ask in Indonesian.

            Keep kitchen and service terms that staff actually say in English —
            chiller, medium rare, allergen — rather than translating them into
            words nobody uses in a working kitchen.

            For "true_false" the options are exactly ["{$yes}", "{$no}"].
            PROMPT,

            default => <<<PROMPT

            Write everything in English.
            For "true_false" the options are exactly ["{$yes}", "{$no}"].
            PROMPT,
        };
    }

    private function systemPrompt(string $language = 'en'): string
    {
        $base = <<<'PROMPT'
        You write training quizzes for restaurant and cafe staff.

        Rules:
        - Ask ONLY about facts stated in the supplied material. Never introduce
          outside knowledge, and never ask about something the material merely
          implies.
        - Write for someone on a shift: short prompts, concrete wording, no
          jargon that is not in the material itself.
        - Prefer questions that change what somebody DOES — a temperature, an
          order of steps, which allergen, what to say to a guest — over
          questions about wording.
        - Every wrong option must be plausible to someone who half-remembers the
          material. Never use "all of the above", "none of the above", joke
          options, or options that are obviously absurd.
        - The explanation states the fact and, where the material makes it
          possible, why it matters.
        - Vary which option index is correct across the set.

        Reply with a JSON object and NOTHING else — no prose, no markdown fence:

        {"questions":[{
          "type":"mcq" | "true_false" | "multi",
          "prompt":"...",
          "options":["...","..."],
          "correct":[0],
          "explanation":"...",
          "difficulty":"easy" | "medium" | "hard",
          "topic":"short subject tag, 1-3 words"
        }]}

        "correct" holds ZERO-BASED indexes into that question's own "options".
        "mcq" has exactly 4 options and exactly 1 correct index.
        "true_false" has exactly 2 options and 1 correct index.
        "multi" has 4 to 6 options and 2 or 3 correct indexes.

        The JSON KEYS above are always these literal English strings, whatever
        language the questions themselves are written in. So are the values of
        "type" and "difficulty" — they are an interface, not prose, and
        translating them makes the reply unreadable.
        PROMPT;

        return $base . "\n" . $this->languageBrief($language);
    }

    private function userPrompt(
        string $material,
        TrainingCourse $course,
        int $count,
        string $difficulty,
        string $language = 'en',
    ): string {
        $mix = match ($difficulty) {
            'easy'   => 'Keep every question easy — this is a first-day check.',
            'hard'   => 'Make every question hard — this is a recertification for experienced staff.',
            default  => 'Mix the difficulty: roughly a third easy, a half medium, the rest hard.',
        };

        $shape = $course->is_compliance
            ? 'This is COMPLIANCE material. Favour true_false and mcq over multi, '
                . 'and make every question about what the rule actually requires.'
            : 'Include at least one "multi" question if the material supports one.';

        $title = $course->title;

        // Repeated here as well as in the system prompt. A single mention at
        // the top is the thing a long document pushes out of view, and the
        // symptom — the first two questions in Malay and the rest drifting back
        // to English — reads as a broken feature rather than a missed
        // instruction.
        $inLanguage = 'Write the questions in ' . (TrainingQuiz::LANGUAGES[$language] ?? 'English') . '.';

        return <<<PROMPT
        Write exactly {$count} questions about the training material below.

        Course title: {$title}
        {$inLanguage}
        {$mix}
        {$shape}

        --- MATERIAL BEGINS ---
        {$material}
        --- MATERIAL ENDS ---
        PROMPT;
    }

    // ── The parser ────────────────────────────────────────────────────────

    /**
     * Pull valid questions out of whatever the model actually said.
     *
     * Returns the questions ready for mass assignment, plus how many were
     * dropped — the caller surfaces that count, because "8 questions, 2
     * discarded" is worth knowing and silently returning six is not.
     *
     * @return array{questions: array<int, array<string, mixed>>, dropped: int}
     */
    public function parseQuestions(string $raw): array
    {
        $decoded = $this->decode($raw);

        $candidates = $decoded['questions'] ?? (array_is_list($decoded) ? $decoded : []);

        $questions = [];
        $dropped   = 0;

        foreach ($candidates as $candidate) {
            $question = $this->validate((array) $candidate);

            if ($question === null) {
                $dropped++;
                continue;
            }

            $questions[] = $question;
        }

        return ['questions' => $questions, 'dropped' => $dropped];
    }

    /**
     * JSON out of a reply that may be wrapped in prose or a fence.
     *
     * Tried in order: the whole string; the contents of a ```json fence; the
     * outermost {...}. Anything else is nothing, which the caller reports as
     * "no usable questions" rather than crashing on a null.
     *
     * @return array<string, mixed>
     */
    private function decode(string $raw): array
    {
        $raw = trim($raw);

        $attempts = [$raw];

        if (preg_match('/```(?:json)?\s*(.+?)```/s', $raw, $m)) {
            $attempts[] = trim($m[1]);
        }

        $first = strpos($raw, '{');
        $last  = strrpos($raw, '}');
        if ($first !== false && $last !== false && $last > $first) {
            $attempts[] = substr($raw, $first, $last - $first + 1);
        }

        foreach ($attempts as $attempt) {
            $decoded = json_decode($attempt, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        Log::warning('[training] could not decode AI quiz reply', ['head' => mb_substr($raw, 0, 400)]);

        return [];
    }

    /**
     * One question, or null if it cannot be trusted.
     *
     * The checks that matter are the last two: every correct index must point
     * at an option that exists, and there must be at least one of them. That is
     * the failure the class note describes — a well-formed question that is
     * impossible to answer correctly — and it is invisible until a trainee gets
     * it wrong and cannot see why.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    private function validate(array $raw): ?array
    {
        $prompt = trim((string) ($raw['prompt'] ?? ''));

        if ($prompt === '') {
            return null;
        }

        $options = array_values(array_filter(
            array_map(fn ($o) => trim((string) $o), (array) ($raw['options'] ?? [])),
            fn (string $o) => $o !== '',
        ));

        $type = in_array($raw['type'] ?? '', ['mcq', 'true_false', 'multi'], true)
            ? $raw['type']
            : 'mcq';

        // A two-option question is a true/false however it labelled itself, and
        // a "true_false" that came back with four options is not one.
        if ($type === 'true_false' && count($options) !== 2) {
            $type = count($options) >= 3 ? 'mcq' : 'true_false';
        }

        if (count($options) < 2) {
            return null;
        }

        $correct = array_values(array_unique(array_map(
            'intval',
            array_filter((array) ($raw['correct'] ?? []), fn ($c) => is_numeric($c)),
        )));
        sort($correct);

        $inRange = array_values(array_filter($correct, fn (int $i) => $i >= 0 && $i < count($options)));

        // An index pointing past the options means the model lost track of its
        // own list; the question cannot be salvaged without guessing.
        if ($inRange === [] || $inRange !== $correct) {
            return null;
        }

        // A single-answer type that came back with several correct indexes is
        // a "multi" that mislabelled itself, not a broken question.
        if ($type !== 'multi' && count($correct) > 1) {
            $type = 'multi';
        }

        // ...and every option being correct is not a question.
        if (count($correct) >= count($options)) {
            return null;
        }

        $difficulty = in_array($raw['difficulty'] ?? '', ['easy', 'medium', 'hard'], true)
            ? $raw['difficulty']
            : 'medium';

        return [
            'type'        => $type,
            'prompt'      => $prompt,
            'options'     => $options,
            'correct'     => $correct,
            'explanation' => trim((string) ($raw['explanation'] ?? '')) ?: null,
            'difficulty'  => $difficulty,
            'topic'       => mb_substr(trim((string) ($raw['topic'] ?? '')), 0, 120) ?: null,
        ];
    }

    /**
     * A quiz shell for a course, with sensible wording already in place.
     *
     * Created before generation rather than after, so a failed API call leaves
     * an empty quiz the author can add questions to by hand instead of nothing
     * at all.
     */
    public function draftQuizFor(TrainingCourse $course): TrainingQuiz
    {
        return TrainingQuiz::create([
            'company_id'         => $course->company_id,
            'training_course_id' => $course->id,
            'title'              => $course->title . ' — Quiz',
            'description'        => 'Generated from the course material.',
            'status'             => 'draft',
            // Cast, don't trust. TrainingCourse mirrors its column defaults so
            // this cannot be null any more, but issues_certificate is NOT NULL
            // and a null here took the whole insert down once already.
            'issues_certificate' => (bool) $course->is_compliance,
            // A race is wrong for compliance material — see the migration note.
            'speed_bonus'        => ! $course->is_compliance,
            'created_by'         => Auth::id(),
        ]);
    }
}
