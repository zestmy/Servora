<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\LmsUser;
use App\Models\Outlet;
use App\Models\TrainingAssignment;
use App\Models\TrainingCourse;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuiz;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\Training\CertificateService;
use App\Services\Training\LeaderboardService;
use App\Services\Training\LiveSessionService;
use App\Services\Training\ReportCardService;
use App\Services\Training\ScoringService;
use App\Services\Training\SelfPacedQuizService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Learning & Development, end to end.
 *
 * The cases here are the ones where being wrong is expensive rather than merely
 * visible: a quiz that can be re-answered after seeing a colleague's screen, a
 * leaderboard that ranks persistence instead of knowledge, a certificate issued
 * twice for the same course, and a course reachable by a trainee whose outlet
 * was never granted it.
 */
class TrainingModuleTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Outlet $otherOutlet;
    private User $manager;
    private LmsUser $trainee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Training Co', 'slug' => Str::slug('Training Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Bangsar', 'code' => 'BSR', 'is_active' => true,
        ]);

        $this->otherOutlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Damansara', 'code' => 'DMS', 'is_active' => true,
        ]);

        $this->manager = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
        ]);

        $this->trainee = LmsUser::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'name'       => 'Aisyah Rahman',
            'email'      => 'aisyah' . uniqid() . '@example.test',
            'password'   => 'secret-not-used',
            'status'     => 'approved',
        ]);
        $this->trainee->outlets()->sync([$this->outlet->id]);
    }

    /**
     * Sign a trainee in the way production does.
     *
     * NOT actingAs($trainee, 'lms'): that calls shouldUse('lms'), which makes
     * the trainee guard the application's DEFAULT guard for the rest of the
     * test. Nothing does that in production, and it sends every piece of `web`
     * middleware down a path built for an App\Models\User — EnsureAccountActive
     * calls isSystemRole() on whatever $request->user() hands it, so the
     * assertion under test never runs and a 500 arrives instead. Setting the
     * guard's user reproduces a signed-in trainee exactly, without moving the
     * default.
     */
    private function asTrainee(LmsUser $trainee): static
    {
        $this->app['auth']->guard('lms')->setUser($trainee);

        return $this;
    }

    private function course(array $overrides = []): TrainingCourse
    {
        return TrainingCourse::create(array_merge([
            'company_id' => $this->company->id,
            'title'      => 'Chiller discipline',
            'content'    => 'Chillers run between 0 and 4 degrees.',
            'status'     => 'published',
        ], $overrides));
    }

    /** A four-question quiz: three easy marks and one everybody gets wrong. */
    private function quiz(?TrainingCourse $course = null, array $overrides = []): TrainingQuiz
    {
        $quiz = TrainingQuiz::create(array_merge([
            'company_id'         => $this->company->id,
            'training_course_id' => ($course ?? $this->course())->id,
            'title'              => 'Chiller quiz',
            'status'             => 'published',
            'pass_mark'          => 70,
            'default_seconds'    => 20,
            'default_points'     => 1000,
            'shuffle_questions'  => false,
            'shuffle_options'    => false,
        ], $overrides));

        foreach (range(1, 4) as $n) {
            $quiz->questions()->create([
                'type'       => 'mcq',
                'prompt'     => "Question {$n}?",
                'options'    => ['Right', 'Wrong', 'Also wrong', 'Very wrong'],
                'correct'    => [0],
                'topic'      => $n === 4 ? 'Allergens' : 'Food safety',
                'sort_order' => $n,
            ]);
        }

        return $quiz->fresh('questions');
    }

    // ── Access ────────────────────────────────────────────────────────────

    /**
     * A course tagged to one branch is not visible from another. This is the
     * same rule the SOP library already applies, and the reason courses carry a
     * pivot rather than a nullable outlet_id.
     */
    public function test_a_course_tagged_to_another_outlet_is_invisible(): void
    {
        $mine   = $this->course(['title' => 'Mine']);
        $theirs = $this->course(['title' => 'Theirs']);
        $all    = $this->course(['title' => 'Everyone']);

        $mine->outlets()->sync([$this->outlet->id]);
        $theirs->outlets()->sync([$this->otherOutlet->id]);

        $visible = TrainingCourse::published()
            ->visibleToOutlets($this->trainee->accessibleOutletIds())
            ->pluck('title')
            ->all();

        sort($visible);

        // An untagged course is company-wide — that is what "everyone does the
        // allergen course" has to mean.
        $this->assertSame(['Everyone', 'Mine'], $visible);
        $this->assertNotContains('Theirs', $visible);
        $this->assertNotNull($all);
    }

    // ── Self-paced ────────────────────────────────────────────────────────

    public function test_a_self_paced_attempt_scores_passes_and_lands_on_the_record(): void
    {
        $quiz    = $this->quiz();
        $service = app(SelfPacedQuizService::class);

        $attempt = $service->startOrResume($quiz, $this->trainee);

        foreach ($quiz->questions as $i => $question) {
            // Three right, the last one wrong: 75%, which clears a 70 pass mark.
            $service->answer($attempt, $question, $i === 3 ? [1] : [0], 5.0);
        }

        $attempt = $service->finish($attempt);

        $this->assertSame(3, $attempt->correct_count);
        $this->assertSame(4, $attempt->question_count);
        $this->assertSame('75.00', (string) $attempt->percent);
        $this->assertTrue($attempt->passed);
        $this->assertNotNull($attempt->completed_at);

        // 5s of 20 → 87.5% of 1000, three times, plus the streak bonus at three
        // in a row. The wrong one is worth nothing.
        $this->assertGreaterThan(2000, $attempt->score);
    }

    /**
     * Being interrupted mid-quiz is normal on a shift. Resuming must not lose
     * the answers already given, and must not start the questions again.
     */
    public function test_an_interrupted_attempt_resumes_where_it_stopped(): void
    {
        $quiz    = $this->quiz();
        $service = app(SelfPacedQuizService::class);

        $attempt = $service->startOrResume($quiz, $this->trainee);
        $service->answer($attempt, $quiz->questions[0], [0], 3.0);
        $service->answer($attempt, $quiz->questions[1], [0], 3.0);

        $resumed = $service->startOrResume($quiz, $this->trainee);

        $this->assertSame($attempt->id, $resumed->id);
        $this->assertSame(2, $service->resumeIndex($resumed));
        $this->assertSame(2, $resumed->answers()->count());
    }

    /** A re-answer overwrites rather than double-counting — the unique index. */
    public function test_answering_the_same_question_twice_keeps_one_row(): void
    {
        $quiz    = $this->quiz();
        $service = app(SelfPacedQuizService::class);
        $attempt = $service->startOrResume($quiz, $this->trainee);

        $service->answer($attempt, $quiz->questions[0], [1], 4.0);
        $service->answer($attempt, $quiz->questions[0], [0], 4.0);

        $this->assertSame(1, $attempt->answers()->count());
        $this->assertTrue($attempt->answers()->first()->is_correct);
    }

    public function test_the_attempt_allowance_is_enforced(): void
    {
        $quiz    = $this->quiz(null, ['max_attempts' => 1]);
        $service = app(SelfPacedQuizService::class);

        $attempt = $service->startOrResume($quiz, $this->trainee);
        foreach ($quiz->questions as $question) {
            $service->answer($attempt, $question, [0], 2.0);
        }
        $service->finish($attempt);

        $this->expectException(\RuntimeException::class);
        $service->startOrResume($quiz->fresh(), $this->trainee);
    }

    // ── Live ──────────────────────────────────────────────────────────────

    public function test_a_live_round_runs_from_lobby_to_podium(): void
    {
        $quiz     = $this->quiz();
        $sessions = app(LiveSessionService::class);

        $session = $sessions->open($quiz, $this->outlet->id, $this->manager->id, 'Tuesday briefing');

        $this->assertSame('lobby', $session->status);
        $this->assertSame(4, $session->questionCount());
        $this->assertMatchesRegularExpression('/^\d{6}$/', $session->pin);

        $signedIn  = $sessions->join($session, 'Aisyah', $this->trainee);
        $anonymous = $sessions->join($session, 'Chef');

        // A nickname-only player still gets a scoreboard row and an attempt —
        // that is what makes the shift-briefing case work.
        $this->assertNull($anonymous->lms_user_id);
        $this->assertNotNull($anonymous->attempt);

        $sessions->start($session);
        $session->refresh();
        $this->assertSame('question', $session->status);

        $answer = $sessions->answer($session, $signedIn, [0]);
        $this->assertNotNull($answer);
        $this->assertTrue($answer->is_correct);

        // The anonymous player says nothing and should be recorded as having
        // missed it — otherwise a question everybody skipped looks unasked.
        $sessions->reveal($session->fresh());
        $this->assertSame(1, $anonymous->fresh()->attempt->answers()->count());
        $this->assertFalse($anonymous->fresh()->attempt->answers()->first()->is_correct);

        // Walk the rest of the room out.
        $session = $session->fresh();
        while (! $session->isLastQuestion()) {
            $sessions->next($session);
            $session = $session->fresh();
        }
        $sessions->end($session);
        $session->refresh();

        $this->assertSame('ended', $session->status);
        $this->assertNotNull($session->ended_at);

        $attempt = $signedIn->fresh()->attempt->fresh();
        $this->assertNotNull($attempt->completed_at);
        $this->assertSame(4, $attempt->question_count);
        $this->assertSame(1, $attempt->correct_count);
    }

    /**
     * A second tap must not rescore. Without this a player who glanced at a
     * neighbour's screen could answer again at leisure.
     */
    public function test_a_live_player_cannot_answer_the_same_question_twice(): void
    {
        $quiz     = $this->quiz();
        $sessions = app(LiveSessionService::class);
        $session  = $sessions->open($quiz, $this->outlet->id, $this->manager->id);
        $player   = $sessions->join($session, 'Aisyah', $this->trainee);

        $sessions->start($session);
        $session->refresh();

        $this->assertNotNull($sessions->answer($session, $player, [1]));  // wrong
        $this->assertNull($sessions->answer($session, $player, [0]));     // ignored

        $this->assertFalse($player->fresh()->attempt->answers()->first()->is_correct);
        $this->assertSame(0, $player->fresh()->score);
    }

    /** The window is the server's, not the phone's. */
    public function test_an_answer_after_the_window_closes_is_refused(): void
    {
        $quiz     = $this->quiz(null, ['default_seconds' => 5]);
        $sessions = app(LiveSessionService::class);
        $session  = $sessions->open($quiz, $this->outlet->id, $this->manager->id);
        $player   = $sessions->join($session, 'Slow Hand');

        $sessions->start($session);

        $session->refresh()->forceFill([
            'question_started_at' => now()->subSeconds(30),
        ])->save();

        $this->assertNull($sessions->answer($session->fresh(), $player, [0]));
    }

    public function test_a_pin_only_matches_a_live_room(): void
    {
        $quiz     = $this->quiz();
        $sessions = app(LiveSessionService::class);
        $session  = $sessions->open($quiz, $this->outlet->id, $this->manager->id);

        $this->assertNotNull($sessions->findByPin($session->pin));

        $sessions->end($session);

        $this->assertNull($sessions->findByPin($session->pin));
    }

    // ── Leaderboard ───────────────────────────────────────────────────────

    /**
     * Best per quiz per person, not the sum of every attempt. Summing would
     * rank by persistence, and would make retrying — which is exactly what an
     * unsure trainee should do — the way to win.
     */
    public function test_the_leaderboard_counts_a_person_s_best_attempt_only(): void
    {
        $quiz    = $this->quiz();
        $service = app(SelfPacedQuizService::class);

        foreach ([[0, 1, 1, 1], [0, 0, 0, 0]] as $run) {
            $attempt = $service->startOrResume($quiz, $this->trainee);
            foreach ($quiz->questions as $i => $question) {
                $service->answer($attempt, $question, [$run[$i]], 5.0);
            }
            $service->finish($attempt);
        }

        $board = app(LeaderboardService::class)->board($this->company->id, 'all');

        $this->assertCount(1, $board);
        $this->assertSame(1, $board[0]['quizzes']);
        $this->assertSame(100.0, $board[0]['accuracy']);
        $this->assertSame(1, $board[0]['rank']);
    }

    // ── Report card ───────────────────────────────────────────────────────

    public function test_the_report_card_names_the_topic_to_work_on(): void
    {
        $quiz    = $this->quiz();
        $service = app(SelfPacedQuizService::class);

        // Every "Allergens" question wrong, every "Food safety" one right.
        foreach (range(1, 2) as $ignored) {
            $attempt = $service->startOrResume($quiz, $this->trainee);
            foreach ($quiz->questions as $question) {
                $service->answer($attempt, $question, $question->topic === 'Allergens' ? [1] : [0], 4.0);
            }
            $service->finish($attempt);
        }

        $card = app(ReportCardService::class)->for($this->trainee);

        $this->assertSame(2, $card['attempts']);
        $this->assertCount(1, $card['weak_topics']);
        $this->assertSame('Allergens', $card['weak_topics'][0]['topic']);
        $this->assertSame(0.0, $card['weak_topics'][0]['accuracy']);
    }

    /**
     * An assignment on an OUTLET reaches everyone at that branch, including
     * people hired after it was set — which is the whole reason to prefer it
     * over naming individuals.
     */
    public function test_an_outlet_assignment_lands_on_a_trainee_who_was_never_named(): void
    {
        $course = $this->course();
        $this->quiz($course);

        TrainingAssignment::create([
            'company_id'         => $this->company->id,
            'training_course_id' => $course->id,
            'outlet_id'          => $this->outlet->id,
            'due_on'             => now()->subDay()->toDateString(),
        ]);

        $outstanding = app(ReportCardService::class)->outstanding($this->trainee);

        $this->assertCount(1, $outstanding);
        $this->assertSame('Chiller discipline', $outstanding[0]['title']);
        $this->assertTrue($outstanding[0]['overdue']);
    }

    public function test_passing_clears_the_assignment(): void
    {
        $course = $this->course();
        $quiz   = $this->quiz($course);

        TrainingAssignment::create([
            'company_id'         => $this->company->id,
            'training_course_id' => $course->id,
            'outlet_id'          => $this->outlet->id,
        ]);

        $service = app(SelfPacedQuizService::class);
        $attempt = $service->startOrResume($quiz, $this->trainee);
        foreach ($quiz->questions as $question) {
            $service->answer($attempt, $question, [0], 3.0);
        }
        $service->finish($attempt);

        $this->assertCount(0, app(ReportCardService::class)->outstanding($this->trainee));
    }

    // ── Certificates ──────────────────────────────────────────────────────

    /**
     * One valid certificate per (trainee, course). A staff file holding four
     * certificates for the same course cannot answer "are they current?".
     */
    public function test_passing_twice_updates_one_certificate_and_keeps_its_serial(): void
    {
        $course = $this->course(['is_compliance' => true, 'recertify_months' => 12]);
        $quiz   = $this->quiz($course, ['issues_certificate' => true]);

        $service = app(SelfPacedQuizService::class);

        $serials = [];
        foreach (range(1, 2) as $ignored) {
            $attempt = $service->startOrResume($quiz, $this->trainee);
            foreach ($quiz->questions as $question) {
                $service->answer($attempt, $question, [0], 3.0);
            }
            $service->finish($attempt);

            $serials[] = $this->trainee->certificates()->first()->serial;
        }

        $this->assertSame(1, $this->trainee->certificates()->count());
        $this->assertSame($serials[0], $serials[1]);
        $this->assertNotNull($this->trainee->certificates()->first()->expires_on);
    }

    public function test_failing_issues_no_certificate(): void
    {
        $quiz = $this->quiz(null, ['issues_certificate' => true]);

        $service = app(SelfPacedQuizService::class);
        $attempt = $service->startOrResume($quiz, $this->trainee);
        foreach ($quiz->questions as $question) {
            $service->answer($attempt, $question, [1], 3.0);
        }
        $service->finish($attempt);

        $this->assertSame(0, $this->trainee->certificates()->count());
    }

    /** Serials get read down a phone, so the ambiguous glyphs are excluded. */
    public function test_a_serial_carries_no_ambiguous_characters(): void
    {
        $serial = app(CertificateService::class)->newSerial();

        $this->assertMatchesRegularExpression('/^CERT-\d{4}-[A-Z2-9]{8}$/', $serial);
        $this->assertDoesNotMatchRegularExpression('/[OIL01]/', substr($serial, 10));
    }

    /** A revoked certificate stops printing. */
    public function test_a_revoked_certificate_pdf_is_gone(): void
    {
        $course = $this->course(['is_compliance' => true]);
        $quiz   = $this->quiz($course, ['issues_certificate' => true]);

        $service = app(SelfPacedQuizService::class);
        $attempt = $service->startOrResume($quiz, $this->trainee);
        foreach ($quiz->questions as $question) {
            $service->answer($attempt, $question, [0], 3.0);
        }
        $service->finish($attempt);

        $certificate = $this->trainee->certificates()->first();

        app(CertificateService::class)->revoke($certificate);

        $this->asTrainee($this->trainee)
            ->get(route('training.certificates.pdf', $certificate->id))
            ->assertStatus(410);
    }

    /** A trainee may take their own certificate and nobody else's. */
    public function test_a_trainee_cannot_download_a_colleague_s_certificate(): void
    {
        $course = $this->course(['is_compliance' => true]);
        $quiz   = $this->quiz($course, ['issues_certificate' => true]);

        $service = app(SelfPacedQuizService::class);
        $attempt = $service->startOrResume($quiz, $this->trainee);
        foreach ($quiz->questions as $question) {
            $service->answer($attempt, $question, [0], 3.0);
        }
        $service->finish($attempt);

        $certificate = $this->trainee->certificates()->first();

        $colleague = LmsUser::create([
            'company_id' => $this->company->id,
            'name'       => 'Someone Else',
            'email'      => 'else' . uniqid() . '@example.test',
            'password'   => 'secret-not-used',
            'status'     => 'approved',
        ]);

        $this->asTrainee($colleague)
            ->get(route('training.certificates.pdf', $certificate->id))
            ->assertForbidden();
    }

    // ── Scoring wiring ────────────────────────────────────────────────────

    /** finalise() sums the ROWS, so a re-answer cannot inflate the total. */
    public function test_the_attempt_total_is_summed_from_the_answer_rows(): void
    {
        $quiz    = $this->quiz();
        $service = app(SelfPacedQuizService::class);
        $scoring = app(ScoringService::class);

        $attempt = $service->startOrResume($quiz, $this->trainee);
        $service->answer($attempt, $quiz->questions[0], [1], 2.0);
        $service->answer($attempt, $quiz->questions[0], [0], 2.0);

        $scoring->finalise($attempt->fresh('answers'), $quiz);

        $this->assertSame(
            (int) $attempt->fresh()->answers()->sum('points_awarded'),
            $attempt->fresh()->score
        );
    }

    /** Opening a session with no questions is refused, not hosted empty. */
    public function test_a_quiz_with_no_questions_cannot_be_hosted(): void
    {
        $empty = TrainingQuiz::create([
            'company_id' => $this->company->id,
            'title'      => 'Nothing here',
            'status'     => 'published',
        ]);

        $this->expectException(\RuntimeException::class);

        app(LiveSessionService::class)->open($empty, $this->outlet->id, $this->manager->id);
    }

    public function test_two_players_cannot_share_a_nickname_in_one_room(): void
    {
        $quiz     = $this->quiz();
        $sessions = app(LiveSessionService::class);
        $session  = $sessions->open($quiz, $this->outlet->id, $this->manager->id);

        $first  = $sessions->join($session, 'Chef');
        $second = $sessions->join($session, 'Chef');

        // Two people called "Chef" is not an error worth stopping the room for
        // — and crucially the second one must NOT be handed the first one's
        // row, which is what matching anonymous players on their nickname did.
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('Chef', $first->nickname);
        $this->assertSame('Chef 2', $second->nickname);
    }

    /** A signed-in trainee rejoining keeps their score rather than resetting. */
    public function test_a_trainee_rejoining_gets_their_own_row_back(): void
    {
        $quiz     = $this->quiz();
        $sessions = app(LiveSessionService::class);
        $session  = $sessions->open($quiz, $this->outlet->id, $this->manager->id);

        $first  = $sessions->join($session, 'Aisyah', $this->trainee);
        $second = $sessions->join($session, 'Aisyah', $this->trainee);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, TrainingSession::find($session->id)->players()->count());
    }

    /** Options are index-addressed, so a question knows its own answer. */
    public function test_a_question_scores_against_its_own_options(): void
    {
        $question = new TrainingQuestion([
            'type' => 'mcq', 'prompt' => 'Which?',
            'options' => ['a', 'b', 'c'], 'correct' => [1],
        ]);

        $this->assertTrue($question->isCorrect([1]));
        $this->assertFalse($question->isCorrect([0]));
        $this->assertFalse($question->isCorrect([1, 2]));
    }
}
