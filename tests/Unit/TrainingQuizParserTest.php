<?php

namespace Tests\Unit;

use App\Services\Training\QuizGeneratorService;
use App\Services\Training\SourceTextExtractor;
use Tests\TestCase;

/**
 * What the AI says, and what we are willing to ship.
 *
 * The failure this exists to stop is the quiet one: a perfectly-formed question
 * whose `correct` index points past the end of its own options. Nothing errors,
 * the question renders, and it is impossible to answer correctly — a trainee
 * gets it wrong and cannot see why. No amount of prompt wording fully prevents
 * it, so it is caught here.
 *
 * Extends the framework TestCase rather than PHPUnit's: the parser logs a
 * warning when a reply cannot be decoded at all, and a facade needs a container
 * behind it. No database is touched.
 */
class TrainingQuizParserTest extends TestCase
{
    private function parser(): QuizGeneratorService
    {
        return new QuizGeneratorService(new SourceTextExtractor());
    }

    public function test_it_reads_plain_json(): void
    {
        $result = $this->parser()->parseQuestions(json_encode([
            'questions' => [[
                'type' => 'mcq', 'prompt' => 'Chiller temperature?',
                'options' => ['4°C', '8°C', '12°C', '18°C'], 'correct' => [0],
                'explanation' => 'Below 4°C.', 'difficulty' => 'easy', 'topic' => 'Food safety',
            ]],
        ]));

        $this->assertCount(1, $result['questions']);
        $this->assertSame(0, $result['dropped']);
        $this->assertSame('Chiller temperature?', $result['questions'][0]['prompt']);
        $this->assertSame([0], $result['questions'][0]['correct']);
        $this->assertSame('Food safety', $result['questions'][0]['topic']);
    }

    /** Models wrap JSON in a fence and a sentence of preamble often enough. */
    public function test_it_reads_json_out_of_a_markdown_fence_with_preamble(): void
    {
        $raw = "Sure! Here are the questions you asked for:\n\n```json\n"
            . json_encode(['questions' => [[
                'type' => 'true_false', 'prompt' => 'Milk is an allergen.',
                'options' => ['True', 'False'], 'correct' => [0],
            ]]])
            . "\n```\n\nLet me know if you want more.";

        $result = $this->parser()->parseQuestions($raw);

        $this->assertCount(1, $result['questions']);
        $this->assertSame('true_false', $result['questions'][0]['type']);
    }

    /**
     * THE ONE THAT MATTERS. Dropped rather than repaired: inventing a correct
     * answer for a question about food safety is worse than asking one question
     * fewer.
     */
    public function test_it_drops_a_question_whose_correct_index_is_out_of_range(): void
    {
        $result = $this->parser()->parseQuestions(json_encode([
            'questions' => [
                ['type' => 'mcq', 'prompt' => 'Good one', 'options' => ['a', 'b', 'c', 'd'], 'correct' => [2]],
                ['type' => 'mcq', 'prompt' => 'Broken one', 'options' => ['a', 'b'], 'correct' => [7]],
            ],
        ]));

        $this->assertCount(1, $result['questions']);
        $this->assertSame(1, $result['dropped']);
        $this->assertSame('Good one', $result['questions'][0]['prompt']);
    }

    public function test_it_drops_a_question_with_no_correct_answer_at_all(): void
    {
        $result = $this->parser()->parseQuestions(json_encode([
            'questions' => [['prompt' => 'Nothing is right', 'options' => ['a', 'b'], 'correct' => []]],
        ]));

        $this->assertSame([], $result['questions']);
        $this->assertSame(1, $result['dropped']);
    }

    /** Every option correct is not a question. */
    public function test_it_drops_a_question_where_everything_is_correct(): void
    {
        $result = $this->parser()->parseQuestions(json_encode([
            'questions' => [['type' => 'multi', 'prompt' => 'All of them', 'options' => ['a', 'b'], 'correct' => [0, 1]]],
        ]));

        $this->assertSame([], $result['questions']);
        $this->assertSame(1, $result['dropped']);
    }

    public function test_it_drops_a_question_with_fewer_than_two_options(): void
    {
        $result = $this->parser()->parseQuestions(json_encode([
            'questions' => [['prompt' => 'Only one', 'options' => ['a'], 'correct' => [0]]],
        ]));

        $this->assertSame([], $result['questions']);
    }

    public function test_it_drops_a_question_with_an_empty_prompt(): void
    {
        $result = $this->parser()->parseQuestions(json_encode([
            'questions' => [['prompt' => '   ', 'options' => ['a', 'b'], 'correct' => [0]]],
        ]));

        $this->assertSame([], $result['questions']);
    }

    /** A single-answer type that came back with two is a mislabelled multi. */
    public function test_it_relabels_a_multi_answer_question_that_called_itself_mcq(): void
    {
        $result = $this->parser()->parseQuestions(json_encode([
            'questions' => [[
                'type' => 'mcq', 'prompt' => 'Which are allergens?',
                'options' => ['Milk', 'Egg', 'Salt', 'Water'], 'correct' => [0, 1],
            ]],
        ]));

        $this->assertSame('multi', $result['questions'][0]['type']);
    }

    /** ...and a "true_false" that came back with four options is not one. */
    public function test_it_relabels_a_true_false_that_has_four_options(): void
    {
        $result = $this->parser()->parseQuestions(json_encode([
            'questions' => [[
                'type' => 'true_false', 'prompt' => 'Which temperature?',
                'options' => ['4°C', '8°C', '12°C', '18°C'], 'correct' => [0],
            ]],
        ]));

        $this->assertSame('mcq', $result['questions'][0]['type']);
    }

    public function test_an_unparseable_reply_yields_nothing_rather_than_throwing(): void
    {
        $result = $this->parser()->parseQuestions("I'm sorry, I can't help with that.");

        $this->assertSame([], $result['questions']);
        $this->assertSame(0, $result['dropped']);
    }

    public function test_a_bare_list_without_the_questions_key_still_reads(): void
    {
        $result = $this->parser()->parseQuestions(json_encode([
            ['prompt' => 'Bare list', 'options' => ['a', 'b'], 'correct' => [1]],
        ]));

        $this->assertCount(1, $result['questions']);
    }

    public function test_an_unknown_difficulty_falls_back_to_medium(): void
    {
        $result = $this->parser()->parseQuestions(json_encode([
            'questions' => [[
                'prompt' => 'How hard?', 'options' => ['a', 'b'], 'correct' => [0],
                'difficulty' => 'impossible',
            ]],
        ]));

        $this->assertSame('medium', $result['questions'][0]['difficulty']);
    }
}
