<?php

namespace App\Services\Training;

use App\Models\AppSetting;
use App\Models\TrainingCourse;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuestionTranslation;
use App\Models\TrainingQuiz;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Support\ExecutionTime;

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

    // ── Translating ───────────────────────────────────────────────────────

    /**
     * Say the same questions in another language.
     *
     * NOT "generate a Malay quiz from the same material". That was the first
     * answer and it is subtly wrong: two independent generations produce two
     * different papers, so a Malay reader and an English reader sit different
     * exams and their scores are not comparable — which is the whole purpose of
     * a leaderboard they share. A translation keeps one set of questions, in
     * one order, with one answer key.
     *
     * THE OPTION COUNT IS THE INVARIANT. `correct` is a list of INDEXES into
     * the option array, so a translation that drops, adds or reorders an option
     * does not read a little worse — it marks the wrong answer correct for
     * everybody reading that language, silently, on a quiz that may be about
     * allergens. Any question whose translation does not come back with exactly
     * the same number of options is refused and reported, never padded.
     *
     * @return array{translated: int, skipped: int, language: string}
     */
    public function translateQuiz(TrainingQuiz $quiz, string $language): array
    {
        if (! isset(TrainingQuiz::LANGUAGES[$language])) {
            throw new \RuntimeException('That is not a language this product writes in.');
        }

        if ($language === ($quiz->language ?: 'en')) {
            throw new \RuntimeException('The quiz is already written in that language.');
        }

        $questions = $quiz->questions()->orderBy('sort_order')->orderBy('id')->get();

        if ($questions->isEmpty()) {
            throw new \RuntimeException('There are no questions to translate yet.');
        }

        $raw = $this->askForTranslation($questions, $language);

        $decoded = $this->decode($raw);
        $rows    = $decoded['questions'] ?? (array_is_list($decoded) ? $decoded : []);

        // Keyed by the id WE sent, not by position. A model that returns nine
        // rows for ten questions would otherwise shift every translation after
        // the gap onto the wrong question — the same class of silent
        // misalignment the option count guards against.
        $byId = [];

        foreach ($rows as $row) {
            $row = (array) $row;

            if (isset($row['id'])) {
                $byId[(int) $row['id']] = $row;
            }
        }

        $translated = 0;
        $skipped    = 0;

        DB::transaction(function () use ($questions, $byId, $language, &$translated, &$skipped) {
            foreach ($questions as $question) {
                $row     = $byId[$question->id] ?? null;
                $options = array_values(array_map(
                    fn ($o) => trim((string) $o),
                    (array) ($row['options'] ?? []),
                ));

                $prompt = trim((string) ($row['prompt'] ?? ''));

                if (! $row || $prompt === '' || count($options) !== count($question->optionList())
                    || in_array('', $options, true)) {
                    $skipped++;

                    continue;
                }

                TrainingQuestionTranslation::updateOrCreate(
                    ['training_question_id' => $question->id, 'language' => $language],
                    [
                        'prompt'             => $prompt,
                        'options'            => $options,
                        'explanation'        => trim((string) ($row['explanation'] ?? '')) ?: null,
                        'machine_translated' => true,
                    ],
                );

                $translated++;
            }
        });

        if ($translated === 0) {
            throw new \RuntimeException(
                'Nothing usable came back. Try again — if it keeps happening, the questions may be too long.'
            );
        }

        return ['translated' => $translated, 'skipped' => $skipped, 'language' => $language];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, TrainingQuestion>  $questions
     */
    private function askForTranslation($questions, string $language): string
    {
        $apiKey = AppSetting::get('openrouter_api_key');

        if (empty($apiKey)) {
            throw new \RuntimeException('OpenRouter API key not configured. Go to Settings > API Keys.');
        }

        $payload = $questions->map(fn (TrainingQuestion $q) => [
            'id'          => $q->id,
            'prompt'      => $q->prompt,
            'options'     => $q->optionList(),
            'explanation' => $q->explanation,
        ])->values()->all();

        $previousTimeout = ExecutionTime::raise(180);

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
                'max_tokens' => 8192,
                // Lower than generation. There is no "give me a different
                // balance of questions" here — there is one right answer to
                // "what does this say in Malay", and drift is not a feature.
                'temperature' => 0.2,
                'messages'    => [
                    ['role' => 'system', 'content' => $this->translationSystemPrompt($language)],
                    ['role' => 'user',   'content' => json_encode(
                        ['questions' => $payload],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                    )],
                ],
            ]);

        ExecutionTime::restore($previousTimeout);

        if ($response->failed()) {
            Log::error('[training] OpenRouter translation error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            $message = $response->json('error.message') ?? ('HTTP ' . $response->status());

            throw new \RuntimeException('Translation failed: ' . $message);
        }

        return (string) ($response->json('choices.0.message.content') ?? '');
    }

    private function translationSystemPrompt(string $language): string
    {
        $brief = trim($this->languageBrief($language));

        return <<<PROMPT
        You translate training quizzes for restaurant and cafe staff.

        {$brief}

        You are given questions as JSON. Return JSON and nothing else:

        {"questions":[{"id":<the same id>,"prompt":"…","options":["…","…"],"explanation":"…"}]}

        RULES, and the first two are absolute:
        1. Return EVERY question, with the id it was given. Never merge, split,
           reorder or omit one.
        2. Return EXACTLY the same number of options per question, in exactly the
           same order. The answer key is stored as positions in that list, so
           moving an option marks the wrong answer correct.
        3. Translate the meaning, not the words. These are read at speed on a
           phone during a shift by somebody who may be holding a tray.
        4. Keep numbers, temperatures, times and measurements identical.
        5. Leave brand names, dish names and menu items as they are written.
        6. If an explanation is null or missing, return it as an empty string.
        PROMPT;
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

        $previousTimeout = ExecutionTime::raise(180);

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
                /*
                 * 8192, raised from 4096 after Malay hit the ceiling.
                 *
                 * Eight questions with options and explanations is comfortably
                 * inside 4096 tokens in English and NOT inside it in Bahasa
                 * Malaysia — the same content runs longer, and the tokeniser is
                 * less efficient on it again. The reply came back as valid JSON
                 * that simply stopped mid-array, which decodes to nothing, so
                 * the whole generation failed with every question already
                 * written and paid for. The parser salvages truncated replies
                 * now as well; this makes it rare rather than routine.
                 */
                'max_tokens' => 8192,
                // Low but not zero. Zero makes every regeneration return the
                // same eight questions, which defeats the "ask again, I want a
                // different balance" flow the button exists for.
                'temperature' => 0.4,
                'messages'   => [
                    ['role' => 'system', 'content' => $this->systemPrompt($language)],
                    ['role' => 'user',   'content' => $this->userPrompt($material, $course, $count, $difficulty, $language)],
                ],
            ]);

        ExecutionTime::restore($previousTimeout);

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

        // Nothing parsed whole — the usual reason is a reply that ran out of
        // tokens mid-array. Keep the questions that finished.
        $salvaged = $this->salvageTruncated($raw);

        if ($salvaged !== null) {
            Log::warning('[training] AI quiz reply was truncated; kept the complete questions', [
                'kept' => count($salvaged['questions'] ?? []),
            ]);

            return $salvaged;
        }

        Log::warning('[training] could not decode AI quiz reply', ['head' => mb_substr($raw, 0, 400)]);

        return [];
    }

    /**
     * Rescue the whole questions from a reply that stopped mid-sentence.
     *
     * A generation that runs out of output tokens returns perfectly good JSON
     * that simply ends partway through an object. json_decode answers that with
     * null, so seven finished questions are thrown away because the eighth was
     * cut in half — every one of them already written, already paid for, and
     * the author is told the AI "did not return any usable questions", which is
     * both wrong and unactionable.
     *
     * Walks the array tracking brace depth, staying aware of strings and their
     * escapes so a `{` inside a prompt does not shift the count, and rebuilds
     * the document from the last element that actually closed.
     *
     * @return array<string, mixed>|null
     */
    private function salvageTruncated(string $raw): ?array
    {
        $open = strpos($raw, '[');

        if ($open === false) {
            return null;
        }

        $depth    = 0;
        $inString = false;
        $escaped  = false;
        $lastGood = null;

        for ($i = $open + 1, $len = strlen($raw); $i < $len; $i++) {
            $char = $raw[$i];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === '"') {
                $inString = ! $inString;
                continue;
            }

            if ($inString) {
                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;

                // Back at the array's own level: this element is complete.
                if ($depth === 0) {
                    $lastGood = $i;
                }
            }
        }

        if ($lastGood === null) {
            return null;
        }

        $decoded = json_decode('{"questions":' . substr($raw, $open, $lastGood - $open + 1) . ']}', true);

        return is_array($decoded) && ($decoded['questions'] ?? []) !== [] ? $decoded : null;
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
